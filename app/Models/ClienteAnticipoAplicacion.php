<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class ClienteAnticipoAplicacion extends Model
{
    protected $table = 'cliente_anticipo_aplicaciones';

    protected $fillable = [
        'cliente_anticipo_id', 'empresa_id', 'numero', 'venta_id', 'user_id',
        'fecha', 'monto', 'cantidad', 'observacion',
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

    public function anticipo(): BelongsTo { return $this->belongsTo(ClienteAnticipo::class, 'cliente_anticipo_id'); }
    public function venta(): BelongsTo    { return $this->belongsTo(Venta::class); }
    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function items(): HasMany      { return $this->hasMany(ClienteAnticipoAplicacionItem::class, 'cliente_anticipo_aplicacion_id'); }
}
