<?php

namespace App\Services;

use App\Models\ClienteAnticipo;
use App\Models\CuentaMovimiento;
use App\Models\DevolucionPago;
use App\Models\EntradaPago;
use App\Models\Gasto;
use App\Models\MetodoPago;
use App\Models\PlanillaColumna;
use App\Models\ProveedorAdelanto;
use App\Models\Turno;
use App\Models\Venta;
use App\Models\VentaAbono;
use Illuminate\Support\Collection;

/**
 * Planilla de caja de UN turno (función opcional: empresas.usa_planilla_caja).
 *
 * Una fila por comprobante y por cada movimiento de dinero del turno, con el
 * monto repartido en columnas:
 *   - las columnas que configuró la empresa (planilla_columnas); cada medio de
 *     pago suma en la suya. Un medio sin columna sale con su propio nombre.
 *   - "Anticipo": lo que se pagó consumiendo un anticipo del cliente.
 *   - "Créditos": lo que quedó al crédito en la venta.
 *
 * Secciones: inicio de caja, boletas, facturas, notas de venta, otros
 * ingresos, salidas o compras y pagos anteriores (abonos de créditos).
 *
 * El total de la columna de efectivo es el efectivo en caja; debe coincidir
 * con Turno::calcularMontoEsperado(). Si no coincide, se informa la
 * diferencia en vez de esconderla.
 */
class PlanillaCajaService
{
    private const ANTICIPO = 'anticipo';
    private const CREDITO  = 'credito';

    /** @var Collection<int, MetodoPago> */
    private Collection $metodos;
    private string $claveEfectivo;
    /** @var array<string, array{clave: string, nombre: string, es_efectivo: bool, automatica: bool}> */
    private array $columnas = [];

    public function deTurno(Turno $turno): array
    {
        $turno->loadMissing('local');
        $this->prepararColumnas($turno->empresa_id);

        $secciones = [
            $this->seccion('inicio', 'Inicio de caja', $this->inicio($turno)),
            ...$this->ventas($turno),
            $this->seccion('otros_ingresos', 'Otros ingresos', $this->otrosIngresos($turno)),
            $this->seccion('salidas', 'Salidas o compras', $this->salidas($turno)),
            $this->seccion('pagos_anteriores', 'Pagos anteriores (abonos de créditos)', $this->pagosAnteriores($turno)),
        ];

        // Totales por columna.
        $totales = array_fill_keys(array_keys($this->columnas), 0.0);
        foreach ($secciones as $s) {
            foreach ($s['filas'] as $f) {
                foreach ($f['montos'] as $clave => $monto) {
                    $totales[$clave] = round(($totales[$clave] ?? 0) + $monto, 2);
                }
            }
        }

        // Se muestran las columnas con algún monto; la de efectivo, siempre.
        $visibles = array_values(array_filter($this->columnas, fn ($c) =>
            $c['es_efectivo'] || abs($totales[$c['clave']] ?? 0) > 0.004));

        $efectivo = round($totales[$this->claveEfectivo] ?? 0, 2);
        $esperado = round($turno->calcularMontoEsperado(), 2);

        $ventas = collect($secciones)->whereIn('clave', ['boletas', 'facturas', 'notas'])->flatMap(fn ($s) => $s['filas']);

        return [
            'columnas'          => $visibles,
            'clave_efectivo'    => $this->claveEfectivo,
            'secciones'         => $secciones,
            'totales'           => $totales,
            'efectivo_en_caja'  => $efectivo,
            'efectivo_esperado' => $esperado,
            // Distinto de 0 solo si hay un movimiento de efectivo que la planilla
            // no supo clasificar: se muestra para no esconder la diferencia.
            'diferencia_sistema'=> round($esperado - $efectivo, 2),
            'ventas_count'      => $ventas->count(),
        ];
    }

    /* ── Columnas ─────────────────────────────────────────────────────── */

