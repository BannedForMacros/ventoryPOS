<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DescuentoLog extends Model
{
    protected $table = 'descuentos_log';

    protected $fillable = [
        'empresa_id', 'venta_id', 'venta_item_id',
        'descuento_concepto_id', 'user_id', 'cliente_id', 'aprobado_por',
        'monto_descuento', 'requeria_aprobacion', 'notificacion_enviada',
    ];

    protected function casts(): array
    {
        return [
            'monto_descuento'      => 'decimal:2',
            'requeria_aprobacion'  => 'boolean',
            'notificacion_enviada' => 'boolean',
        ];
    }

    public function empresa(): BelongsTo          { return $this->belongsTo(Empresa::class); }
    public function venta(): BelongsTo            { return $this->belongsTo(Venta::class); }
    public function ventaItem(): BelongsTo        { return $this->belongsTo(VentaItem::class); }
    public function concepto(): BelongsTo         { return $this->belongsTo(DescuentoConcepto::class, 'descuento_concepto_id'); }
    public function user(): BelongsTo             { return $this->belongsTo(User::class); }
    public function cliente(): BelongsTo          { return $this->belongsTo(Cliente::class); }
    public function aprobadoPor(): BelongsTo      { return $this->belongsTo(User::class, 'aprobado_por'); }
}
