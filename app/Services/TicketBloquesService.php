<?php

namespace App\Services;

use App\Support\PlantillaTicket;

/**
 * Arma el ticket por bloques (plantilla "detallada") a partir del payload de
 * siempre más unos datos extra. No consulta la base de datos: recibe arrays y
 * devuelve arrays, por eso sirve igual para imprimir y para la vista previa.
 *
 * CONTRATO de cada bloque: Models/Bloque.cs del agente (VentoryPrint 1.3.0+).
 * Aquí nunca se mandan anchos: el agente reparte según el papel de cada caja.
 *
 * @phpstan-type Extras array{
 *   doc: 'venta'|'cotizacion'|'despacho', cpe?: bool, anulada?: bool,
 *   cajero_telefono?: ?string, observacion?: ?string, pie?: ?string,
 *   pagos?: list<array{nombre:string, monto:float}>,
 *   a_cuenta?: float, saldo?: float, vencimiento?: ?string, sin_estado_pago?: bool,
 *   entrega?: ?array{tipo:'recojo'|'envio', ruta?:?string, zona?:?string, programada?:?string},
 *   pendientes?: ?array{titulo:string, columnas:list<string>, filas:list<list<string>>},
 * }
 */
class TicketBloquesService
{
    /**
     * @param  array  $p   Payload de siempre (negocio, documento, cliente, items, totales, pago, pie, qr, logo).
     * @param  array  $x   Datos extra (ver Extras).
     * @param  array  $pl  Plantilla resuelta (PlantillaTicket::resolver).
     * @return list<array<string, mixed>>
     */
    public function armar(array $p, array $x, array $pl): array
    {
        $doc = $x['doc'] ?? 'venta';
        $bloques = [];
        $anterior = null;

        foreach (PlantillaTicket::seccionesDe($pl, $doc) as $clave) {
            $seccion = match ($clave) {
                'logo'        => $this->logo($p),
                'negocio'     => $this->negocio($p, $x),
                'documento'   => $this->documento($p, $x, $pl),
                'cliente'     => $this->cliente($p, $x, $pl),
                'entrega'     => $this->entrega($x, $pl),
                'items'       => $this->items($p, $x, $pl),
                'pendientes'  => $this->pendientes($x),
                'totales'     => $this->totales($p, $x),
                'pagos'       => $this->pagos($p, $x, $pl),
                'estado_pago' => $this->estadoPago($p, $x, $pl),
                'pie'         => $this->pie($p, $x),
                default       => [],
            };
            if (!$seccion) {
                continue;
            }

            if ($separador = $this->separador($anterior, $clave)) {
                $bloques[] = $separador;
            }
            array_push($bloques, ...$seccion);
            $anterior = $clave;
        }

        return $bloques;
    }

    /** Qué va entre dos secciones: una raya, un espacio o nada. */
    private function separador(?string $anterior, string $clave): ?array
    {
        if ($anterior === null || $anterior === 'logo') {
            return null;
        }
        // La banda y el pie respiran con un espacio; la tabla trae sus propias rayas.
        if (in_array($clave, ['estado_pago', 'pie'], true)) {
            return ['tipo' => 'espacio', 'n' => 1];
        }
        if ($anterior === 'estado_pago') {
            return ['tipo' => 'espacio', 'n' => 1];
        }

        return ['tipo' => 'linea'];
    }

    // ── Secciones ────────────────────────────────────────────────────────────

    private function logo(array $p): array
    {
        return empty($p['logo']) ? [] : [['tipo' => 'logo']];
    }

    private function negocio(array $p, array $x): array
    {
        $n = $p['negocio'] ?? [];
        $bloques = [];

        if ($nombre = trim((string) ($n['nombre'] ?? ''))) {
            $bloques[] = ['tipo' => 'texto', 'texto' => mb_strtoupper($nombre), 'alinear' => 'centro', 'tamano' => 'ancho', 'negrita' => true];
        }

        // En un comprobante SUNAT el RUC del emisor es obligatorio.
        $conRuc = ($n['mostrarRuc'] ?? true) || !empty($x['cpe']);
        $lineas = array_filter([
            $conRuc && !empty($n['ruc']) ? 'RUC: ' . $n['ruc'] : null,
            $n['direccion'] ?? null,
            !empty($n['telefono']) ? 'Telf: ' . $n['telefono'] : null,
        ]);
        if ($lineas) {
            $bloques[] = ['tipo' => 'texto', 'texto' => implode("\n", $lineas), 'alinear' => 'centro'];
        }

        return $bloques;
    }