    private function prepararColumnas(int $empresaId): void
    {
        $this->metodos = MetodoPago::with('tipo:id,slug')->where('empresa_id', $empresaId)
            ->orderByDesc('activo')->orderBy('nombre')->get()->keyBy('id');
        $this->columnas = [];

        foreach (PlanillaColumna::deEmpresa($empresaId)->orderBy('orden')->orderBy('id')->get() as $c) {
            $this->columnas["c{$c->id}"] = ['clave' => "c{$c->id}", 'nombre' => $c->nombre, 'es_efectivo' => false, 'automatica' => false];
        }
        // Medios sin columna asignada: una columna con su propio nombre.
        foreach ($this->metodos as $m) {
            if (!$m->planilla_columna_id) {
                $this->columnas["m{$m->id}"] = ['clave' => "m{$m->id}", 'nombre' => $m->nombre, 'es_efectivo' => false, 'automatica' => true];
            }
        }

        // Columna del efectivo: la del primer medio de tipo efectivo.
        $efectivo = $this->metodos->first(fn ($m) => $m->tipo?->slug === 'efectivo');
        $this->claveEfectivo = $efectivo ? $this->claveDe($efectivo->id) : 'efectivo';
        if (!isset($this->columnas[$this->claveEfectivo])) {
            $this->columnas = [$this->claveEfectivo => ['clave' => $this->claveEfectivo, 'nombre' => 'Efectivo', 'es_efectivo' => true, 'automatica' => true]] + $this->columnas;
        }
        $this->columnas[$this->claveEfectivo]['es_efectivo'] = true;

        $this->columnas[self::ANTICIPO] = ['clave' => self::ANTICIPO, 'nombre' => 'Anticipo', 'es_efectivo' => false, 'automatica' => true];
        $this->columnas[self::CREDITO]  = ['clave' => self::CREDITO,  'nombre' => 'Créditos', 'es_efectivo' => false, 'automatica' => true];
    }

    private function claveDe(?int $metodoId): string
    {
        $m = $metodoId ? $this->metodos->get($metodoId) : null;
        if (!$m) return $this->claveEfectivo;
        return $m->planilla_columna_id ? "c{$m->planilla_columna_id}" : "m{$m->id}";
    }

    /* ── Secciones ────────────────────────────────────────────────────── */

    private function inicio(Turno $t): array
    {
        $filas = [$this->fila('inicio', "apertura", 'Apertura', null, [$this->claveEfectivo => (float) $t->monto_apertura])];
        if ($t->fondosEntranEnDeclaracion() && (float) $t->monto_caja_chica > 0) {
            $filas[] = $this->fila('inicio', 'caja_chica', 'Fondos de caja chica', null, [$this->claveEfectivo => (float) $t->monto_caja_chica]);
        }
        return $filas;
    }

    /** Boletas, facturas y notas de venta: una fila por comprobante, anuladas incluidas. */
    private function ventas(Turno $t): array
    {
        $ventas = Venta::where('turno_id', $t->id)
            ->with(['cliente', 'pagos'])
            ->withSum('aplicacionesAnticipo as anticipo_aplicado', 'monto')
            ->orderBy('fecha_venta')->orderBy('id')
            ->get();

        $grupo = fn (string $tipo) => match (true) {
            str_starts_with($tipo, 'boleta')  => 'boletas',
            str_starts_with($tipo, 'factura') => 'facturas',
            default                           => 'notas',
        };

        $filas = ['boletas' => [], 'facturas' => [], 'notas' => []];
        foreach ($ventas as $v) {
            $anulada = $v->estado === 'anulada';
            $montos = [];
            if (!$anulada) {
                $pagado = 0.0;
                foreach ($v->pagos as $p) {
                    $neto = (float) $p->monto - (float) $p->vuelto;
                    $pagado += $neto;
                    $this->sumar($montos, $this->claveDe($p->metodo_pago_id), $neto);
                }
                $anticipo = (float) ($v->anticipo_aplicado ?? 0);
                $this->sumar($montos, self::ANTICIPO, $anticipo);
                if ($v->es_credito) {
                    $this->sumar($montos, self::CREDITO, max(0, (float) $v->total - $pagado - $anticipo));
                }
            }
            $filas[$grupo($v->tipo_comprobante)][] = $this->fila('venta', "v{$v->id}",
                $v->numero_comprobante ? "{$v->numero} ({$v->numero_comprobante})" : $v->numero,
                $v->cliente?->nombre_completo ?? 'Cliente general', $montos) + [
                    'venta_id' => $v->id,
                    'anulada'  => $anulada,
                    'total'    => (float) $v->total,
                ];
        }

        return [
            $this->seccion('boletas', 'Boletas electrónicas', $filas['boletas']),
            $this->seccion('facturas', 'Facturas electrónicas', $filas['facturas']),
            $this->seccion('notas', 'Notas de venta', $filas['notas']),
        ];
    }

