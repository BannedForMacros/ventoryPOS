<?php
// Inspección temporal de esquema (se borra luego)
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$focus = ['ventas','venta_pagos','clientes','proveedores','cuentas','entradas','metodos_pago','gastos','stock','productos','turnos','modulos','permisos'];
foreach ($focus as $t) {
    echo "== $t ==", PHP_EOL;
    $cols = DB::select("select column_name, data_type, is_nullable, column_default from information_schema.columns where table_name = ? order by ordinal_position", [$t]);
    foreach ($cols as $c) {
        echo sprintf("  %-28s %-28s %s %s", $c->column_name, $c->data_type, $c->is_nullable==='YES'?'NULL':'NOT NULL', $c->column_default ? 'def='.substr($c->column_default,0,40) : ''), PHP_EOL;
    }
}
echo PHP_EOL, "== ultimas migraciones corridas ==", PHP_EOL;
foreach (DB::select("select migration, batch from migrations order by id desc limit 8") as $m) echo "  {$m->migration} (batch {$m->batch})", PHP_EOL;
