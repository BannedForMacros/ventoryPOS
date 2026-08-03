<?php

namespace App\Console\Commands;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Empresa;
use App\Models\Entrada;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Salida;
use App\Models\SalidaTipo;
use App\Models\Stock;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Carga inicial de Vacanostra desde produccion/data/vacanostra.json (exportado
 * de stock-inicial-vacanostra.xlsx, hoja "tienda").
 *
 * LA HOJA REPARTE CADA PRODUCTO EN TRES DESTINOS, y esta es la traducción:
 *
 *   queda    (281 und) → se queda en la tienda
 *   retornar (384 und) → vuelve a Comercial Lavagna 1 / 2
 *   facturar (247 und) → viene en camino, llega en 3-5 días
 *
 * Por eso el INVENTARIO DE APERTURA son 665 unidades (queda + retornar): es lo
 * que Vacanostra tenía físicamente ANTES de devolver. Acto seguido se registra
 * la salida del retorno, que lo deja en las 281 con las que opera. Cargar
 * directamente 281 dejaría la devolución sin rastro en el kardex y sin
 * documento que mostrarle a Lavagna.
 *
 * Las 247 de facturar NO entran al stock: quedan como entradas en tránsito y
 * suman recién cuando se marque la recepción.
 *
 * Idempotente: se puede correr varias veces sin duplicar.
 *
 *   php artisan vacanostra:importar --ruc=20609876543
 *   php artisan vacanostra:importar --fresh    (borra la carga previa y rehace)
 */
class ImportarVacanostra extends Command
{
    protected $signature = 'vacanostra:importar {--ruc=20609876543} {--fresh}';

    protected $description = 'Carga stock inicial, retorno a Lavagna y compras en tránsito de Vacanostra';

    /**
     * Fecha del conteo físico (corte de apertura) y fecha de los movimientos.
     *
     * VAN EN DÍAS DISTINTOS Y ES OBLIGATORIO: `Stock::reconstruir` trata el
     * inventario inicial como un corte y solo cuenta los movimientos
     * ESTRICTAMENTE POSTERIORES a su fecha (`fecha > corte 23:59:59`), porque da
     * por hecho que todo lo del día del conteo ya está dentro del conteo. Si la
     * salida del retorno lleva la misma fecha que la apertura, Recalcular la
     * ignora y el stock rebota de 281 a 665.
     *
     * Semánticamente también es lo correcto: primero se contó lo que había
     * (665) y al día siguiente salió la devolución (384).
     */
    private string $fechaApertura;
    private string $fecha;

    public function handle(): int
    {
        $ruta = base_path('produccion/data/vacanostra.json');
        if (!file_exists($ruta)) {
            $this->error("No existe {$ruta}. Genera el JSON desde el Excel primero.");
            return self::FAILURE;
        }

        $data    = json_decode(file_get_contents($ruta), true);
        $empresa = Empresa::where('ruc', $this->option('ruc'))->first();
        if (!$empresa) {
            $this->error("No existe la empresa con RUC {$this->option('ruc')}.");
            return self::FAILURE;
        }

        $almacen = Almacen::where('empresa_id', $empresa->id)->where('activo', true)->first();
        $user    = User::where('empresa_id', $empresa->id)->first();
        $this->fechaApertura = now()->subDay()->toDateString();
        $this->fecha         = now()->toDateString();

        if (!$almacen || !$user) {
            $this->error('La empresa no tiene almacén o usuario. Créala con el panel de administración.');
            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->limpiar($empresa, $almacen);
        }

        DB::transaction(function () use ($data, $empresa, $almacen, $user) {
            $this->info('Proveedores...');
            $provs = $this->proveedores($empresa, $data);

            $this->info('Catálogo de productos...');
            [$productos, $unidad] = $this->catalogo($empresa, $data);

            $this->info('Inventario de apertura (queda + retorno)...');
            $apertura = $this->stockInicial($empresa, $almacen, $data, $productos);

            // Materializa el saldo a partir del corte recién escrito. En la
            // primera corrida deja las 665 de la apertura, que es lo que la
            // salida del retorno necesita tener disponible para confirmarse; en
            // las siguientes deja 281, porque la salida ya existe y cuenta.
            foreach (array_values($productos) as $productoId) {
                Stock::reconstruir($almacen->id, $productoId);
            }

            $this->info('Retorno a Comercial Lavagna...');
            $this->retorno($empresa, $almacen, $user, $data, $productos, $unidad);

            $this->info('Compras en camino...');
            $this->transito($empresa, $almacen, $user, $data, $productos, $provs, $unidad);

            // El saldo se DERIVA de los documentos, no se escribe a mano: es lo
            // que hace que correr el comando dos veces dé el mismo resultado.
            $this->info('Recalculando stock desde los documentos...');
            foreach (array_values($productos) as $productoId) {
                Stock::reconstruir($almacen->id, $productoId);
            }

            $this->resumen($empresa, $almacen, $apertura);
        });

        return self::SUCCESS;
    }