    private function documento(array $p, array $x, array $pl): array
    {
        $d = $p['documento'] ?? [];
        $titulo = trim(mb_strtoupper((string) ($d['tipo'] ?? 'NOTA DE VENTA')) . "\n" . ($d['numero'] ?? ''));

        return [
            ['tipo' => 'texto', 'texto' => $titulo, 'alinear' => 'centro', 'negrita' => true],
            ['tipo' => 'pares', 'items' => $this->pares([
                [!empty($x['cpe']) ? 'Fecha de emisión:' : 'Fecha:', $d['fecha'] ?? null],
                ['Cajero:', $d['vendedor'] ?? null],
                ['Cel. cajero:', ($pl['opciones']['celular_cajero'] && !empty($d['vendedor'])) ? ($x['cajero_telefono'] ?? null) : null],
                ['Caja:', $d['caja'] ?? null],
            ])],
        ];
    }

    private function cliente(array $p, array $x, array $pl): array
    {
        $c = $p['cliente'] ?? [];
        $telefono    = trim((string) ($c['telefono'] ?? ''));
        $direccion   = trim((string) ($c['direccion'] ?? ''));
        $observacion = trim((string) ($x['observacion'] ?? ''));

        // "RUC 20605105514" → etiqueta "RUC:" y el número aparte, como en una factura.
        [$etqDoc, $valDoc] = $this->documentoCliente((string) ($c['doc'] ?? ''));

        $bloques = [];
        if ($pl['textos']['titulo_cliente'] !== '') {
            $bloques[] = ['tipo' => 'texto', 'texto' => $pl['textos']['titulo_cliente'], 'negrita' => true];
        }

        // En un envío la zona va con los datos de entrega, a la vista del repartidor.
        $zona = ($x['entrega']['tipo'] ?? null) === 'envio' ? trim((string) ($x['entrega']['zona'] ?? '')) : '';

        $enRecuadro = $pl['opciones']['cliente_recuadro'] && ($telefono !== '' || $direccion !== '');

        $bloques[] = ['tipo' => 'pares', 'items' => $this->pares([
            ['Cliente:', $c['nombre'] ?? 'Cliente Varios'],
            [$etqDoc, $valDoc],
            ['Zona:', $enRecuadro ? null : $zona],
            ['Celular:', $enRecuadro ? null : $telefono],
            ['Dirección:', $enRecuadro ? null : $direccion],
        ])];

        if ($enRecuadro) {
            $lineas = [];
            foreach ([['ZONA', $zona], ['TELÉFONO', $telefono], ['DIRECCIÓN', $direccion]] as [$titulo, $valor]) {
                if ($valor === '') {
                    continue;
                }
                if ($lineas) {
                    $lineas[] = ['separador' => true];
                }
                $lineas[] = ['texto' => $titulo];
                $lineas[] = ['texto' => $valor, 'tamano' => 'alto', 'negrita' => true];
            }
            $bloques[] = $this->caja('recuadro', $lineas, $pl);
        }

        if ($observacion !== '') {
            $bloques[] = ['tipo' => 'pares', 'items' => $this->pares([['Obs.:', $observacion]])];
        }

        return $bloques;
    }

    /** Recojo o envío en grande, la ruta y cuándo está programada la entrega. */
    private function entrega(array $x, array $pl): array
    {
        $e = $x['entrega'] ?? null;
        if (!$e) {
            return [];
        }

        $envio = ($e['tipo'] ?? '') === 'envio';
        $bloques = [['tipo' => 'recuadro', 'alinear' => 'centro'] + $this->caja('recuadro', [
            ['texto' => $pl['textos'][$envio ? 'envio' : 'recojo'], 'tamano' => 'grande', 'negrita' => true],
        ], $pl)];

        if ($envio && trim((string) ($e['ruta'] ?? '')) !== '') {
            $bloques[] = $this->caja('banda', array_values(array_filter([
                ['texto' => mb_strtoupper(trim($e['ruta'])), 'tamano' => 'grande', 'negrita' => true],
                trim((string) ($e['zona'] ?? '')) !== '' ? ['texto' => mb_strtoupper(trim($e['zona'])), 'negrita' => true] : null,
            ])), $pl);
        }

        if (!empty($e['programada'])) {
            $bloques[] = ['tipo' => 'pares', 'items' => [
                ['etiqueta' => 'Entrega programada:', 'valor' => $e['programada'], 'negrita' => true],
            ]];
        }

        return $bloques;
    }

