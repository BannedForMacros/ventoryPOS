<?php

namespace App\Services;

use App\Models\ClienteAnticipo;
use App\Models\ClienteAnticipoItem;
use App\Models\Conductor;
use App\Models\Producto;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\Venta;
use App\Models\Viaje;
use App\Models\ViajeParada;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Viajes de reparto: un vehículo con su chofer lleva ventas a los clientes.
 *
 * ─── EL VIAJE NO MUEVE STOCK ───────────────────────────────────────────────────
 *
 * Igual que la guía, el viaje es la HOJA DE RUTA, no el movimiento. El stock lo
 * siguen moviendo los mismos documentos de siempre:
 *  - Parada "pendiente": mercadería por entregar. Al marcarla entregada se usa
 *    EntregaPendienteService, la MISMA entrega de la bandeja de Despachos, así
 *    el kardex, el saldo del pedido y la auditoría no tienen un segundo camino.
 *  - Parada "directa": la venta ya sacó su stock al venderse (p. ej. "puesto en
 *    obra" con la placa elegida en el POS). El viaje solo deja constancia.
 *
 * ─── UN PEDIDO GRANDE VA EN VARIOS VIAJES ──────────────────────────────────────
 *
 * 18,000 ladrillos no entran en un camión de 4,500: cada viaje lleva una parte.
 * Lo que va cargado en un viaje abierto queda "asignado" y no se puede volver a
 * cargar en otro; al cerrar el viaje, lo que volvió sin entregar se libera solo.
 */
class ViajeService
{
    private const EPS = 0.00009;

    private const RELACIONES_PEDIDO = [
        'cliente:id,nombres,apellidos,razon_social,telefono,direccion,es_cliente_general',
        'venta:id,numero,fecha_venta,cliente_direccion,cliente_telefono,ruta_entrega_id,entrega_programada,saldo_pendiente',
        'venta.rutaEntrega:id,nombre,zona',
        'items.producto:id,peso_kg',
    ];

    public function __construct(
        private EntregaPendienteService $entregas,
    ) {}

    // ── Lectura ──────────────────────────────────────────────────────────────

    /**
     * Cantidad de cada ítem de pedido ya cargada en paradas que siguen por
     * entregar en viajes abiertos.
     *
     * @param  int[] $itemIds
     * @return Collection<int, float> cliente_anticipo_item_id => cantidad
     */
    public function asignado(array $itemIds, ?int $excluirViajeId = null): Collection
    {
        if (empty($itemIds)) return collect();

        return DB::table('viaje_parada_items as vpi')
            ->join('viaje_paradas as vp', 'vp.id', '=', 'vpi.viaje_parada_id')
            ->join('viajes as v', 'v.id', '=', 'vp.viaje_id')
            ->whereIn('vpi.cliente_anticipo_item_id', $itemIds)
            ->where('vp.modo', 'pendiente')
            ->where('vp.estado', 'por_entregar')
            ->whereIn('v.estado', Viaje::ABIERTOS)
            ->when($excluirViajeId, fn ($q) => $q->where('v.id', '<>', $excluirViajeId))
            ->groupBy('vpi.cliente_anticipo_item_id')
            ->selectRaw('vpi.cliente_anticipo_item_id as id, SUM(vpi.cantidad) as t')
            ->pluck('t', 'id')
            ->map(fn ($t) => (float) $t);
    }

    /**
     * Pedidos por entregar que todavía se pueden cargar (lo que falta entregar
     * menos lo que ya va en otro viaje abierto). Mismo alcance que Despachos:
     * un usuario de un local ve solo los pedidos de su local.
     */
    public function pendientesDisponibles(User $user, ?int $excluirViajeId = null, string $buscar = '', int $limite = 60, array $incluirIds = []): array
    {
        $base = fn () => ClienteAnticipo::query()
            ->where('cliente_anticipos.empresa_id', $user->empresa_id)
            ->where('cliente_anticipos.tipo_valorizacion', 'material')
            ->where('cliente_anticipos.estado', 'activo')
            ->whereNotNull('cliente_anticipos.venta_id')
            ->join('ventas', 'ventas.id', '=', 'cliente_anticipos.venta_id')
            ->where('ventas.estado', 'completada')
            ->when($user->local_id, fn ($q) => $q->where('ventas.local_id', $user->local_id));

        $anticipos = $base()
            ->when($buscar !== '', fn ($q) => $q->where(fn ($s) => $s
                ->where('ventas.numero', 'ilike', "%{$buscar}%")
                ->orWhere('ventas.cliente_direccion', 'ilike', "%{$buscar}%")
                ->orWhereHas('cliente', fn ($c) => $c
                    ->where('nombres', 'ilike', "%{$buscar}%")
                    ->orWhere('apellidos', 'ilike', "%{$buscar}%")
                    ->orWhere('razon_social', 'ilike', "%{$buscar}%"))
                ->orWhereHas('items', fn ($i) => $i->where('producto_nombre', 'ilike', "%{$buscar}%"))))
            ->with(self::RELACIONES_PEDIDO)
            ->orderByRaw('ventas.entrega_programada ASC NULLS LAST')
            ->orderBy('cliente_anticipos.id')
            ->select('cliente_anticipos.*')
            ->limit($limite)
            ->get();

        // Al editar, los pedidos que ya van en el viaje siempre vienen, aunque
        // queden fuera del límite: si no, el formulario los soltaría al guardar.
        $faltan = array_diff($incluirIds, $anticipos->pluck('id')->all());
        if (!empty($faltan)) {
            $anticipos = $anticipos->concat($base()->whereIn('cliente_anticipos.id', $faltan)
                ->with(self::RELACIONES_PEDIDO)->select('cliente_anticipos.*')->get());
        }

        $asignado = $this->asignado($anticipos->flatMap->items->pluck('id')->all(), $excluirViajeId);

        return $anticipos->map(function (ClienteAnticipo $a) use ($asignado) {
            $items = $a->items->map(function (ClienteAnticipoItem $i) use ($asignado) {
                $pendiente  = (float) $i->cantidad_pendiente;
                $enViaje    = (float) ($asignado[$i->id] ?? 0);

                return [
                    'id'                => $i->id,
                    'producto_id'       => $i->producto_id,
                    'producto_nombre'   => $i->producto_nombre,
                    'unidad_nombre'     => $i->unidad_nombre,
                    'factor_conversion' => (float) $i->factor_conversion,
                    'pendiente'         => $pendiente,
                    'en_viaje'          => $enViaje,
                    'disponible'        => max(0, round($pendiente - $enViaje, 4)),
                    'peso_unitario'     => $i->producto?->peso_kg !== null
                        ? round((float) $i->producto->peso_kg * (float) $i->factor_conversion, 4) : null,
                ];
            })->filter(fn ($i) => $i['pendiente'] > self::EPS)->values();

            return [
                'id'          => $a->id,
                'venta_id'    => $a->venta_id,
                'venta'       => $a->venta?->numero,
                'cliente'     => self::nombreCliente($a->cliente),
                'direccion'   => $a->venta?->cliente_direccion ?? $a->cliente?->direccion,
                'telefono'    => $a->venta?->cliente_telefono ?? $a->cliente?->telefono,
                'ruta'        => $a->venta?->rutaEntrega?->etiqueta,
                'programada'  => $a->venta?->entrega_programada?->toIso8601String(),
                'saldo_venta' => (float) ($a->venta?->saldo_pendiente ?? 0),
                'items'       => $items,
            ];
        })->filter(fn ($a) => collect($a['items'])->sum('disponible') > self::EPS)->values()->all();
    }

    /** Peso cargado, capacidad y % de uso del vehículo. */
    public function carga(Viaje $viaje): array
    {
        $viaje->loadMissing(['paradas.items', 'vehiculo']);
        $paradas  = $viaje->paradas->where('estado', '<>', 'no_entregado');
        $peso     = round((float) $paradas->sum(fn ($p) => $p->pesoKg()), 3);
        $sinPeso  = $paradas->flatMap->items->contains(fn ($i) => $i->peso_kg === null);
        $capacidad = (float) ($viaje->vehiculo?->capacidad_kg ?? 0);

        return [
            'peso_kg'      => $peso,
            'capacidad_kg' => $capacidad > 0 ? $capacidad : null,
            'porcentaje'   => $capacidad > 0 ? round($peso / $capacidad * 100, 1) : null,
            'sin_peso'     => $sinPeso,
        ];
    }

    // ── Armar / editar ───────────────────────────────────────────────────────

    /**
     * Crea un viaje o edita uno abierto. Las paradas que ya se resolvieron
     * (entregadas o devueltas) se conservan; las que siguen por entregar se
     * reemplazan por las enviadas.
     *
     * $data: tipo, fecha, hora_salida, vehiculo_id, conductor_id, ayudantes[],
     *        almacen_id, ruta_entrega_id, origen, destino, proveedor_id,
     *        entrada_id, observacion,
     *        paradas[]: {cliente_anticipo_id, items[]: {cliente_anticipo_item_id, cantidad}}
     *                 | {venta_id}  (venta ya entregada al venderse)
     */
    public function guardar(?Viaje $viaje, array $data, User $user): Viaje
    {
        return DB::transaction(function () use ($viaje, $data, $user) {
            $empresaId = (int) $user->empresa_id;

            if ($viaje) {
                $viaje = Viaje::whereKey($viaje->id)->lockForUpdate()->firstOrFail();
                if (!$viaje->abierto()) {
                    throw ValidationException::withMessages(['viaje' => "El viaje {$viaje->numero} ya está {$this->etiquetaEstado($viaje->estado)}: no se puede cambiar."]);
                }
            }

            $tipo     = in_array($data['tipo'] ?? null, Viaje::TIPOS, true) ? $data['tipo'] : 'reparto';
            $vehiculo = $this->vehiculoOperativo($empresaId, (int) $data['vehiculo_id'], $viaje?->vehiculo_id);
            $chofer   = $this->chofer($empresaId, $data['conductor_id'] ?? null);
            $ayudantes = $this->ayudantes($empresaId, (array) ($data['ayudantes'] ?? []), $chofer?->id);

            $cabecera = [
                'tipo'            => $tipo,
                'fecha'           => substr((string) $data['fecha'], 0, 10),
                'hora_salida'     => $data['hora_salida'] ?? null ?: null,
                'vehiculo_id'     => $vehiculo->id,
                'conductor_id'    => $chofer?->id,
                'almacen_id'      => $data['almacen_id'] ?? null ?: null,
                'ruta_entrega_id' => $data['ruta_entrega_id'] ?? null ?: null,
                'origen'          => self::texto($data['origen'] ?? null),
                'destino'         => self::texto($data['destino'] ?? null),
                'proveedor_id'    => $tipo === 'abastecimiento' ? ($data['proveedor_id'] ?? null ?: null) : null,
                'entrada_id'      => $tipo === 'abastecimiento' ? ($data['entrada_id'] ?? null ?: null) : null,
                'observacion'     => self::texto($data['observacion'] ?? null),
            ];

            if ($viaje) {
                $viaje->update($cabecera);
            } else {
                $viaje = Viaje::create($cabecera + [
                    'empresa_id' => $empresaId,
                    'numero'     => Viaje::generarNumero($empresaId),
                    'estado'     => 'programado',
                    'user_id'    => $user->id,
                ]);
            }

            $viaje->ayudantes()->sync($ayudantes);

            $paradas = (array) ($data['paradas'] ?? []);
            if ($tipo === 'abastecimiento' && !empty($paradas)) {
                throw ValidationException::withMessages(['paradas' => 'Un viaje de abastecimiento trae carga al almacén: no lleva entregas a clientes.']);
            }
            $this->reemplazarParadas($viaje, $paradas, $user);

            AuditoriaService::log($viaje->wasRecentlyCreated ? 'viaje.creado' : 'viaje.editado', $viaje, [
                'numero'   => $viaje->numero,
                'vehiculo' => $vehiculo->placa,
                'chofer'   => $chofer?->nombre_completo,
                'paradas'  => $viaje->paradas()->count(),
            ], $user);

            return $viaje->fresh(['paradas.items', 'vehiculo', 'conductor', 'ayudantes']);
        });
    }

    private function reemplazarParadas(Viaje $viaje, array $paradas, User $user): void
    {
        // Lo ya entregado o devuelto de un pedido no se toca: es historia del
        // viaje y movió stock. Las directas sí se reemplazan: no mueven nada.
        $viaje->paradas()->where(fn ($q) => $q->where('estado', 'por_entregar')->orWhere('modo', 'directa'))
            ->get()->each->delete();
        $siguiente = (int) $viaje->paradas()->max('orden');

        $anticiposVistos = [];
        $ventasVistas    = [];

        foreach (array_values($paradas) as $idx => $p) {
            $siguiente++;

            if (!empty($p['cliente_anticipo_id'])) {
                $anticipoId = (int) $p['cliente_anticipo_id'];
                if (isset($anticiposVistos[$anticipoId])) {
                    throw ValidationException::withMessages(["paradas.{$idx}" => 'Ese pedido ya está en el viaje: ajusta sus cantidades en la misma parada.']);
                }
                $anticiposVistos[$anticipoId] = true;
                $this->crearParadaPendiente($viaje, $anticipoId, (array) ($p['items'] ?? []), $siguiente, $idx, $user);
                continue;
            }

            if (!empty($p['venta_id'])) {
                $ventaId = (int) $p['venta_id'];
                if (isset($ventasVistas[$ventaId])) continue;
                $ventasVistas[$ventaId] = true;
                $this->crearParadaDirecta($viaje, $ventaId, $siguiente, $idx);
                continue;
            }

            throw ValidationException::withMessages(["paradas.{$idx}" => 'Una de las paradas no indica qué pedido o venta lleva.']);
        }
    }

    private function crearParadaPendiente(Viaje $viaje, int $anticipoId, array $lineas, int $orden, int $idx, User $user): ViajeParada
    {
        $anticipo = ClienteAnticipo::whereKey($anticipoId)->lockForUpdate()->with('venta:id,numero,estado,local_id')->first();

        if (!$anticipo || (int) $anticipo->empresa_id !== (int) $viaje->empresa_id
            || $anticipo->tipo_valorizacion !== 'material' || !$anticipo->venta_id) {
            throw ValidationException::withMessages(["paradas.{$idx}" => 'Ese pedido no existe o no es mercadería por entregar.']);
        }
        if ($anticipo->estado !== 'activo' || $anticipo->venta?->estado !== 'completada') {
            throw ValidationException::withMessages(["paradas.{$idx}" => "El pedido de la venta {$anticipo->venta?->numero} ya no tiene nada por entregar (entregado o anulado)."]);
        }
        if ($user->local_id && (int) $anticipo->venta->local_id !== (int) $user->local_id) {
            throw ValidationException::withMessages(["paradas.{$idx}" => 'Ese pedido es de otro local.']);
        }

        $items    = $anticipo->items()->with('producto:id,peso_kg')->lockForUpdate()->get()->keyBy('id');
        $asignado = $this->asignado($items->keys()->all(), $viaje->id);

        $parada = $viaje->paradas()->create([
            'venta_id'            => $anticipo->venta_id,
            'cliente_anticipo_id' => $anticipo->id,
            'orden'               => $orden,
            'modo'                => 'pendiente',
            'estado'              => 'por_entregar',
        ]);

        $cargado = 0;
        foreach ($lineas as $j => $l) {
            $item     = $items->get((int) ($l['cliente_anticipo_item_id'] ?? 0));
            $cantidad = round((float) ($l['cantidad'] ?? 0), 4);
            if (!$item) {
                throw ValidationException::withMessages(["paradas.{$idx}.items.{$j}" => 'Ese producto no es de este pedido.']);
            }
            if ($cantidad <= self::EPS) continue;

            $libre = round((float) $item->cantidad_pendiente - (float) ($asignado[$item->id] ?? 0), 4);
            if ($cantidad > $libre + self::EPS) {
                $enOtro = (float) ($asignado[$item->id] ?? 0);
                throw ValidationException::withMessages(["paradas.{$idx}.items.{$j}" =>
                    "De «{$item->producto_nombre}» ({$anticipo->venta->numero}) solo quedan " . self::num(max(0, $libre)) . ' por cargar'
                    . ($enOtro > self::EPS ? ' (' . self::num($enOtro) . ' ya van en otro viaje).' : '.')]);
            }

            $parada->items()->create([
                'cliente_anticipo_item_id' => $item->id,
                'producto_id'              => $item->producto_id,
                'producto_nombre'          => $item->producto_nombre,
                'unidad_nombre'            => $item->unidad_nombre,
                'cantidad'                 => $cantidad,
                'factor_conversion'        => $item->factor_conversion,
                'peso_kg'                  => self::peso($item->producto, $cantidad, (float) $item->factor_conversion),
            ]);
            $cargado++;
        }

        if ($cargado === 0) {
            throw ValidationException::withMessages(["paradas.{$idx}" => "Indica cuánto se carga del pedido de la venta {$anticipo->venta->numero}."]);
        }

        return $parada;
    }

    private function crearParadaDirecta(Viaje $viaje, int $ventaId, int $orden, int $idx): ViajeParada
    {
        $venta = Venta::with('items.producto:id,peso_kg')->find($ventaId);

        if (!$venta || (int) $venta->empresa_id !== (int) $viaje->empresa_id || $venta->estado !== 'completada') {
            throw ValidationException::withMessages(["paradas.{$idx}" => 'Esa venta no existe o está anulada.']);
        }
        $pedido = ClienteAnticipo::where('venta_id', $venta->id)->where('tipo_valorizacion', 'material')->where('estado', 'activo')->exists();
        if ($pedido) {
            throw ValidationException::withMessages(["paradas.{$idx}" => "La venta {$venta->numero} tiene mercadería por entregar: agrégala desde los pedidos pendientes."]);
        }
        $otro = ViajeParada::where('venta_id', $venta->id)->where('modo', 'directa')
            ->whereHas('viaje', fn ($q) => $q->where('estado', '<>', 'anulado')->where('id', '<>', $viaje->id))
            ->with('viaje:id,numero')->first();
        if ($otro) {
            throw ValidationException::withMessages(["paradas.{$idx}" => "La venta {$venta->numero} ya va en el viaje {$otro->viaje->numero}."]);
        }

        $parada = $viaje->paradas()->create([
            'venta_id'     => $venta->id,
            'orden'        => $orden,
            'modo'         => 'directa',
            // Ya salió del stock al venderse: el viaje solo la lleva.
            'estado'       => 'entregado',
            'entregado_at' => $venta->fecha_venta,
        ]);

        foreach ($venta->items as $vi) {
            $parada->items()->create([
                'venta_item_id'     => $vi->id,
                'producto_id'       => $vi->producto_id,
                'producto_nombre'   => $vi->producto_nombre,
                'unidad_nombre'     => $vi->unidad_nombre,
                'cantidad'          => $vi->cantidad,
                'factor_conversion' => $vi->factor_conversion,
                'peso_kg'           => self::peso($vi->producto, (float) $vi->cantidad, (float) $vi->factor_conversion),
            ]);
        }

        return $parada;
    }

    // ── Ciclo del viaje ──────────────────────────────────────────────────────

    public function iniciar(Viaje $viaje, User $user): Viaje
    {
        return DB::transaction(function () use ($viaje, $user) {
            $viaje = Viaje::whereKey($viaje->id)->lockForUpdate()->firstOrFail();
            if ($viaje->estado !== 'programado') {
                throw ValidationException::withMessages(['viaje' => "El viaje {$viaje->numero} ya está {$this->etiquetaEstado($viaje->estado)}."]);
            }
            $this->exigirSalida($viaje);

            $viaje->update(['estado' => 'en_ruta', 'salida_at' => now()]);
            AuditoriaService::log('viaje.salio', $viaje, ['numero' => $viaje->numero], $user);

            return $viaje;
        });
    }

    /**
     * Entrega una parada con lo que de verdad recibió el cliente. Si entrega
     * menos de lo cargado, el resto vuelve a quedar por entregar en Despachos.
     *
     * $data: fecha, observacion, items[]: {id (viaje_parada_items.id), cantidad}
     */
    public function entregarParada(ViajeParada $parada, array $data, User $user): ViajeParada
    {
        return DB::transaction(function () use ($parada, $data, $user) {
            $parada = ViajeParada::whereKey($parada->id)->lockForUpdate()->with('items')->firstOrFail();
            $viaje  = Viaje::whereKey($parada->viaje_id)->lockForUpdate()->with('vehiculo')->firstOrFail();

            if ($parada->modo !== 'pendiente' || $parada->estado !== 'por_entregar') {
                throw ValidationException::withMessages(['parada' => 'Esta parada ya no está por entregar.']);
            }
            if (!$viaje->abierto()) {
                throw ValidationException::withMessages(['parada' => "El viaje {$viaje->numero} ya está {$this->etiquetaEstado($viaje->estado)}."]);
            }
            // Entregar sin haber marcado la salida: el camión salió igual.
            if ($viaje->estado === 'programado') {
                $this->exigirSalida($viaje);
                $viaje->update(['estado' => 'en_ruta', 'salida_at' => now()]);
            }

            $cargado = $parada->items->keyBy('id');
            $lineas  = [];
            foreach ((array) ($data['items'] ?? []) as $j => $l) {
                $pi = $cargado->get((int) ($l['id'] ?? 0));
                if (!$pi) {
                    throw ValidationException::withMessages(["items.{$j}.id" => 'Ese producto no va en esta parada.']);
                }
                $cantidad = round((float) ($l['cantidad'] ?? 0), 4);
                if ($cantidad > (float) $pi->cantidad + self::EPS) {
                    throw ValidationException::withMessages(["items.{$j}.cantidad" => "De «{$pi->producto_nombre}» se cargaron " . self::num((float) $pi->cantidad) . ': no se puede entregar más.']);
                }
                if ($cantidad > self::EPS && $pi->cliente_anticipo_item_id) {
                    $lineas[] = ['id' => $pi->cliente_anticipo_item_id, 'cantidad' => $cantidad];
                }
            }

            $anticipo = ClienteAnticipo::find($parada->cliente_anticipo_id);
            if (!$anticipo) {
                throw ValidationException::withMessages(['parada' => 'El pedido de esta parada ya no existe.']);
            }

            $placa = $viaje->vehiculo?->placa;
            $this->entregas->aplicarEntregaMaterial($anticipo, [
                'fecha'       => $data['fecha'],
                'items'       => $lineas,
                'observacion' => trim("Viaje {$viaje->numero}" . ($placa ? " · {$placa}" : '') . '. ' . ($data['observacion'] ?? '')) ?: null,
            ], $user);

            $entregaId = $anticipo->aplicaciones()->latest('id')->value('id');
            $completa  = $parada->items->every(function ($pi) use ($data) {
                $l = collect((array) ($data['items'] ?? []))->firstWhere('id', $pi->id);

                return $l && round((float) $l['cantidad'], 4) >= (float) $pi->cantidad - self::EPS;
            });

            $parada->update([
                'estado'                         => 'entregado',
                'cliente_anticipo_aplicacion_id' => $entregaId,
                'entregado_at'                   => Carbon::parse(substr((string) $data['fecha'], 0, 10))->setTimeFrom(now()),
                'motivo'                         => $completa ? null : 'Entrega parcial: lo que no se entregó sigue pendiente en Despachos.',
            ]);
            $viaje->touch();

            return $parada;
        });
    }

    /** El cliente no recibió: lo cargado vuelve a quedar por entregar. */
    public function noEntregada(ViajeParada $parada, string $motivo, User $user): ViajeParada
    {
        return DB::transaction(function () use ($parada, $motivo, $user) {
            $parada = ViajeParada::whereKey($parada->id)->lockForUpdate()->firstOrFail();
            $viaje  = Viaje::whereKey($parada->viaje_id)->lockForUpdate()->firstOrFail();

            if ($parada->modo !== 'pendiente' || $parada->estado !== 'por_entregar' || !$viaje->abierto()) {
                throw ValidationException::withMessages(['parada' => 'Esta parada ya no está por entregar.']);
            }

            $parada->update(['estado' => 'no_entregado', 'motivo' => mb_substr(trim($motivo), 0, 255) ?: 'No se entregó']);
            $viaje->touch();
            AuditoriaService::log('viaje.parada_no_entregada', $viaje, [
                'numero' => $viaje->numero, 'venta_id' => $parada->venta_id, 'motivo' => $parada->motivo,
            ], $user);

            return $parada;
        });
    }

    /** Vuelve el camión: lo que quedó sin entregar se libera para otro viaje. */
    public function cerrar(Viaje $viaje, User $user): Viaje
    {
        return DB::transaction(function () use ($viaje, $user) {
            $viaje = Viaje::whereKey($viaje->id)->lockForUpdate()->firstOrFail();
            if ($viaje->estado !== 'en_ruta') {
                throw ValidationException::withMessages(['viaje' => $viaje->estado === 'programado'
                    ? "El viaje {$viaje->numero} todavía no sale. Si no va a salir, anúlalo."
                    : "El viaje {$viaje->numero} ya está {$this->etiquetaEstado($viaje->estado)}."]);
            }

            $devueltas = $viaje->paradas()->where('modo', 'pendiente')->where('estado', 'por_entregar')
                ->update(['estado' => 'no_entregado', 'motivo' => 'Volvió sin entregar', 'updated_at' => now()]);

            $viaje->update(['estado' => 'cerrado', 'cierre_at' => now()]);
            AuditoriaService::log('viaje.cerrado', $viaje, ['numero' => $viaje->numero, 'devueltas' => $devueltas], $user);

            return $viaje;
        });
    }

    /** Solo si no se entregó nada: lo entregado ya movió el stock. */
    public function anular(Viaje $viaje, string $motivo, User $user): Viaje
    {
        return DB::transaction(function () use ($viaje, $motivo, $user) {
            $viaje = Viaje::whereKey($viaje->id)->lockForUpdate()->firstOrFail();
            if (!$viaje->abierto()) {
                throw ValidationException::withMessages(['viaje' => "El viaje {$viaje->numero} ya está {$this->etiquetaEstado($viaje->estado)}."]);
            }
            if ($viaje->paradas()->where('modo', 'pendiente')->where('estado', 'entregado')->exists()) {
                throw ValidationException::withMessages(['viaje' => "El viaje {$viaje->numero} ya entregó mercadería: ciérralo en vez de anularlo."]);
            }

            $viaje->update(['estado' => 'anulado', 'motivo_anulacion' => mb_substr(trim($motivo), 0, 255), 'cierre_at' => now()]);
            AuditoriaService::log('viaje.anulado', $viaje, ['numero' => $viaje->numero, 'motivo' => $motivo], $user);

            return $viaje;
        });
    }

    // ── Desde el POS ─────────────────────────────────────────────────────────

    /**
     * Venta hecha eligiendo la placa (JH: "cliente, producto, cantidad, placa").
     * Va al viaje abierto de ese vehículo en el día; si no hay, se abre uno.
     * Si quedó mercadería por entregar, la parada la lleva pendiente; si no, la
     * venta ya salió del stock y la parada nace entregada.
     *
     * Llamar dentro de la transacción de la venta.
     */
    public function vincularVenta(Venta $venta, int $vehiculoId, User $user): ViajeParada
    {
        $vehiculo = Vehiculo::deEmpresa($venta->empresa_id)->activo()->find($vehiculoId)
            ?? throw ValidationException::withMessages(['vehiculo_id' => 'Ese vehículo no existe o está desactivado.']);

        $fecha = $venta->fecha_venta?->toDateString() ?? now()->toDateString();

        $viaje = Viaje::deEmpresa($venta->empresa_id)->abierto()
            ->where('vehiculo_id', $vehiculo->id)
            ->where('tipo', 'reparto')
            ->whereDate('fecha', $fecha)
            ->lockForUpdate()
            ->latest('id')
            ->first();

        if (!$viaje) {
            $viaje = Viaje::create([
                'empresa_id'   => $venta->empresa_id,
                'numero'       => Viaje::generarNumero((int) $venta->empresa_id),
                'tipo'         => 'reparto',
                'fecha'        => $fecha,
                'vehiculo_id'  => $vehiculo->id,
                'conductor_id' => $vehiculo->conductor_id,
                'estado'       => 'programado',
                'user_id'      => $user->id,
            ]);
            AuditoriaService::log('viaje.creado', $viaje, ['numero' => $viaje->numero, 'vehiculo' => $vehiculo->placa, 'origen' => 'pos'], $user);
        }

        $orden  = (int) $viaje->paradas()->max('orden') + 1;
        $pedido = ClienteAnticipo::where('venta_id', $venta->id)->where('tipo_valorizacion', 'material')
            ->where('estado', 'activo')->with('items')->first();

        if ($pedido) {
            $parada = $this->crearParadaPendiente($viaje, $pedido->id, $pedido->items->map(fn ($i) => [
                'cliente_anticipo_item_id' => $i->id,
                'cantidad'                 => (float) $i->cantidad_pendiente,
            ])->all(), $orden, 0, $user);
        } else {
            $parada = $this->crearParadaDirecta($viaje, $venta->id, $orden, 0);
        }

        $viaje->touch();

        return $parada;
    }

    // ── Interioridades ───────────────────────────────────────────────────────

    private function vehiculoOperativo(int $empresaId, int $vehiculoId, ?int $actual): Vehiculo
    {
        $vehiculo = Vehiculo::deEmpresa($empresaId)->find($vehiculoId);
        if (!$vehiculo || !$vehiculo->activo) {
            throw ValidationException::withMessages(['vehiculo_id' => 'Elige un vehículo activo.']);
        }
        // Un viaje que ya tenía este vehículo puede seguir con él aunque luego
        // lo hayan mandado al taller; uno nuevo no.
        if ($vehiculo->estado !== 'disponible' && $vehiculo->id !== $actual) {
            throw ValidationException::withMessages(['vehiculo_id' => "{$vehiculo->placa} está " . ($vehiculo->estado === 'taller' ? 'en el taller' : 'fuera de servicio') . '.']);
        }

        return $vehiculo;
    }

    private function chofer(int $empresaId, $conductorId): ?Conductor
    {
        if (!$conductorId) return null;
        $c = Conductor::deEmpresa($empresaId)->activo()->find((int) $conductorId);
        if (!$c || $c->tipo !== Conductor::CHOFER) {
            throw ValidationException::withMessages(['conductor_id' => 'Elige un chofer activo.']);
        }

        return $c;
    }

    /** @return int[] */
    private function ayudantes(int $empresaId, array $ids, ?int $choferId): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($id) => $id > 0 && $id !== $choferId)));
        if (empty($ids)) return [];

        $validos = Conductor::deEmpresa($empresaId)->activo()->whereIn('id', $ids)->pluck('id')->all();
        if (count($validos) !== count($ids)) {
            throw ValidationException::withMessages(['ayudantes' => 'Uno de los ayudantes no existe o está desactivado.']);
        }

        return $validos;
    }

    /** Para salir: chofer y un vehículo que no esté ya en otra ruta ni en el taller. */
    private function exigirSalida(Viaje $viaje): void
    {
        if (!$viaje->conductor_id) {
            throw ValidationException::withMessages(['conductor_id' => "Asigna el chofer del viaje {$viaje->numero} antes de que salga."]);
        }
        $vehiculo = Vehiculo::find($viaje->vehiculo_id);
        if ($vehiculo && $vehiculo->estado !== 'disponible') {
            throw ValidationException::withMessages(['vehiculo_id' => "{$vehiculo->placa} está " . ($vehiculo->estado === 'taller' ? 'en el taller' : 'fuera de servicio') . ': cambia el vehículo del viaje.']);
        }
        $otro = Viaje::where('vehiculo_id', $viaje->vehiculo_id)->where('estado', 'en_ruta')->where('id', '<>', $viaje->id)->value('numero');
        if ($otro) {
            throw ValidationException::withMessages(['vehiculo_id' => "{$vehiculo?->placa} sigue en ruta con el viaje {$otro}: ciérralo primero."]);
        }
    }

    private function etiquetaEstado(string $estado): string
    {
        return ['programado' => 'programado', 'en_ruta' => 'en ruta', 'cerrado' => 'cerrado', 'anulado' => 'anulado'][$estado] ?? $estado;
    }

    private static function peso(?Producto $producto, float $cantidad, float $factor): ?float
    {
        if ($producto?->peso_kg === null) return null;

        return round($cantidad * ($factor ?: 1) * (float) $producto->peso_kg, 3);
    }

    private static function texto($v): ?string
    {
        $v = is_string($v) ? trim($v) : null;

        return $v !== null && $v !== '' ? $v : null;
    }

    private static function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 4, '.', ','), '0'), '.');
    }

    public static function nombreCliente($c): string
    {
        if (!$c || ($c->es_cliente_general ?? false)) return 'Cliente general';

        return ($c->razon_social ?: trim(($c->nombres ?? '') . ' ' . ($c->apellidos ?? ''))) ?: '—';
    }
}
