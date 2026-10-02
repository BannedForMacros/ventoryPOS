<?php

/**
 * HYC (empresa 1097) — turno #858, Caja 1 (01/10/2026):
 *
 * La cajera cerró entregando S/ 2,770.20 a administración y dejando 1,500 en
 * caja (estaba al revés). El administrador reabrió y volvió a cerrar con lo
 * correcto: entrega S/ 1,500, quedan S/ 2,770.20. La reapertura no borraba la
 * entrega del primer cierre, así que el turno quedó con las dos (4,270.20).
 *
 * Se ANULA (no se borra) SOLO la entrega del primer cierre (retiro #63): la
 * fila queda como constancia con estado 'anulado'. No toca tesorería ni
 * balance: la entrega a administración es un traslado de custodia y la Caja
 * Grande se calcula con lo que quedó en el cajón (efectivo_arrastre = 2,770.20).
 *
 *   php produccion/corregir_entrega_duplicada_turno_858_hyc.php            (simulación)
 *   php produccion/corregir_entrega_duplicada_turno_858_hyc.php --aplicar  (escribe)
 */

use App\Models\Turno;
use App\Models\TurnoRetiro;
use App\Models\User;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$aplicar = in_array('--aplicar', $argv, true);
echo $aplicar ? "MODO APLICAR\n" : "MODO SIMULACIÓN (usa --aplicar para escribir)\n";

$turno   = Turno::where('empresa_id', 1097)->findOrFail(858);
$retiros = TurnoRetiro::where('turno_id', $turno->id)->where('momento', 'cierre')->orderBy('id')->get();
foreach ($retiros as $r) echo "  retiro #{$r->id} S/ {$r->monto} ({$r->created_at})\n";
echo "  quedó en caja: S/ {$turno->efectivo_arrastre}\n";

$malo = $retiros->firstWhere('id', 63);
if (!$malo) { echo "El retiro #63 ya no existe: nada que hacer.\n"; exit(0); }
if ($retiros->count() !== 2 || (float) $malo->monto !== 2770.20 || (float) $turno->efectivo_arrastre !== 2770.20) {
    echo "Los datos no son los esperados: no se toca nada.\n"; exit(1);
}

$admin = User::where('empresa_id', 1097)->whereHas('rol', fn ($q) => $q->where('es_admin', true))->orderBy('id')->firstOrFail();

DB::beginTransaction();
$malo->update([
    'estado'      => TurnoRetiro::ESTADO_ANULADO,
    'observacion' => trim(($malo->observacion ?? '') . ' [Anulada el ' . now()->format('d/m/Y H:i')
        . ': entrega del primer cierre, rehecho al reabrir el turno]'),
]);
App\Services\AuditoriaService::log('turno.entrega_cierre_corregida', $turno, [
    'motivo'          => 'Reapertura no borró la entrega del primer cierre (quedó duplicada)',
    'retiro_anulado'  => ['id' => 63, 'monto' => 2770.20, 'creado' => (string) $malo->created_at],
    'entrega_vigente' => (float) $retiros->firstWhere('id', '!=', 63)->monto,
], $admin);
$total = (float) TurnoRetiro::where('turno_id', $turno->id)->where('momento', 'cierre')->sum('monto');
echo "Entregado a administración al cierre ahora: S/ " . number_format($total, 2) . "\n";

if ($aplicar) { DB::commit(); echo "APLICADO.\n"; }
else          { DB::rollBack(); echo "SIMULACIÓN: nada se escribió.\n"; }
