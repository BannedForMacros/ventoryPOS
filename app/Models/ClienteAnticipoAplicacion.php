<?php

namespace App\Models;

use App\Models\Concerns\AvisaTiempoReal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class ClienteAnticipoAplicacion extends Model
{
    use AvisaTiempoReal;

    protected static function recursosTiempoReal(): array { return ['despachos', 'anticipos']; }

    protected $table = 'cliente_anticipo_aplicaciones';

    protected $fillable = [
        'cliente_anticipo_id', 'empresa_id', 'numero', 'venta_id', 'user_id',
        'fecha', 'monto', 'cantidad', 'observacion', 'metodo_pago_id', 'cuenta_id',
        // Abono de CxC que consumió el anticipo (para revertir en pareja al anular).
        'venta_abono_id', 'deuda_pago_id',
    ];

    /**
     * Correlativo de la entrega: E-0001 por empresa. Mismo patrón que
     * Venta::generarNumero / Cotizacion::generarNumero (optimistic-insert:
     * el índice único (empresa_id, numero) protege ante colisiones; el caller
     * reintenta si dos entregas calculan el mismo número a la vez).
     */
    public static function generarNumero(int $empresaId): string
    {
        $max = (int) DB::table('cliente_anticipo_aplicaciones')
            ->where('empresa_id', $empresaId)
            ->selectRaw("COALESCE(MAX(CAST(SUBSTRING(numero FROM 3) AS INTEGER)), 0) as n")
            ->value('n');

        return 'E-' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    protected function casts(): array
    {
        return [
            'fecha'    => 'date:Y-m-d',
            'monto'    => 'decimal:2',
            'cantidad' => 'decimal:4',
        ];
    }

    public function anticipo(): BelongsTo   { return $this->belongsTo(ClienteAnticipo::class, 'cliente_anticipo_id'); }
    public function venta(): BelongsTo      { return $this->belongsTo(Venta::class); }
    public function user(): BelongsTo       { return $this->belongsTo(User::class); }
    public function metodoPago(): BelongsTo { return $this->belongsTo(MetodoPago::class, 'metodo_pago_id'); }
    public function cuenta(): BelongsTo     { return $this->belongsTo(Cuenta::class, 'cuenta_id'); }
    public function items(): HasMany      { return $this->hasMany(ClienteAnticipoAplicacionItem::class, 'cliente_anticipo_aplicacion_id'); }

    /**
     * Si este consumo del anticipo lo creó OTRO módulo, dice cuál (para el
     * mensaje); si es una entrega hecha aquí, null.
     *
     * Esos consumos (cobro de una CxC, cuota de una deuda, pago en el POS) van
     * en pareja con su documento: anularlos o editarlos desde Anticipos
     * devolvería el saldo y dejaría vivo el abono/pago, o registraría un egreso
     * de caja que nunca existió. Se anulan desde su módulo.
     */
    public function origenExterno(): ?string
    {
        if ($this->venta_abono_id) {
            return 'Cuentas por cobrar (es el cobro de un crédito con este anticipo)';
        }
        if ($this->deuda_pago_id) {
            return 'Deudas (es el cobro de una cuota con este anticipo)';
        }

        // Pago en el POS: anticipo en dinero aplicado a una venta, sin método de
        // salida (no salió dinero: se usó como forma de pago). Las entregas de
        // un pedido pendiente (multi-producto) también llevan venta_id, pero
        // esas sí se gestionan aquí.
        $anticipo = $this->anticipo;
        if ($this->venta_id && !$this->metodo_pago_id && $anticipo
            && $anticipo->tipo_valorizacion === 'monto'
            && !($anticipo->relationLoaded('items') ? $anticipo->items->isNotEmpty() : $anticipo->items()->exists())) {
            return 'Ventas (se usó para pagar una venta: anula o edita esa venta)';
        }

        return null;
    }
}