    /** Anticipos cobrados y préstamos recibidos en el turno. */
    private function otrosIngresos(Turno $t): array
    {
        $filas = [];
        $anticipos = ClienteAnticipo::where('turno_id', $t->id)->where('estado', '<>', 'anulado')
            ->where(fn ($q) => $q->whereNotNull('metodo_pago_id')->orWhereHas('cuenta', fn ($c) => $c->where('es_efectivo', true)))
            ->with('cliente')->orderBy('id')->get();
        foreach ($anticipos as $a) {
            $filas[] = $this->fila('ingreso', "ant{$a->id}", 'Anticipo', $a->cliente?->nombre_completo ?? '—',
                [$this->claveDe($a->metodo_pago_id) => (float) $a->monto]);
        }
        foreach ($this->movimientosDeuda($t, 'ingreso') as $m) {
            $filas[] = $this->fila('ingreso', "mov{$m->id}", 'Préstamo', $m->descripcion, [$this->claveEfectivo => (float) $m->monto]);
        }
        return $filas;
    }

    /** Todo lo que salió del cajón: gastos, retiros, compras, adelantos, devoluciones. */
    private function salidas(Turno $t): array
    {
        $filas = [];
        $ef = $this->claveEfectivo;

        $gastos = Gasto::where('turno_id', $t->id)
            ->where(fn ($q) => $q->whereNull('cuenta_id')->orWhereHas('cuenta', fn ($c) => $c->where('es_efectivo', true)))
            ->with(['concepto', 'tipo'])->orderBy('id')->get();
        foreach ($gastos as $g) {
            $filas[] = $this->fila('salida', "g{$g->id}", $g->concepto?->nombre ?? $g->tipo?->nombre ?? 'Gasto',
                $g->comentario ?: $g->tipo?->nombre, [$ef => -(float) $g->monto]);
        }

        foreach ($t->retiros()->where('momento', 'turno')->with('user:id,name')->orderBy('id')->get() as $r) {
            $filas[] = $this->fila('salida', "r{$r->id}", 'Retiro', $r->concepto, [$ef => -(float) $r->monto]);
        }

        $compras = EntradaPago::where('turno_id', $t->id)
            ->where(fn ($q) => $q->whereHas('metodoPago.tipo', fn ($x) => $x->where('slug', 'efectivo'))
                ->orWhere(fn ($q2) => $q2->whereNull('metodo_pago_id')->whereHas('cuenta', fn ($c) => $c->where('es_efectivo', true))))
            ->with('entrada')->orderBy('id')->get();
        foreach ($compras as $c) {
            $filas[] = $this->fila('salida', "ep{$c->id}", 'Compra', $c->entrada?->proveedor ?: ($c->entrada?->numero_documento ?? '—'), [$ef => -(float) $c->monto]);
        }

        $adelantos = ProveedorAdelanto::where('turno_id', $t->id)->whereNotIn('estado', ['anulado', 'devuelto'])
            ->where(fn ($q) => $q->whereHas('metodoPago.tipo', fn ($x) => $x->where('slug', 'efectivo'))
                ->orWhere(fn ($q2) => $q2->whereNull('metodo_pago_id')->whereHas('cuenta', fn ($c) => $c->where('es_efectivo', true))))
            ->with('proveedor')->orderBy('id')->get();
        foreach ($adelantos as $a) {
            $filas[] = $this->fila('salida', "pa{$a->id}", 'Adelanto a proveedor', $a->proveedor?->nombre_mostrado ?? '—', [$ef => -(float) $a->monto]);
        }

        // Reembolsos de devoluciones: salen del medio con que se devolvió.
        $reembolsos = DevolucionPago::whereHas('devolucion', fn ($q) => $q->where('turno_id', $t->id)->whereIn('estado', ['aprobada', 'completada']))
            ->with('devolucion.venta.cliente')->orderBy('id')->get();
        foreach ($reembolsos as $p) {
            $d = $p->devolucion;
            $filas[] = $this->fila('salida', "dp{$p->id}", 'Devolución ' . ($d?->venta?->numero ?? ''),
                $d?->venta?->cliente?->nombre_completo ?? 'Cliente general', [$this->claveDe($p->metodo_pago_id) => -(float) $p->monto]);
        }

        foreach ($this->movimientosDeuda($t, 'egreso') as $m) {
            $filas[] = $this->fila('salida', "mov{$m->id}", 'Pago de deuda', $m->descripcion, [$ef => -(float) $m->monto]);
        }

        // Devoluciones de anticipos y cancelaciones de pendientes pagadas en efectivo.
        $otros = CuentaMovimiento::where('empresa_id', $t->empresa_id)->where('tipo', 'egreso')
            ->whereHas('cuenta', fn ($c) => $c->where('es_efectivo', true))
            ->where(fn ($q) => $q
                ->where(fn ($s) => $s->where('ref_tipo', 'cliente_anticipo_devolucion')
                    ->whereIn('ref_id', ClienteAnticipo::where('turno_devolucion_id', $t->id)->select('id')))
                // Cancelaciones: el turno guardado en la cancelación ("Afecta caja"),
                // igual que el esperado del turno.
                ->orWhere(fn ($s) => $s->where('ref_tipo', 'anticipo_cancelacion')
                    ->whereIn('ref_id', $t->cancelacionesAnticipo()->select('id'))))
            ->orderBy('id')->get();
        foreach ($otros as $m) {
            $filas[] = $this->fila('salida', "mov{$m->id}", 'Devolución de anticipo', $m->descripcion, [$ef => -(float) $m->monto]);
        }

        return $filas;
    }