    /* ── Proveedores ───────────────────────────────────────────────────────── */

    /**
     * Dos niveles: las dos Comercial Lavagna (quienes FACTURAN la mercadería y a
     * quienes se devuelve) y los proveedores de marca del Excel, identificados
     * por un código de 3 letras.
     *
     * DOS CÓDIGOS PUEDEN SER LA MISMA EMPRESA: 4LO y FDL comparten el RUC de
     * Food for Life, y PRR y SAL el de Salbur. Como `proveedores` tiene UNIQUE
     * (empresa_id, numero_documento), se consolidan en un solo registro y ambos
     * códigos quedan anotados en `observacion` para poder cruzar con el Excel.
     * Por eso salen 33 proveedores de 35 códigos.
     *
     * @return array<string, int> código => proveedor_id
     */
    private function proveedores(Empresa $empresa, array $data): array
    {
        $out = [];

        foreach (['CL1' => 'COMERCIAL LAVAGNA 1', 'CL2' => 'COMERCIAL LAVAGNA 2'] as $cod => $nombre) {
            $out[$cod] = Proveedor::updateOrCreate(
                ['empresa_id' => $empresa->id, 'razon_social' => $nombre],
                ['tipo_documento' => 'RUC', 'nombre_comercial' => $nombre, 'activo' => true],
            )->id;
        }

        $ruta   = base_path('produccion/data/vacanostra_proveedores.json');
        $reales = file_exists($ruta) ? json_decode(file_get_contents($ruta), true) : [];

        // Un registro por RUC; los códigos que lo comparten se agrupan.
        $porRuc = [];
        foreach ($reales as $p) {
            $porRuc[$p['ruc']]['datos']    = $p;
            $porRuc[$p['ruc']]['codigos'][] = $p['cod'];
        }

        foreach ($porRuc as $ruc => $g) {
            $p = $g['datos'];

            $proveedor = Proveedor::updateOrCreate(
                ['empresa_id' => $empresa->id, 'numero_documento' => $ruc],
                [
                    'tipo_documento'   => 'RUC',
                    'razon_social'     => $p['razon'],
                    'nombre_comercial' => $this->nombreCorto($p['razon']),
                    'direccion'        => $p['dir'],
                    'observacion'      => 'Código en Excel: ' . implode(', ', $g['codigos']),
                    'activo'           => true,
                ],
            );

            foreach ($g['codigos'] as $cod) {
                $out[$cod] = $proveedor->id;
            }
        }

        // Códigos que quedaron sin nombre real: se crean con el código como
        // razón social para no perder la referencia del Excel.
        foreach ($data['proveedores'] as $cod) {
            if (isset($out[$cod])) continue;
            $out[$cod] = Proveedor::updateOrCreate(
                ['empresa_id' => $empresa->id, 'razon_social' => $cod],
                ['nombre_comercial' => $cod, 'observacion' => 'Código en Excel: ' . $cod, 'activo' => true],
            )->id;
        }

        // Restos de una carga anterior: los registros cuya razón social seguía
        // siendo el código de 3 letras y que ahora tienen su nombre real.
        Proveedor::where('empresa_id', $empresa->id)
            ->whereIn('razon_social', array_column($reales, 'cod'))
            ->whereNull('numero_documento')
            ->delete();

        $this->line('  ' . count($porRuc) . ' proveedores reales (' . count($reales) . ' códigos) + 2 Comercial Lavagna.');

        return $out;
    }