    /** Por producto: lo vendido, lo ya entregado y lo que falta entregar. */
    private function pendientes(array $x): array
    {
        $t = $x['pendientes'] ?? null;
        if (!$t || empty($t['filas'])) {
            return [];
        }

        return [
            ['tipo' => 'texto', 'texto' => $t['titulo'], 'negrita' => true],
            [
                'tipo'     => 'tabla',
                'columnas' => array_merge(
                    [['titulo' => 'Producto', 'flexible' => true]],
                    array_map(fn ($titulo) => ['titulo' => $titulo], $t['columnas']),
                ),
                'filas'    => $t['filas'],
            ],
        ];
    }

    private function items(array $p, array $x, array $pl): array
    {
        $miles = !empty($x['cpe']);
        $filas = array_map(fn ($it) => [
            trim(($it['desc'] ?? '') . (!empty($it['unidad']) ? ' x ' . $it['unidad'] : '')),
            $this->cantidad((float) ($it['cant'] ?? 0)),
            $this->numero((float) ($it['precio'] ?? 0), $miles),
            $this->numero((float) ($it['importe'] ?? 0), $miles),
        ], $p['items'] ?? []);

        return [[
            'tipo'     => 'tabla',
            'estilo'   => $pl['opciones']['items_dos_lineas'] ? 'dosLineas' : 'auto',
            'columnas' => [
                ['titulo' => $pl['opciones']['items_dos_lineas'] ? '' : 'Producto', 'flexible' => true],
                ['titulo' => 'Cant.'],
                ['titulo' => 'P.U.'],
                ['titulo' => 'Importe'],
            ],
            'filas'    => array_values($filas),
        ]];
    }

    private function totales(array $p, array $x): array
    {
        $t   = $p['totales'] ?? [];
        $sym = $this->simbolo($t['moneda'] ?? 'PEN');
        $m   = fn ($v) => $sym . ' ' . $this->numero((float) $v, true);
        $pos = fn ($k) => (float) ($t[$k] ?? 0) > 0;

        $lineas = [];
        if ($pos('descuento')) {
            $lineas[] = ['Descuento:', '-' . $m($t['descuento'])];
        }

        if (!empty($x['cpe'])) {
            $igv = (float) ($t['igv'] ?? 0);
            $gravada = $t['gravada'] ?? ($igv > 0 ? (float) ($t['total'] ?? 0) - $igv : 0);
            if ($gravada > 0)       $lineas[] = ['Op. Gravada:', $m($gravada)];
            if ($pos('exonerada'))  $lineas[] = ['Op. Exonerada:', $m($t['exonerada'])];
            if ($pos('inafecta'))   $lineas[] = ['Op. Inafecta:', $m($t['inafecta'])];
            if ($igv > 0)           $lineas[] = ['IGV (' . rtrim(rtrim(number_format((float) ($t['igvTasa'] ?? 18), 2, '.', ''), '0'), '.') . '%):', $m($igv)];
        } elseif (($p['negocio']['mostrarIgv'] ?? false) && $this->llevaIgv($p['documento']['tipo'] ?? '')) {
            // Igual que el ticket estándar: el desglose solo en boletas y facturas.
            if ($pos('subtotal') && (float) $t['subtotal'] != (float) ($t['total'] ?? 0)) {
                $lineas[] = ['Subtotal:', $m($t['subtotal'])];
            }
            if ($pos('igv')) {
                $lineas[] = ['IGV:', $m($t['igv'])];
            }
        }

        $items = $this->pares($lineas);
        $items[] = ['etiqueta' => 'TOTAL:', 'valor' => $m($t['total'] ?? 0), 'negrita' => true, 'tamano' => 'alto'];

        $bloques = [['tipo' => 'pares', 'estilo' => 'extremos', 'items' => $items]];
        if (!empty($t['enLetras'])) {
            $bloques[] = ['tipo' => 'texto', 'texto' => $t['enLetras']];
        }

        return $bloques;
    }

