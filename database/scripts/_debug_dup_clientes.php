<?php

use Illuminate\Support\Facades\DB;

// Diagnóstico temporal: duplicados de clientes por documento y por nombre.
echo "== Mismo numero_documento repetido (global) ==\n";
$dupDoc = DB::table('clientes')
    ->selectRaw("numero_documento, count(*) as n, string_agg(id::text, ',' order by id) as ids,
                 string_agg(coalesce(nullif(telefono,''),'NULL'), ' | ' order by id) as telefonos,
                 string_agg(empresa_id::text, ',' order by id) as empresas")
    ->whereNotNull('numero_documento')->where('numero_documento', '!=', '')
    ->groupBy('numero_documento')
    ->havingRaw('count(*) > 1')
    ->orderByDesc('n')
    ->limit(20)
    ->get();
foreach ($dupDoc as $d) {
    echo "{$d->numero_documento}  x{$d->n}  ids: {$d->ids}  empresas: {$d->empresas}  tels: {$d->telefonos}\n";
}
if ($dupDoc->isEmpty()) echo "(ninguno)\n";

echo "\n== Mismo nombre normalizado, varios registros ==\n";
$dupNom = DB::table('clientes')
    ->selectRaw("upper(trim(coalesce(nombres,'') || ' ' || coalesce(apellidos,''))) as nom,
                 count(*) as n,
                 string_agg(id::text || '(doc:' || coalesce(nullif(numero_documento,''),'-') || ',tel:' || coalesce(nullif(telefono,''),'NULL') || ',emp:' || empresa_id || ')', E'\n    ' order by id) as det")
    ->groupBy('nom')
    ->havingRaw('count(*) > 1')
    ->orderByDesc('n')
    ->limit(15)
    ->get();
foreach ($dupNom as $d) {
    echo "{$d->nom}  x{$d->n}\n    {$d->det}\n";
}
if ($dupNom->isEmpty()) echo "(ninguno)\n";
