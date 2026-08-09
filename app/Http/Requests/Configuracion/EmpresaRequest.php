<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('empresa')?->id;

        return [
            'razon_social'     => 'required|string|max:255',
            'nombre_comercial' => 'nullable|string|max:255',
            'ruc'              => ['required', 'string', 'size:11', Rule::unique('empresas', 'ruc')->ignore($id)],
            'direccion'        => 'nullable|string|max:255',
            'telefono'         => 'nullable|string|max:20',
            'email'            => 'nullable|email|max:255',
            // Logo de la empresa: archivo PNG/JPG (opcional). Se sube en el form
            // multipart; si no viene, el logo actual se conserva.
            'logo'             => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            // Plantilla del ticket impreso: el admin decide qué sale sin tocar
            // el agente de impresión. Se ensamblan en ticket_config (JSON).
            'ticket_cliente_celular'   => 'boolean',
            'ticket_cliente_direccion' => 'boolean',
            'ticket_mostrar_ruc'       => 'boolean',
            'ticket_logo_escala'       => 'nullable|integer|min:10|max:200',
            'ticket_pie'               => 'nullable|string|max:500',
            // Líneas libres al final del ticket, una por renglón (Yape, redes...).
            'ticket_lineas_extra'      => 'nullable|string|max:500',
            'activo'           => 'boolean',
            // Tasa de IGV aplicable a la empresa (en %). 18.00 default Perú.
            // Topes generosos para tolerar reformas tributarias o paises con
            // tasas distintas; 0 permite empresas exentas (RUS, comercio inafecto).
            'tasa_igv'         => 'nullable|numeric|min:0|max:30',
            'modo_almacen'     => 'required|in:simple,central_y_local',
            'descuenta_stock_en_venta'        => 'boolean',
            // Si esta activo, el POS permite vender aunque no alcance el stock:
            // el saldo queda negativo y se avisa al cerrar la caja.
            'permite_stock_negativo'          => 'boolean',
            // Si esta activo, el POS permite agregar el mismo producto/presentación
            // varias veces en una misma venta. Útil para precios variables.
            'permite_duplicar_items_venta'    => 'boolean',
            'modo_cierre_caja'                => 'required|in:rapido,con_declaraciones',
            'modo_cierre_inventario'          => 'required|in:por_venta,declarado',
            'cierre_precarga_stock'           => 'boolean',
            'usa_fondos_iniciales'            => 'boolean',
            'fondos_iniciales_en_declaracion' => 'boolean',
            // F8 — Si está activo, el balance diario toma el conteo del
            // CONSOLIDADOR (segundo conteo); si no, el cierre de la cajera.
            'requiere_consolidacion_caja'     => 'boolean',
            'permite_devoluciones'            => 'boolean',
            'dias_max_devolucion'             => 'nullable|integer|min:0|max:365',
            'requiere_aprobacion_devolucion'  => 'boolean',
            'restock_default'                 => 'boolean',
            // Manejo de efectivo (todo opt-in; defaults = comportamiento clásico)
            'modo_apertura_caja'              => 'sometimes|in:libre,arrastre,fondo_fijo',
            'apertura_editable'               => 'boolean',
            'usa_retiros_caja'                => 'boolean',
            'retiro_requiere_aprobacion'      => 'boolean',
            'cierre_pregunta_destino'         => 'boolean',
            'usa_caja_grande'                 => 'boolean',
            // Ventas: comportamiento de edición/anulación por cajeras.
            'venta_edicion_minutos'           => 'required|integer|min:0|max:120',
            'cajera_puede_anular'             => 'boolean',
            // Mercadería en tránsito (comprada/facturada pero que aún no llega).
            // `vende_` solo tiene efecto si `usa_` está activo — lo resuelve
            // MercaderiaTransitoService, no hace falta validación cruzada.
            'usa_mercaderia_transito'         => 'boolean',
            'vende_mercaderia_transito'       => 'boolean',
            // "Afecta caja" por módulo: mapa { modulo: { activo: bool } }.
            // Solo se persiste el flag `activo`; labels/defaults viven en el
            // registro App\Support\AfectaCaja, no en la BD.
            'afecta_caja_config'              => ['nullable', 'array'],
            'afecta_caja_config.*.activo'     => ['boolean'],
        ];
    }
}