    /** Abonos a créditos cobrados en el turno (con dinero; no compensaciones). */
    private function pagosAnteriores(Turno $t): array
    {
        $abonos = VentaAbono::where('turno_id', $t->id)->whereNotNull('metodo_pago_id')
            ->with('venta.cliente')->orderBy('id')->get();
        return $abonos->map(fn ($a) => $this->fila('abono', "ab{$a->id}", $a->venta?->numero ?? 'Abono',
            $a->venta?->cliente?->nombre_completo ?? 'Cliente general', [$this->claveDe($a->metodo_pago_id) => (float) $a->monto]))->all();
    }

    /* ── Utilidades ───────────────────────────────────────────────────── */

    /** Movimientos en efectivo de deudas / préstamos imputados a este turno. */
    private function movimientosDeuda(Turno $t, string $tipo): Collection
    {
        return CuentaMovimiento::where('empresa_id', $t->empresa_id)->where('tipo', $tipo)
            ->whereHas('cuenta', fn ($c) => $c->where('es_efectivo', true))
            ->where(fn ($q) => $q
                ->where(fn ($s) => $s->where('ref_tipo', 'deuda')
                    ->whereIn('ref_id', \App\Models\Deuda::where('turno_id', $t->id)->select('id')))
                ->orWhere(fn ($s) => $s->where('ref_tipo', 'deuda_pago')
                    ->whereIn('ref_id', \App\Models\DeudaPago::where('turno_id', $t->id)->select('id'))))
            ->orderBy('id')->get();
    }

    private function sumar(array &$montos, string $clave, float $monto): void
    {
        if (abs($monto) < 0.005) return;
        $montos[$clave] = round(($montos[$clave] ?? 0) + $monto, 2);
    }

    private function fila(string $tipo, string $id, string $vale, ?string $cliente, array $montos): array
    {
        return [
            'id'      => $id,
            'tipo'    => $tipo,
            'vale'    => $vale,
            'cliente' => $cliente,
            'montos'  => array_map(fn ($m) => round($m, 2), array_filter($montos, fn ($m) => abs($m) >= 0.005)),
        ];
    }

    private function seccion(string $clave, string $titulo, array $filas): array
    {
        return ['clave' => $clave, 'titulo' => $titulo, 'filas' => $filas];
    }
}
