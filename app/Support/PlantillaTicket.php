<?php

namespace App\Support;

/**
 * Plantilla del ticket impreso de una empresa (empresas.ticket_plantilla).
 *
 * Una plantilla dice QUÉ secciones salen, en QUÉ orden y con QUÉ textos. El
 * dibujo lo hace el agente de impresión con bloques genéricos (texto, pares,
 * tabla, recuadro, banda…), así que una plantilla nueva o una sección nueva se
 * agregan aquí, sin publicar una versión del agente.
 *
 *  - "estandar": el ticket de siempre. No se mandan bloques; sale byte a byte
 *    igual que antes de existir las plantillas. Es el valor por defecto.
 *  - "detallada": ticket por bloques, con datos del cliente destacados, pago
 *    por cada medio y estado de pago en grande.
 *
 * Los interruptores simples que ya existían (RUC, cajero, caja, IGV, pie,
 * líneas extra, logo) siguen en empresas.ticket_config y valen para las dos.
 */
class PlantillaTicket
{
    public const ESTANDAR  = 'estandar';
    public const DETALLADA = 'detallada';

    public const PLANTILLAS = [
        self::ESTANDAR => [
            'nombre'  => 'Estándar',
            'detalle' => 'El ticket de siempre. No cambia nada.',
        ],
        self::DETALLADA => [
            'nombre'  => 'Detallada',
            'detalle' => 'Datos del cliente destacados, pago por cada medio y estado de pago en grande.',
        ],
    ];

    /**
     * Secciones de la plantilla detallada, en su orden por defecto.
     * `fija`: no se puede apagar (sin ella el ticket no sirve).
     * `docs`: en qué documentos aparece.
     */
    public const SECCIONES = [
        'logo'        => ['nombre' => 'Logo',                 'detalle' => 'El logo de la empresa, si tiene uno.',                                   'docs' => ['venta', 'cotizacion', 'despacho']],
        'negocio'     => ['nombre' => 'Datos del negocio',    'detalle' => 'Nombre, RUC, dirección y teléfono de la tienda.',                        'docs' => ['venta', 'cotizacion', 'despacho']],
        'documento'   => ['nombre' => 'Documento',            'detalle' => 'Tipo y número, fecha, quién atendió y su celular, caja.',               'docs' => ['venta', 'cotizacion', 'despacho'], 'fija' => true],
        'cliente'     => ['nombre' => 'Cliente',              'detalle' => 'Nombre, documento, teléfono, dirección y observación.',                 'docs' => ['venta', 'cotizacion', 'despacho']],
        'entrega'     => ['nombre' => 'Entrega',              'detalle' => 'Recojo o envío en grande, la ruta y la fecha programada. Solo con Entregas activado.', 'docs' => ['venta', 'despacho']],
        'items'       => ['nombre' => 'Productos',            'detalle' => 'Lo vendido: cantidad, precio e importe.',                               'docs' => ['venta', 'cotizacion'], 'fija' => true],
        'pendientes'  => ['nombre' => 'Por entregar',         'detalle' => 'Por producto: vendido, entregado y pendiente. Solo si quedó mercadería por entregar.', 'docs' => ['venta', 'despacho']],
        'totales'     => ['nombre' => 'Totales',              'detalle' => 'Descuento, IGV si corresponde y el total.',                             'docs' => ['venta', 'cotizacion'], 'fija' => true],
        'pagos'       => ['nombre' => 'Forma de pago',        'detalle' => 'Una línea por cada medio con su monto; a cuenta y saldo si quedó deuda.', 'docs' => ['venta']],
        'estado_pago' => ['nombre' => 'Estado de pago',       'detalle' => 'En grande: pagado, o por cancelar con el monto a cobrar.',              'docs' => ['venta', 'despacho']],
        'pie'         => ['nombre' => 'Pie',                  'detalle' => 'Código QR del comprobante, mensaje final y líneas extra.',              'docs' => ['venta', 'cotizacion', 'despacho']],
    ];

    /** Textos que cada empresa puede cambiar. */
    public const TEXTOS = [
        'titulo_cliente'       => ['nombre' => 'Título de los datos del cliente', 'defecto' => 'DATOS DEL CLIENTE', 'max' => 40],
        'titulo_pagos'         => ['nombre' => 'Título de la forma de pago',      'defecto' => 'FORMA DE PAGO',     'max' => 40],
        'pagado'               => ['nombre' => 'Cuando está pagado',              'defecto' => 'PAGADO',            'max' => 30],
        'por_cancelar'         => ['nombre' => 'Cuando queda saldo',              'defecto' => 'POR CANCELAR',      'max' => 30],
        'por_cancelar_detalle' => ['nombre' => 'Segunda línea cuando queda saldo (opcional)', 'defecto' => '',      'max' => 40],
    ];