    private function pagos(array $p, array $x, array $pl): array
    {
        $sym = $this->simbolo($p['totales']['moneda'] ?? 'PEN');
        $m   = fn ($v) => $sym . ' ' . $this->numero((float) $v, true);

        $lineas = array_map(fn ($pago) => [$pago['nombre'] . ':', $m($pago['monto'])], $x['pagos'] ?? []);

        if (($p['pago']['vuelto'] ?? 0) > 0) {
            $lineas[] = ['Recibido:', $m($p['pago']['recibido'] ?? 0)];
            $lineas[] = ['Vuelto:', $m($p['pago']['vuelto'])];
        }

        $saldo = (float) ($x['saldo'] ?? 0);
        if ($saldo > 0.009) {
            // Sin pago inicial no hay nada "a cuenta" que mostrar.
            if ((float) ($x['a_cuenta'] ?? 0) > 0.009) {
                $lineas[] = ['A cuenta:', $m($x['a_cuenta'])];
            }
            $lineas[] = ['Saldo:', $m($saldo)];
            if (!empty($x['vencimiento'])) {
                $lineas[] = ['Vence:', $x['vencimiento']];
            }
        }

        if (!$lineas) {
            return [];
        }

        $bloques = [];
        if ($pl['textos']['titulo_pagos'] !== '') {
            $bloques[] = ['tipo' => 'texto', 'texto' => $pl['textos']['titulo_pagos'], 'negrita' => true];
        }
        $bloques[] = ['tipo' => 'pares', 'estilo' => 'extremos', 'items' => $this->pares($lineas)];

        return $bloques;
    }

    private function estadoPago(array $p, array $x, array $pl): array
    {
        if (!empty($x['sin_estado_pago'])) {
            return [];
        }
        if (!empty($x['anulada'])) {
            return [$this->caja('banda', [['texto' => 'ANULADA', 'tamano' => 'grande', 'negrita' => true]], $pl)];
        }

        $saldo = (float) ($x['saldo'] ?? 0);
        if ($saldo <= 0.009) {
            return $pl['opciones']['banda_pagado']
                ? [$this->caja('banda', [['texto' => $pl['textos']['pagado'], 'tamano' => 'grande', 'negrita' => true]], $pl)]
                : [];
        }

        $sym = $this->simbolo($p['totales']['moneda'] ?? 'PEN');
        // En un envío, el saldo lo cobra quien entrega.
        $detalle = ($x['entrega']['tipo'] ?? null) === 'envio'
            ? $pl['textos']['cobrar_entrega']
            : trim((string) $pl['textos']['por_cancelar_detalle']);

        return [$this->caja('banda', array_values(array_filter([
            ['texto' => $pl['textos']['por_cancelar'], 'tamano' => 'alto', 'negrita' => true],
            $detalle !== '' ? ['texto' => $detalle, 'negrita' => true] : null,
            ['texto' => $sym . ' ' . $this->numero($saldo, true), 'tamano' => 'grande', 'negrita' => true],
        ])), $pl)];
    }

    private function pie(array $p, array $x): array
    {
        $bloques = [];
        if (!empty($p['qr'])) {
            $bloques[] = ['tipo' => 'qr', 'datos' => $p['qr']];
        }

        $pie = trim((string) ($x['pie'] ?? $p['pie'] ?? ''));
        if ($pie !== '') {
            $bloques[] = ['tipo' => 'texto', 'texto' => $pie, 'alinear' => 'centro'];
        }

        return $bloques;
    }

    // ── Auxiliares ───────────────────────────────────────────────────────────

    /** [[etiqueta, valor], …] → items del bloque "pares", sin los vacíos. */
    private function pares(array $lineas): array
    {
        $items = [];
        foreach ($lineas as [$etiqueta, $valor]) {
            $valor = trim((string) ($valor ?? ''));
            if ($valor !== '') {
                $items[] = ['etiqueta' => $etiqueta, 'valor' => $valor];
            }
        }

        return $items;
    }

    private function caja(string $tipo, array $lineas, array $pl): array
    {
        return array_filter([
            'tipo'    => $tipo,
            'modo'    => $pl['opciones']['recuadros_texto'] ? 'texto' : null,
            'lineas'  => $lineas,
        ], fn ($v) => $v !== null);
    }

    private function documentoCliente(string $doc): array
    {
        $doc = trim($doc);
        if ($doc === '') {
            return ['Doc:', null];
        }
        if (preg_match('/^([A-Za-z]{2,4})\s+(.+)$/', $doc, $m)) {
            return [mb_strtoupper($m[1]) . ':', trim($m[2])];
        }

        return ['Doc:', $doc];
    }

    private function llevaIgv(string $tipo): bool
    {
        $t = mb_strtoupper($tipo);

        return str_contains($t, 'BOLETA') || str_contains($t, 'FACTURA');
    }

    private function simbolo(?string $moneda): string
    {
        return strtoupper((string) $moneda) === 'USD' ? '$' : 'S/';
    }

    private function numero(float $v, bool $miles): string
    {
        return number_format($v, 2, '.', $miles ? ',' : '');
    }

    /** 1 → "1", 0.5 → "0.5", 2.25 → "2.25". */
    private function cantidad(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }
}
