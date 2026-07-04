<?php
// Smoke test temporal del balance diario (se borra luego)
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Services\BalanceDiarioService;

$user = User::whereHas('rol', fn($q) => $q->where('es_admin', true))->first();
if (!$user) { echo "No hay usuario admin\n"; exit(1); }
echo "Usuario: {$user->name} (empresa {$user->empresa_id})\n";

$service = app(BalanceDiarioService::class);
$balance = $service->generar($user, now()->toDateString());

echo "Balance {$balance->fecha->toDateString()} [{$balance->estado}]\n";
echo str_repeat('-', 60), "\n";
foreach ($balance->items as $i) {
    printf("  %-6s %-22s %-38s %12.2f %s\n",
        strtoupper($i->seccion), $i->categoria, mb_substr($i->descripcion, 0, 38),
        (float) $i->monto, $i->es_manual ? '[manual]' : '[auto]');
}
echo str_repeat('-', 60), "\n";
printf("A FAVOR: %.2f | EN CONTRA: %.2f | NETO: %.2f\n",
    (float) $balance->total_favor, (float) $balance->total_contra, (float) $balance->balance_neto);
printf("Anterior: %s | Diferencia: %s | Gastos día: %.2f | Utilidad real: %s\n",
    $balance->balance_anterior ?? 'null',
    $balance->diferencia ?? 'null',
    (float) $balance->gastos_dia,
    $balance->utilidad_real ?? 'null');

// Regeneración idempotente (no debe duplicar líneas)
$balance2 = $service->generar($user, now()->toDateString());
echo "Regenerado: items=", $balance2->items->count(), " (antes ", $balance->items->count(), ")\n";