    /** Nombre de pantalla: la razón social hasta antes de la forma societaria. */
    private function nombreCorto(string $razon): string
    {
        $corto = preg_split('/\s+(S\.?A\.?C\.?|S\.?A\.?|E\.?I\.?R\.?L\.?|S\.?R\.?L\.?|SAC|SA|EIRL|SRL|EMPRESA INDIVIDUAL)\b/i', $razon)[0];
        return trim($corto, " .,-") ?: $razon;
    }

    /* ── Catálogo ──────────────────────────────────────────────────────────── */

    /** @return array{0: array<string,int>, 1: UnidadMedida} código => producto_id */
    private function catalogo(Empresa $empresa, array $data): array
    {
        $unidad = UnidadMedida::firstOrCreate(
            ['empresa_id' => $empresa->id, 'abreviatura' => 'UND'],
            ['nombre' => 'Unidad', 'activo' => true],
        );

        $categoria = Categoria::firstOrCreate(
            ['empresa_id' => $empresa->id, 'nombre' => 'General'],
            ['activo' => true],
        );

        $mapa = [];
        $sinPrecio = 0;

        foreach ($data['productos'] as $p) {
            $producto = Producto::updateOrCreate(
                ['empresa_id' => $empresa->id, 'codigo' => $p['codigo']],
                [
                    'categoria_id'   => $categoria->id,
                    'nombre'         => $p['nombre'],
                    'tipo'           => 'producto',
                    'tipo_precio'    => 'fijo',
                    'precio_venta'   => $p['precio'],
                    'precio_costo'   => $p['costo'],
                    'activo'         => true,
                    'incluye_igv'    => true,
                    'controla_stock' => true,
                ],
            );

            $producto->unidades()->updateOrCreate(
                ['unidad_medida_id' => $unidad->id],
                [
                    'es_base'           => true,
                    'factor_conversion' => 1,
                    'tipo_precio'       => 'fijo',
                    'precio_venta'      => $p['precio'],
                    'precio_costo'      => $p['costo'],
                    'activo'            => true,
                ],
            );

            $mapa[$p['codigo']] = $producto->id;
            if ($p['precio'] <= 0 && ($p['queda'] > 0 || $p['facturar'] > 0)) $sinPrecio++;
        }

        $this->line("  {$this->fmt(count($mapa))} productos. Sin precio de venta pero con movimiento: {$sinPrecio}.");

        return [$mapa, $unidad];
    }

    /* ── Inventario de apertura ────────────────────────────────────────────── */

