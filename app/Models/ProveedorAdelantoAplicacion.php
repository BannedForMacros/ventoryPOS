<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProveedorAdelantoAplicacion extends Model
{
    protected $table = 'proveedor_adelanto_aplicaciones';

    protected $fillable = [
        'proveedor_adelanto_id', 'entrada_id', 'user_id',
        'fecha', 'monto', 'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto' => 'decimal:2',
        ];
    }

    public function adelanto(): BelongsTo { return $this->belongsTo(ProveedorAdelanto::class, 'proveedor_adelanto_id'); }
    public function entrada(): BelongsTo  { return $this->belongsTo(Entrada::class); }
    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
}
