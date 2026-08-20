<?php

namespace App\Http\Requests\Ventas;

use App\Models\Cuenta;
use App\Models\Empresa;
use App\Models\MetodoPago;
use App\Models\ProductoUnidad;
use App\Services\Facturacion\FacturacionEmpresa;
use App\Services\LocalScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreVentaRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;

        return [
            // Cliente: si viene, debe ser de la empresa Y estar activo.
            'cliente_id'             => [
                'nullable', 'integer',
                Rule::exists('clientes', 'id')
                    ->where('empresa_id', $empresaId)
                    ->where('activo', true),
            ],
            'tipo_comprobante'       => ['required', Rule::in(['ticket', 'boleta', 'factura', 'boleta_externa', 'factura_externa'])],
            'numero_comprobante'     => ['nullable', 'string', 'max:30'],            'observacion'            => ['nullable', 'string', 'max:500'],
            // Multimoneda: moneda de la venta y TC del día (soles por 1 USD).
            // Los precios/pagos vienen EN esa moneda; el backend convierte a soles.
            'moneda'                 => ['nullable', Rule::in(['PEN', 'USD'])],
            'tipo_cambio'            => ['nullable', 'numeric', 'gt:0', 'required_if:moneda,USD'],
            'descuento_total'        => ['nullable', 'numeric', 'min:0'],
            // F1 — Venta a crédito: se entrega mercadería sin cobrar el total.
            // Exige cliente identificado (se valida en withValidator) y permite
            // pagos parciales o sin pago inicial.
            'es_credito'             => ['nullable', 'boolean'],
            'fecha_vencimiento'      => ['nullable', 'date', 'after_or_equal:today'],
            // Pendiente por entregar: el cliente paga TODO pero se lleva solo
            // parte de la mercadería. El POS crea un anticipo material con los
            // ítems pendientes (items.*.cantidad_pendiente) y el stock de lo
            // pendiente se descuenta recién al entregarlo.
            // La fecha estimada es informativa: puede quedar en el pasado al
            // EDITAR una venta vieja o al backdatear un turno reabierto.
            'entrega_pendiente'      => ['nullable', 'boolean'],
            'fecha_entrega_estimada' => ['nullable', 'date'],
            // Backdate de admin: registrar la venta en un turno REABIERTO ajeno
            // con la fecha real en que ocurrió. Solo se honran si el usuario es
            // admin (guardas en el controlador).
            'turno_id'               => ['nullable', 'integer', Rule::exists('turnos', 'id')->where('empresa_id', $empresaId)],
            'fecha_venta'            => ['nullable', 'date', 'before_or_equal:today'],
            // Token unico generado por el frontend para prevenir duplicados por reintentos.
            // Si el cliente reenvia la misma venta (timeout, doble click, etc.) el backend
            // detecta el key y devuelve la venta ya creada en lugar de duplicarla.
            'idempotency_key'        => ['nullable', 'string', 'min:10', 'max:100'],
            // true → botón "Crear e imprimir" (auto-imprime el ticket); false/ausente
            // → botón "Confirmar venta" (solo crea, sin imprimir).
            'imprimir'               => ['nullable', 'boolean'],
            // Si la venta nace de una cita prellenada en el POS. Opcional.
            'cita_id'                => ['nullable', 'integer', Rule::exists('citas', 'id')->where('empresa_id', $empresaId)],
            // Si la venta nace de una cotización prellenada en el POS. Opcional.
            'cotizacion_id'          => ['nullable', 'integer', Rule::exists('cotizaciones', 'id')->where('empresa_id', $empresaId)],
            // Anticipo de cliente (efectivo) con el que se pagará parte o todo de la venta.
            'anticipo_id'            => ['nullable', 'integer', Rule::exists('cliente_anticipos', 'id')->where('empresa_id', $empresaId)],
            'descuento_concepto_id'  => [
                'nullable', 'integer',
                Rule::exists('descuento_conceptos', 'id')
                    ->where('empresa_id', $empresaId)
                    ->where('activo', true),
            ],

            // Items: producto debe ser de la empresa Y activo.
            // producto_unidad_id se valida cruzado en withValidator (pertenencia + activo).
            'items'                           => ['required', 'array', 'min:1'],
            'items.*.producto_id'             => [
                'required', 'integer',
                Rule::exists('productos', 'id')
                    ->where('empresa_id', $empresaId)
                    ->where('activo', true),
            ],
            'items.*.producto_unidad_id'      => ['required', 'integer', 'exists:producto_unidades,id'],
            'items.*.cantidad'                => ['required', 'numeric', 'min:0.0001'],
            // Cuánto de la línea queda SIN entregar (solo con entrega_pendiente).
            'items.*.cantidad_pendiente'      => ['nullable', 'numeric', 'min:0'],
            'items.*.precio_unitario'         => ['required', 'numeric', 'min:0'],
            'items.*.descuento_item'          => ['nullable', 'numeric', 'min:0'],
            'items.*.descuento_concepto_id'   => [
                'nullable', 'integer',
                Rule::exists('descuento_conceptos', 'id')
                    ->where('empresa_id', $empresaId)
                    ->where('activo', true),
            ],

            // Pagos: metodo activo y de la empresa.
            // cuenta_metodo_pago_id se valida cruzado en withValidator (debe pertenecer
            // al metodo Y a la empresa, y la cuenta debe estar activa).
            // En venta a crédito los pagos son opcionales (pago inicial parcial o nada).
            'pagos'                              => [$this->boolean('es_credito') ? 'nullable' : 'required', 'array', $this->boolean('es_credito') ? 'min:0' : 'min:1'],
            'pagos.*.metodo_pago_id'             => [
                'required', 'integer',
                Rule::exists('metodos_pago', 'id')
                    ->where('empresa_id', $empresaId)
                    ->where('activo', true),
            ],
            'pagos.*.cuenta_metodo_pago_id'      => ['nullable', 'integer', 'exists:cuenta_metodo_pago,id'],
            'pagos.*.monto'                      => ['required', 'numeric', 'min:0.01'],
            'pagos.*.referencia'                 => ['nullable', 'string', 'max:200'],
            // El flag `es_efectivo` del frontend ya no se usa: el backend deriva
            // la decision desde metodos_pago.admite_vuelto. Se acepta y se ignora
            // para no romper clientes viejos del API.
            'pagos.*.es_efectivo'                => ['nullable', 'boolean'],
            'pagos.*.admite_vuelto'              => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $empresaId = $this->user()->empresa_id;

            $this->validarItems($validator, $empresaId);
            $this->validarPagosPertenencia($validator, $empresaId);
            $this->validarDescuentoYTope($validator); // M20
            $this->validarAnticipo($validator, $empresaId);

            $total = $this->calcularTotalEsperado($empresaId);

            if ($this->boolean('es_credito')) {
                // F1 — Crédito: los pagos NO necesitan cubrir el total, pero
                // tampoco pueden excederlo (un sobrepago no es crédito) y el
                // cliente debe estar identificado (no Cliente General: sin
                // nombre no hay a quién cobrarle).
                $this->validarVentaCredito($validator, $total, $empresaId);
            } else {
                $this->validarTotalCubierto($validator, $total);
            }

            $this->validarSobrepagoNoEfectivo($validator, $total, $empresaId);
            $this->validarEntregaPendiente($validator, $empresaId);
            $this->validarComprobanteElectronico($validator, $empresaId, $total); // V13
        });
    }

    /**
     * V13 — Requisitos de SUNAT para boleta/factura, validados ANTES de cerrar
     * la venta.
     *
     * POR QUÉ AQUÍ Y NO DESPUÉS: si la venta se cierra y recién entonces
     * descubrimos que la factura no tiene RUC, el dinero ya entró a caja y el
     * comprobante queda impagable — solo se arregla anulando o con Nota de
     * Crédito. Es infinitamente más barato decírselo a la cajera con el cliente
     * todavía en el mostrador.
     *
     * El flujo `ticket` (hoy el 100 % de las ventas) NO cambia en absoluto:
     * estas reglas solo se activan con tipo_comprobante boleta o factura.
     */
    private function validarComprobanteElectronico($validator, int $empresaId, float $total): void
    {
        // Mientras la emisión de ESTA empresa esté apagada, el POS debe comportarse
        // EXACTAMENTE como antes de existir este archivo: 'boleta' y 'factura' son
        // meras etiquetas que no emiten nada, y rechazar una venta por reglas de
        // SUNAT que nadie va a aplicar sería romper la caja sin contrapartida.
        //
        // POR EMPRESA, no por instalación: antes esto era
        // `config('facturamac.enabled')`, un flag del `.env` común a los dos
        // contribuyentes de esta instalación. Ahora se pregunta por la empresa que
        // está vendiendo, que es la única que puede responderlo.
        if (! app(FacturacionEmpresa::class)->activa($empresaId)) {
            return;
        }

        $tipo = $this->input('tipo_comprobante');
        if (!in_array($tipo, ['boleta', 'factura'], true)) {
            return; // ticket o comprobantes externos: no aplican reglas SUNAT
        }

        // G12 — La venta puede cobrarse en USD, pero ventoryPOS contabiliza
        // todo en soles al TC congelado. Emitir en dólares exigiría un mapeo
        // distinto que hoy no existe, así que se bloquea de raíz.
        $moneda = strtoupper((string) ($this->input('moneda') ?: 'PEN'));
        if ($moneda !== 'PEN') {
            $validator->errors()->add(
                'moneda',
                'Los comprobantes electrónicos solo se emiten en soles. Cambia la moneda a PEN o registra la venta como ticket.',
            );
        }

        $clienteId = $this->input('cliente_id');
        $cliente   = $clienteId
            ? \App\Models\Cliente::where('id', $clienteId)->where('empresa_id', $empresaId)->first()
            : null;

        // G5 — Factura (01): SUNAT rechaza toda factura cuyo adquirente no
        // tenga RUC y dirección. Sin cliente se usaría el Cliente General, que
        // no tiene ninguno de los dos.
        if ($tipo === 'factura') {
            $esRuc        = $cliente && strtoupper((string) $cliente->tipo_documento) === 'RUC';
            $tieneDirec   = $cliente && trim((string) $cliente->direccion) !== '';
            $identificado = $cliente && !$cliente->es_cliente_general;

            if (!$identificado || !$esRuc || !$tieneDirec) {
                $validator->errors()->add(
                    'cliente_id',
                    'Una factura requiere un cliente con RUC y dirección.',
                );
            }

            return; // el umbral de boleta no aplica a facturas
        }

        // Boleta (03): SUNAT exige identificar al adquirente a partir del umbral.
        // Por debajo se admite el Cliente General (documento tipo "0").
        //
        // EL UMBRAL LO DICTA EL EMISOR (§7), no este archivo. Antes se leía
        // `config('facturamac.umbral_boleta_identificada')`, una clave que ya no
        // existe en el config adelgazado: el POS aplicaba SIEMPRE 700 aunque el
        // tenant tuviera configurado otro. Con un umbral de 500 en el emisor, el
        // POS dejaba cerrar una boleta de 600 al Cliente General y el emisor la
        // rechazaba DESPUÉS de cobrar — justo el "fallar tarde" que esta
        // validación existe para evitar.
        //
        // No poder leer la configuración NO bloquea la venta: `umbralBoletaIdentificada()`
        // no lanza y cae al default legal (700). Lo peor que puede pasar con el
        // emisor caído es aplicar el umbral de ayer; lo que no puede pasar es que
        // la caja deje de cobrar.
        $umbral = app(FacturacionEmpresa::class)
            ->configuracion($empresaId)
            ->umbralBoletaIdentificada();
        if ($total >= $umbral - 0.01) {
            if (!$cliente || $cliente->es_cliente_general) {
                $validator->errors()->add(
                    'cliente_id',
                    sprintf(
                        'Esta boleta suma S/ %s. Desde S/ %s SUNAT exige identificar al cliente: '
                        . 'selecciona (o registra) uno con DNI o RUC antes de cobrar.',
                        number_format($total, 2),
                        number_format($umbral, 2),
                    ),
                );
            }
        }
    }

    /**
     * Pendiente por entregar (POS): reglas de consistencia.
     *
     *  - Requiere cliente identificado (no Cliente General): sin nombre no hay
     *    a quién apuntarle la mercadería pendiente.
     *  - Incompatible con venta a crédito SALVO que la venta ya esté saldada
     *    (monto_pagado >= total). En ese caso el anticipo material sí representa
     *    dinero recibido y no distorsiona la contabilidad.
     *  - Cada cantidad_pendiente debe ser <= a la cantidad de su línea, y al
     *    menos una línea debe dejar algo pendiente (si no, es venta normal).
     */
    private function validarEntregaPendiente($validator, int $empresaId): void
    {
        if (!$this->boolean('entrega_pendiente')) return;

        if ($this->boolean('es_credito')) {
            $venta = $this->route('venta');
            $estaSaldada = $venta instanceof \App\Models\Venta
                && (float) $venta->total > 0
                && (float) $venta->saldo_pendiente <= 0.0001;

            if (!$estaSaldada) {
                $validator->errors()->add(
                    'entrega_pendiente',
                    'No se puede combinar "Pendiente por entregar" con venta a crédito salvo que el crédito ya esté totalmente pagado.',
                );
            }
        }

        $clienteId = $this->input('cliente_id');
        $esGeneral = !$clienteId || \App\Models\Cliente::where('id', $clienteId)
            ->where('empresa_id', $empresaId)
            ->value('es_cliente_general');
        if ($esGeneral) {
            $validator->errors()->add(
                'cliente_id',
                'Marcar mercadería pendiente por entregar requiere un cliente identificado (no Cliente General).',
            );
        }

        $hayPendiente = false;
        foreach ($this->input('items', []) as $index => $item) {
            $cantidad  = (float) ($item['cantidad'] ?? 0);
            $pendiente = (float) ($item['cantidad_pendiente'] ?? 0);
            if ($pendiente < 0) continue; // min:0 ya lo atrapa

            if ($pendiente > $cantidad + 0.00009) {
                $validator->errors()->add(
                    "items.{$index}.cantidad_pendiente",
                    'La cantidad pendiente por entregar no puede superar la cantidad vendida de la línea.',
                );
            }
            if ($pendiente > 0.00009) $hayPendiente = true;
        }

        if (!$hayPendiente) {
            $validator->errors()->add(
                'entrega_pendiente',
                'Marcaste "Pendiente por entregar" pero ninguna línea tiene cantidad pendiente. Indica cuánto se queda o desmarca la opción.',
            );
        }
    }

    /**
     * M20 — Reglas de gobernanza del descuento global:
     *
     *  (a) Si descuento_total > 0 → exigir descuento_concepto_id (motivo
     *      auditable, no puede ser regalo arbitrario).
     *
     *  (b) Si el rol del usuario tiene `max_descuento_porcentaje` definido,
     *      validar que el descuento aplicado no exceda ese porcentaje del
     *      subtotal bruto del carrito.
     *
     *  Admin típicamente tiene `max_descuento_porcentaje = NULL` (sin tope).
     *  Cajeros pueden estar limitados a 10%, 20%, etc. según política de cada
     *  empresa.
     */
    private function validarDescuentoYTope($validator): void
    {
        $descuentoTotal = (float) ($this->input('descuento_total') ?? 0);
        if ($descuentoTotal <= 0) return;

        // (a) Concepto obligatorio cuando hay descuento global
        if (empty($this->input('descuento_concepto_id'))) {
            $validator->errors()->add(
                'descuento_concepto_id',
                'Debes seleccionar un motivo (concepto de descuento) cuando aplicas un descuento global.'
            );
            return;
        }

        // (b) Tope porcentual por rol
        $user = $this->user();
        $user->loadMissing('rol');
        $topePct = $user->rol?->max_descuento_porcentaje;
        if ($topePct === null) return; // sin tope (típicamente admin)

        // Subtotal bruto del carrito (suma simple, no la base IGV-separada).
        // El cajero entiende este monto como "el total antes del descuento".
        $subtotalBruto = 0.0;
        foreach ($this->input('items', []) as $item) {
            $precio    = (float) ($item['precio_unitario'] ?? 0);
            $descItem  = (float) ($item['descuento_item']  ?? 0);
            $cantidad  = (float) ($item['cantidad']        ?? 0);
            $subtotalBruto += ($precio - $descItem) * $cantidad;
        }

        if ($subtotalBruto <= 0) return; // sin productos válidos, otra regla lo atrapa

        $pctSolicitado = round(($descuentoTotal / $subtotalBruto) * 100, 2);
        if ($pctSolicitado > (float) $topePct + 0.01) {
            $validator->errors()->add(
                'descuento_total',
                "Tu rol permite máximo {$topePct}% de descuento sobre el subtotal. "
                . "Estás aplicando {$pctSolicitado}%. Pide aprobación de un supervisor."
            );
        }
    }

    /**
     * Valida que cada item:
     *   - Su producto_unidad_id pertenezca al producto_id indicado.
     *   - La unidad este activa (no fue desactivada despues de cargar el catalogo).
     *   - El producto este activo (defensa redundante por si paso el exists()).
     */
    private function validarItems($validator, int $empresaId): void
    {
        $unidadIds = collect($this->input('items', []))
            ->pluck('producto_unidad_id')
            ->filter()
            ->unique()
            ->all();

        $unidades = ProductoUnidad::with('producto')
            ->whereIn('id', $unidadIds)
            ->get()
            ->keyBy('id');

        // Costo promedio real del stock en el almacén de ventas del usuario.
        // Se usa como piso de precio antes que el campo estático productos.precio_costo,
        // porque este último no se actualiza automáticamente y queda desfasado.
        $almacenVentas = app(LocalScopeService::class)->almacenParaVentas($this->user());
        $productoIds   = $unidades->pluck('producto_id')->unique()->all();
        $stockCostos   = ($almacenVentas && !empty($productoIds))
            ? DB::table('stock')
                ->where('almacen_id', $almacenVentas->id)
                ->whereIn('producto_id', $productoIds)
                ->pluck('costo_promedio', 'producto_id')
            : collect();

        foreach ($this->input('items', []) as $index => $item) {
            $unidadId   = $item['producto_unidad_id'] ?? null;
            $productoId = $item['producto_id']        ?? null;
            if (!$unidadId || !$productoId) continue;

            $unidad = $unidades->get($unidadId);
            if (!$unidad) continue;

            if ((int) $unidad->producto_id !== (int) $productoId) {
                $validator->errors()->add(
                    "items.{$index}.producto_unidad_id",
                    'La unidad no pertenece al producto seleccionado.',
                );
                continue;
            }

            // Defense in depth: la unidad y el producto deben ser de la empresa.
            // exists() ya filtra el producto, pero la unidad solo se busca por id,
            // asi que un atacante podria enviar una unidad de otra empresa cuyo
            // producto_id coincida con un producto de su propia empresa. Bloqueamos.
            if (!$unidad->producto || $unidad->producto->empresa_id !== $empresaId) {
                $validator->errors()->add(
                    "items.{$index}.producto_unidad_id",
                    'La unidad no pertenece a tu empresa.',
                );
                continue;
            }

            if ($unidad->activo === false) {
                $validator->errors()->add(
                    "items.{$index}.producto_unidad_id",
                    'La presentación seleccionada está inactiva.',
                );
            }

            if ($unidad->producto->activo === false) {
                $validator->errors()->add(
                    "items.{$index}.producto_id",
                    'El producto seleccionado fue desactivado. Refresca el catálogo.',
                );
            }

            // Piso de precio: el POS permite editar el precio de venta por linea,
            // pero nunca por debajo del costo real de la mercaderia. Orden de
            // prioridad:
            //   1) costo propio de la presentacion (producto_unidades.precio_costo)
            //   2) costo promedio del stock del almacen de ventas × factor_conversion
            //   3) fallback al costo base del producto (productos.precio_costo)
            // Costo 0/null = sin piso.
            $factor         = (float) $unidad->factor_conversion;
            $costoUnidad    = (float) ($unidad->precio_costo ?? 0);
            $costoStock     = (float) ($stockCostos[$unidad->producto_id] ?? 0);
            $costoProducto  = (float) ($unidad->producto->precio_costo ?? 0);

            $costoMinimo = 0.0;
            if ($costoUnidad > 0) {
                $costoMinimo = $costoUnidad;
            } elseif ($costoStock > 0 && $factor > 0) {
                $costoMinimo = round($costoStock * $factor, 2);
            } elseif ($costoProducto > 0 && $factor > 0) {
                $costoMinimo = round($costoProducto * $factor, 2);
            }

            $precio = (float) ($item['precio_unitario'] ?? 0);
            if ($costoMinimo > 0 && $precio < $costoMinimo - 0.009) {
                $validator->errors()->add(
                    "items.{$index}.precio_unitario",
                    sprintf(
                        'El precio de "%s" (S/ %s) no puede ser menor al costo (S/ %s).',
                        $unidad->producto->nombre,
                        number_format($precio, 2),
                        number_format($costoMinimo, 2),
                    ),
                );
            }
        }
    }

    /**
     * Valida que, cuando un pago incluye cuenta_metodo_pago_id:
     *   - La cuenta exista, sea de la empresa y este activa.
     *   - La cuenta este efectivamente vinculada al metodo_pago_id elegido.
     *
     * Sin esta validacion un usuario podria enviar el ID de una cuenta de otra
     * empresa o de un metodo distinto, y el pago se asentaria contra esa cuenta.
     */
    private function validarPagosPertenencia($validator, int $empresaId): void
    {
        // Cuenta OBLIGATORIA: si el método de pago tiene cuentas vinculadas, el
        // pago debe traer una (con 1 el front la autoselecciona; con 2+ el usuario
        // elige). Los métodos SIN cuentas (efectivo, electrónico sin vincular) se
        // resuelven solos en TesoreriaService, así que no se exigen.
        $metodoIds = collect($this->input('pagos', []))
            ->pluck('metodo_pago_id')->filter()->unique()->all();
        $metodosConCuentas = empty($metodoIds) ? [] : DB::table('cuenta_metodo_pago')
            ->whereIn('metodo_pago_id', $metodoIds)
            ->pluck('metodo_pago_id')->flip()->all();

        foreach ($this->input('pagos', []) as $index => $pago) {
            $metodoId = $pago['metodo_pago_id'] ?? null;
            $pivotId  = $pago['cuenta_metodo_pago_id'] ?? null;
            if ($metodoId && !$pivotId && isset($metodosConCuentas[$metodoId])) {
                $validator->errors()->add(
                    "pagos.{$index}.cuenta_metodo_pago_id",
                    'Debes seleccionar la cuenta para este método de pago.',
                );
            }
        }

        $pivotIds = collect($this->input('pagos', []))
            ->pluck('cuenta_metodo_pago_id')
            ->filter()
            ->unique()
            ->all();

        if (empty($pivotIds)) return;

        $pivots = DB::table('cuenta_metodo_pago')
            ->whereIn('id', $pivotIds)
            ->get()
            ->keyBy('id');

        $cuentaIds = $pivots->pluck('cuenta_id')->unique()->all();
        $cuentas = Cuenta::whereIn('id', $cuentaIds)->get()->keyBy('id');

        foreach ($this->input('pagos', []) as $index => $pago) {
            $pivotId  = $pago['cuenta_metodo_pago_id'] ?? null;
            $metodoId = $pago['metodo_pago_id']        ?? null;
            if (!$pivotId) continue;

            $pivot = $pivots->get($pivotId);
            if (!$pivot) continue;

            // La cuenta debe pertenecer a la empresa del usuario y estar activa.
            $cuenta = $cuentas->get($pivot->cuenta_id);
            if (!$cuenta || $cuenta->empresa_id !== $empresaId) {
                $validator->errors()->add(
                    "pagos.{$index}.cuenta_metodo_pago_id",
                    'La cuenta seleccionada no pertenece a tu empresa.',
                );
                continue;
            }
            if ($cuenta->activo === false) {
                $validator->errors()->add(
                    "pagos.{$index}.cuenta_metodo_pago_id",
                    'La cuenta seleccionada está inactiva.',
                );
                continue;
            }

            // El pivot debe corresponder al metodo enviado.
            if ((int) $pivot->metodo_pago_id !== (int) $metodoId) {
                $validator->errors()->add(
                    "pagos.{$index}.cuenta_metodo_pago_id",
                    'La cuenta no pertenece al método de pago seleccionado.',
                );
            }
        }
    }

    /**
     * Valida el anticipo de efectivo indicado para pagar la venta.
     * Debe ser activo, tipo 'monto', pertenecer al cliente seleccionado y
     * pertenecer a la empresa. No se permite combinar con venta a crédito.
     */
    private function validarAnticipo($validator, int $empresaId): void
    {
        $anticipoId = $this->input('anticipo_id');
        if (!$anticipoId) return;

        $clienteId = $this->input('cliente_id');
        if (!$clienteId) {
            $validator->errors()->add('anticipo_id', 'Para usar un anticipo debes seleccionar un cliente.');
            return;
        }

        $anticipo = \App\Models\ClienteAnticipo::where('id', $anticipoId)
            ->where('empresa_id', $empresaId)
            ->where('cliente_id', $clienteId)
            ->where('tipo_valorizacion', 'monto')
            ->where('estado', 'activo')
            ->first();

        if (!$anticipo) {
            $validator->errors()->add('anticipo_id', 'El anticipo no existe, no pertenece al cliente o no está activo.');
            return;
        }

        if ((float) $anticipo->saldo <= 0.009) {
            $validator->errors()->add('anticipo_id', 'El anticipo seleccionado no tiene saldo disponible.');
        }
    }

    /**
     * Monto usable del anticipo para esta venta (mínimo entre saldo y total).
     * Devuelve 0 si no hay anticipo o no es válido.
     */
    private function montoAnticipoUsable(float $total): float
    {
        $anticipoId = $this->input('anticipo_id');
        if (!$anticipoId) return 0.0;

        $anticipo = \App\Models\ClienteAnticipo::where('id', $anticipoId)
            ->where('tipo_valorizacion', 'monto')
            ->where('estado', 'activo')
            ->first();

        if (!$anticipo) return 0.0;

        return min((float) $anticipo->saldo, $total);
    }

    /**
     * Calculo espejo de Venta::calcularTotales: separa base gravada (afecta IGV)
     * de base exonerada (no afecta IGV) y prorrateamos el descuento_total entre
     * ambas. Tasa de IGV se lee de la empresa.
     */
    private function calcularTotalEsperado(int $empresaId): float
    {
        $tasa = (float) (Empresa::find($empresaId)?->tasa_igv ?? 18) / 100;

        $baseGravadaRaw = 0.0;
        $baseExonerada  = 0.0;

        foreach ($this->input('items', []) as $item) {
            $precio     = (float) ($item['precio_unitario'] ?? 0);
            $descuento  = (float) ($item['descuento_item'] ?? 0);
            $cantidad   = (float) ($item['cantidad'] ?? 0);
            $incluyeIgv = !empty($item['incluye_igv']);
            $importe    = ($precio - $descuento) * $cantidad;

            if ($incluyeIgv) {
                $baseGravadaRaw += $tasa > 0 ? $importe / (1 + $tasa) : $importe;
            } else {
                $baseExonerada += $importe;
            }
        }

        // Mismo criterio que Venta::calcularTotales(): el descuento_total es BRUTO;
        // se prorratea por el bruto de cada base y su parte gravada se divide entre
        // (1+tasa) antes de restarla a la base neta, para que el total baje exacto.
        $descuentoTotal = (float) ($this->input('descuento_total') ?? 0);
        $brutoGravado   = $baseGravadaRaw * (1 + $tasa);
        $totalBruto     = $brutoGravado + $baseExonerada;

        if ($totalBruto > 0 && $descuentoTotal > 0) {
            $descGravadoBruto = $descuentoTotal * ($brutoGravado / $totalBruto);
            $descExonerado    = $descuentoTotal * ($baseExonerada / $totalBruto);
            $descGravadoNeto  = $tasa > 0 ? $descGravadoBruto / (1 + $tasa) : $descGravadoBruto;
        } else {
            $descGravadoNeto = $descExonerado = 0.0;
        }

        $baseGravadaFinal = max(0, $baseGravadaRaw - $descGravadoNeto);
        $baseExonFinal    = max(0, $baseExonerada  - $descExonerado);

        // Misma secuencia de redondeo que Venta::calcularTotales(): primero
        // redondear IGV a 2 decimales y luego sumar al total. Esto garantiza
        // que el "total esperado" coincida bit-a-bit con el total persistido,
        // sin centavos de diferencia que provocarian "pagos no cubren".
        $igv = round($baseGravadaFinal * $tasa, 2);
        return round($baseGravadaFinal + $igv + $baseExonFinal, 2);
    }

    /**
     * F1 — Reglas específicas de la venta a crédito.
     */
    private function validarVentaCredito($validator, float $total, int $empresaId): void
    {
        // Cliente obligatorio y distinto del Cliente General.
        $clienteId = $this->input('cliente_id');
        if (!$clienteId) {
            $validator->errors()->add(
                'cliente_id',
                'Una venta a crédito requiere seleccionar un cliente identificado.',
            );
        } else {
            $esGeneral = \App\Models\Cliente::where('id', $clienteId)
                ->where('empresa_id', $empresaId)
                ->value('es_cliente_general');
            if ($esGeneral) {
                $validator->errors()->add(
                    'cliente_id',
                    'No se puede vender a crédito al Cliente General. Selecciona un cliente con nombre.',
                );
            }
        }

        // El pago inicial no puede exceder el total (eso no sería crédito).
        // El anticipo del cliente cuenta como pago inicial.
        $totalPagado = round(collect($this->input('pagos', []))->sum(fn($p) => (float) ($p['monto'] ?? 0)) + $this->montoAnticipoUsable($total), 2);
        if ($totalPagado > $total + 0.01) {
            $validator->errors()->add(
                'pagos',
                "En una venta a crédito el pago inicial ({$totalPagado}) no puede exceder el total ({$total}).",
            );
        }
    }

    private function validarTotalCubierto($validator, float $total): void
    {
        $totalPagado = round(collect($this->input('pagos', []))->sum(fn($p) => (float) ($p['monto'] ?? 0)), 2);
        $totalPagado += $this->montoAnticipoUsable($total);

        if ($totalPagado < $total - 0.01) {
            $validator->errors()->add(
                'pagos',
                "El total pagado ({$totalPagado}) no cubre el total de la venta ({$total}).",
            );
        }
    }

    /**
     * Regla de sobrepago:
     *   - Pagos cuyo metodo tiene `admite_vuelto=true` SI pueden sobrepasar el
     *     total (genera vuelto fisico). Tipicamente efectivo, pero el admin
     *     puede marcar otros (ej. vales internos con devolucion).
     *   - La suma de pagos que NO admiten vuelto no puede exceder el total
     *     de la venta. Cualquier sobrepago debe pagarse con un metodo que SI
     *     admita vuelto, porque es el unico capaz de devolverlo fisicamente.
     *
     * Fuente de verdad: la columna `metodos_pago.admite_vuelto`, decidida por
     * el admin al crear/editar cada metodo. Esto reemplaza la inferencia
     * `tipo='efectivo'` anterior, que rompia escenarios reales (admin crea
     * "SIP" o "Sodexo" y el sistema asumia mal su comportamiento de vuelto).
     *
     * Casos validos (asumiendo Efectivo.admite_vuelto=true, Tarjeta=false):
     *   - Total 50, efectivo 70             → OK (vuelto 20).
     *   - Total 50, tarjeta 50              → OK.
     *   - Total 100, tarjeta 80 + efect 30  → OK (vuelto 10 efectivo).
     *
     * Casos invalidos:
     *   - Total 50, tarjeta 70              → exceso 20 sin metodo de vuelto.
     *   - Total 50, tarjeta 40 + yape 20    → exceso 10 sin metodo de vuelto.
     */
    private function validarSobrepagoNoEfectivo($validator, float $total, int $empresaId): void
    {
        $metodoIds = collect($this->input('pagos', []))
            ->pluck('metodo_pago_id')
            ->filter()
            ->unique()
            ->all();

        $metodos = MetodoPago::whereIn('id', $metodoIds)
            ->where('empresa_id', $empresaId)
            ->get()
            ->keyBy('id');

        $sinVueltoTotal = 0.0;
        $sinVueltoIdx   = [];

        foreach ($this->input('pagos', []) as $index => $pago) {
            $monto    = (float) ($pago['monto'] ?? 0);
            $metodoId = $pago['metodo_pago_id'] ?? null;
            $metodo   = $metodoId ? $metodos->get($metodoId) : null;

            // Fuente de verdad: flag `admite_vuelto` en BD. Si el metodo no
            // existe en BD lo tratamos como "no admite vuelto" (defensa).
            $admiteVuelto = (bool) ($metodo?->admite_vuelto);
            if ($admiteVuelto) continue;

            $sinVueltoTotal += $monto;
            $sinVueltoIdx[]  = $index;
        }

        if ($sinVueltoTotal > $total + 0.01) {
            $exceso = round($sinVueltoTotal - $total, 2);
            foreach ($sinVueltoIdx as $idx) {
                $validator->errors()->add(
                    "pagos.{$idx}.monto",
                    "La suma de pagos sin vuelto (S/ {$sinVueltoTotal}) excede el total "
                    . "de la venta (S/ {$total}). Exceso: S/ {$exceso}. Usa un método que "
                    . "admita vuelto para cubrir el excedente.",
                );
            }
        }
    }
}