    /**
     * `stock_iniciales` es el corte: marca que ese conteo YA incluye todo lo
     * anterior, así que ni Recalcular ni la reconstrucción del kardex lo van a
     * duplicar sumando documentos viejos.
     *
     * SOLO escribe el corte, nunca toca `stock` directamente. El saldo real lo
     * deriva `Stock::reconstruir()` al final (apertura − retorno + recibidas),
     * que es lo que hace idempotente al comando: forzar aquí el stock a la
     * apertura hacía que una segunda corrida devolviera al almacén las 384
     * unidades que ya se habían retornado.
     */
    private function stockInicial(Empresa $empresa, Almacen $almacen, array $data, array $productos): float
    {
        $total = 0.0;

        foreach ($data['productos'] as $p) {
            // Apertura = lo que se queda + lo que se va a devolver: ambas cosas
            // estaban físicamente en la tienda cuando se hizo el conteo.
            $cantidad = round($p['queda'] + $p['retornar'], 4);
            if ($cantidad <= 0) continue;

            $productoId = $productos[$p['codigo']];

            DB::table('stock_iniciales')->updateOrInsert(
                ['almacen_id' => $almacen->id, 'producto_id' => $productoId],
                [
                    'empresa_id' => $empresa->id,
                    'fecha'      => $this->fechaApertura,
                    'cantidad'   => $cantidad,
                    'costo'      => $p['costo'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $total += $cantidad;
        }

        $this->line("  Apertura: {$this->fmt($total)} unidades.");
        return $total;
    }

    /* ── Retorno a Lavagna ─────────────────────────────────────────────────── */

    /**
     * Una salida por cada Lavagna. Los 5 proveedores cuyo destino no se puede
     * deducir (no aparecen en FACTURAR, así que no hay a quién mapearlos) van a
     * una tercera salida marcada POR ASIGNAR, para que nadie la dé por buena.
     */
    private function retorno(Empresa $empresa, Almacen $almacen, User $user, array $data, array $productos, UnidadMedida $unidad): void
    {
        $tipo = SalidaTipo::updateOrCreate(
            ['empresa_id' => $empresa->id, 'slug' => 'retorno_proveedor'],
            ['nombre' => 'Retorno a proveedor', 'es_sistema' => false, 'activo' => true, 'orden' => 15],
        );

        $grupos = [];
        foreach ($data['productos'] as $p) {
            if ($p['retornar'] <= 0) continue;
            $grupos[$p['empresa_retorno'] ?? 'SIN_ASIGNAR'][] = $p;
        }

        foreach ($grupos as $destino => $items) {
            $etiqueta = match ($destino) {
                'CL1' => 'RETORNO COMERCIAL LAVAGNA 1',
                'CL2' => 'RETORNO COMERCIAL LAVAGNA 2',
                default => 'RETORNO POR ASIGNAR',
            };

            if (Salida::where('empresa_id', $empresa->id)->where('numero_documento', $etiqueta)->exists()) {
                $this->line("  {$etiqueta}: ya existe, se omite.");
                continue;
            }

            $salida = Salida::create([
                'empresa_id'       => $empresa->id,
                'almacen_id'       => $almacen->id,
                'user_id'          => $user->id,
                'salida_tipo_id'   => $tipo->id,
                'numero_documento' => $etiqueta,
                'fecha'            => $this->fecha,
                'estado'           => 'borrador',
                'total'            => 0,
                'observacion'      => $destino === 'SIN_ASIGNAR'
                    ? 'Proveedores sin destino deducible (EXC, JEI, OCU, SAL, VDO): confirmar a qué Lavagna van antes de dar por cerrada la devolución.'
                    : 'Devolución de mercadería según inventario de apertura.',
            ]);

            $unidades = 0.0;
            foreach ($items as $p) {
                $salida->detalles()->create([
                    'producto_id'       => $productos[$p['codigo']],
                    'unidad_medida_id'  => $unidad->id,
                    'cantidad'          => $p['retornar'],
                    'factor_conversion' => 1,
                    'cantidad_base'     => $p['retornar'],
                    'costo_unitario'    => $p['costo'],
                    'subtotal'          => round($p['retornar'] * $p['costo'], 2),
                ]);
                $unidades += $p['retornar'];
            }

            $salida->confirmar();
            $this->line("  {$etiqueta}: " . count($items) . " productos, {$this->fmt($unidades)} und.");
        }
    }

    /* ── Compras en camino ─────────────────────────────────────────────────── */

    /**
     * Una entrada en tránsito por cada Lavagna: son ellas las que facturan, así
     * que son ellas el proveedor de la compra y a quien se le debe. El código de
     * 3 letras del Excel es la marca del producto, no quien emite la factura.
     */
    private function transito(Empresa $empresa, Almacen $almacen, User $user, array $data, array $productos, array $provs, UnidadMedida $unidad): void
    {
        $grupos = [];
        foreach ($data['productos'] as $p) {
            if ($p['facturar'] <= 0) continue;
            $grupos[$p['empresa'] ?? 'CL1'][] = $p;
        }

        foreach ($grupos as $emp => $items) {
            $nombre = $emp === 'CL2' ? 'COMERCIAL LAVAGNA 2' : 'COMERCIAL LAVAGNA 1';

            if (Entrada::where('empresa_id', $empresa->id)->where('observacion', "Compra en camino — {$nombre}")->exists()) {
                $this->line("  {$nombre}: ya existe, se omite.");
                continue;
            }

            $entrada = Entrada::create([
                'empresa_id'   => $empresa->id,
                'almacen_id'   => $almacen->id,
                'user_id'      => $user->id,
                'proveedor_id' => $provs[$emp] ?? null,
                'proveedor'    => $nombre,
                'tipo'         => 'compra',
                'fecha'        => $this->fecha,
                'estado'       => 'borrador',
                'total'        => 0,
                'estado_pago'  => 'pendiente',
                'observacion'  => "Compra en camino — {$nombre}",
            ]);
            $entrada->update(['correlativo' => Entrada::generarCorrelativo($empresa->id, $this->fecha)]);

            $total = 0.0; $unidades = 0.0;
            foreach ($items as $p) {
                $subtotal = round($p['facturar'] * $p['costo'], 2);
                $entrada->detalles()->create([
                    'producto_id'       => $productos[$p['codigo']],
                    'unidad_medida_id'  => $unidad->id,
                    'cantidad'          => $p['facturar'],
                    'factor_conversion' => 1,
                    'cantidad_base'     => $p['facturar'],
                    'precio_costo'      => $p['costo'],
                    'subtotal'          => $subtotal,
                    // Algunas líneas ya traen su factura; el resto la recibe después.
                    'numero_documento'  => $p['documento'],
                ]);
                $total += $subtotal; $unidades += $p['facturar'];
            }

            $entrada->update(['total' => round($total, 2)]);
            // 5 días hábiles es el plazo que maneja Lavagna; se ajusta a mano si cambia.
            $entrada->marcarEnTransito(now()->addDays(5)->toDateString());

            $this->line("  {$nombre}: " . count($items) . " productos, {$this->fmt($unidades)} und, S/ " . number_format($total, 2));
        }
    }

    /* ── Utilidades ────────────────────────────────────────────────────────── */

    private function limpiar(Empresa $empresa, Almacen $almacen): void
    {
        $this->warn('--fresh: borrando carga previa...');
        DB::table('salidas_detalle')->whereIn('salida_id',
            DB::table('salidas')->where('empresa_id', $empresa->id)->pluck('id'))->delete();
        DB::table('salidas')->where('empresa_id', $empresa->id)->delete();
        DB::table('entradas_detalle')->whereIn('entrada_id',
            DB::table('entradas')->where('empresa_id', $empresa->id)->pluck('id'))->delete();
        DB::table('entradas')->where('empresa_id', $empresa->id)->delete();
        DB::table('movimientos_inventario')->where('empresa_id', $empresa->id)->delete();
        DB::table('stock_iniciales')->where('empresa_id', $empresa->id)->delete();
        DB::table('stock')->where('almacen_id', $almacen->id)->delete();
    }

    private function resumen(Empresa $empresa, Almacen $almacen, float $apertura): void
    {
        $stock = (float) Stock::where('almacen_id', $almacen->id)->sum('cantidad');
        $transito = app(\App\Services\MercaderiaTransitoService::class)->resumen($empresa->id);

        $this->newLine();
        $this->getOutput()->writeln('<bg=green;fg=white> LISTO </>');
        $this->line("  Apertura            : {$this->fmt($apertura)} und");
        $this->line("  Stock operativo     : {$this->fmt($stock)} und  (apertura − retorno)");
        $this->line("  En camino           : {$transito['en_camino']} entradas · S/ " . number_format($transito['valor'], 2));
        $this->newLine();
    }

    private function fmt(float|int $n): string
    {
        return number_format((float) $n, 0);
    }
}