    public const OPCIONES = [
        'cliente_recuadro' => ['nombre' => 'Teléfono y dirección del cliente en un recuadro', 'detalle' => 'En letra grande, para que el repartidor los vea de un vistazo.', 'defecto' => true],
        'celular_cajero'   => ['nombre' => 'Imprimir el celular de quien atendió',            'detalle' => 'Se registra en Configuración, Usuarios.',                          'defecto' => true],
        'banda_pagado'     => ['nombre' => 'Mostrar el estado también cuando está pagado',    'detalle' => 'Si se apaga, solo sale cuando queda saldo por cobrar.',           'defecto' => true],
        'items_dos_lineas' => ['nombre' => 'Producto arriba y números debajo',                 'detalle' => 'Si se apaga, va todo en una fila cuando el nombre cabe.',          'defecto' => true],
        'recuadros_texto'  => ['nombre' => 'Dibujar recuadros y bandas con letras',           'detalle' => 'Solo si la ticketera no imprime bien las imágenes.',              'defecto' => false],
    ];

    /**
     * La plantilla lista para usar: lo guardado sobre los valores por defecto.
     * Una sección que aún no existía cuando se guardó entra en su lugar por
     * defecto, así agregar secciones no obliga a reconfigurar empresas.
     *
     * @return array{plantilla:string, secciones:list<array{clave:string, activa:bool}>, textos:array<string,string>, opciones:array<string,bool>}
     */
    public static function resolver(mixed $guardada): array
    {
        $g = is_array($guardada) ? $guardada : [];

        $plantilla = array_key_exists($g['plantilla'] ?? '', self::PLANTILLAS) ? $g['plantilla'] : self::ESTANDAR;

        // Orden guardado primero; las que falten, tras su vecina anterior por defecto.
        $orden = [];
        $activa = [];
        foreach ((array) ($g['secciones'] ?? []) as $s) {
            $clave = is_array($s) ? ($s['clave'] ?? null) : null;
            if (is_string($clave) && isset(self::SECCIONES[$clave]) && !in_array($clave, $orden, true)) {
                $orden[] = $clave;
                $activa[$clave] = (bool) ($s['activa'] ?? true);
            }
        }
        $defecto = array_keys(self::SECCIONES);
        foreach ($defecto as $i => $clave) {
            if (in_array($clave, $orden, true)) {
                continue;
            }
            $pos = 0;
            for ($j = $i - 1; $j >= 0; $j--) {
                $k = array_search($defecto[$j], $orden, true);
                if ($k !== false) { $pos = $k + 1; break; }
            }
            array_splice($orden, $pos, 0, [$clave]);
        }

        $secciones = array_map(fn ($clave) => [
            'clave'  => $clave,
            'activa' => !empty(self::SECCIONES[$clave]['fija']) || ($activa[$clave] ?? true),
        ], $orden);

        $textos = [];
        foreach (self::TEXTOS as $clave => $def) {
            $v = $g['textos'][$clave] ?? null;
            $textos[$clave] = is_string($v) ? mb_substr(trim($v), 0, $def['max']) : $def['defecto'];
            // Vacío = volver al texto por defecto, salvo los que son opcionales.
            if ($textos[$clave] === '' && $def['defecto'] !== '') {
                $textos[$clave] = $def['defecto'];
            }
        }

        $opciones = [];
        foreach (self::OPCIONES as $clave => $def) {
            $opciones[$clave] = array_key_exists($clave, (array) ($g['opciones'] ?? []))
                ? (bool) $g['opciones'][$clave]
                : $def['defecto'];
        }

        return compact('plantilla', 'secciones', 'textos', 'opciones');
    }

    /**
     * La plantilla de una empresa lista para armar el ticket: incluye los
     * textos de Entregas, que se configuran en Configuración → Entregas.
     */
    public static function deEmpresa(?\App\Models\Empresa $empresa, mixed $plantilla = null): array
    {
        $pl = self::resolver($plantilla ?? $empresa?->ticket_plantilla);
        $pl['textos'] += ConfigEntregas::de($empresa)['textos'];

        return $pl;
    }

    /** Claves de las secciones activas para un documento, en orden. */
    public static function seccionesDe(array $plantilla, string $doc): array
    {
        return array_values(array_map(
            fn ($s) => $s['clave'],
            array_filter($plantilla['secciones'], fn ($s) => $s['activa']
                && in_array($doc, self::SECCIONES[$s['clave']]['docs'], true)),
        ));
    }

    /** Catálogo para la pantalla de configuración. */
    public static function catalogo(): array
    {
        $lista = fn (array $defs) => array_map(
            fn ($clave, $def) => ['clave' => $clave] + $def,
            array_keys($defs), $defs,
        );

        return [
            'plantillas' => $lista(self::PLANTILLAS),
            'secciones'  => $lista(self::SECCIONES),
            'textos'     => $lista(self::TEXTOS),
            'opciones'   => $lista(self::OPCIONES),
        ];
    }
}
