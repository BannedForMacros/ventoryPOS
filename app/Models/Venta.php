<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Venta extends Model
{
    protected $fillable = [
        'empresa_id', 'local_id', 'turno_id', 'caja_id', 'user_id', 'cliente_id',
        'numero', 'tipo_comprobante',
        'subtotal', 'descuento_total', 'descuento_concepto_id', 'igv', 'total',
        'estado', 'observacion', 'fecha_venta',
    ];

    protected function casts(): array
    {
        return [
            'fecha_venta'     => 'datetime',
            'subtotal'        => 'decimal:2',
            'descuento_total' => 'decimal:2',
            'igv'             => 'decimal:2',
            'total'           => 'decimal:2',
        ];
    }

    public function empresa(): BelongsTo         { return $this->belongsTo(Empresa::class); }
    public function local(): BelongsTo           { return $this->belongsTo(Local::class); }
    public function turno(): BelongsTo           { return $this->belongsTo(Turno::class); }
    public function caja(): BelongsTo            { return $this->belongsTo(Caja::class); }
    public function user(): BelongsTo            { return $this->belongsTo(User::class); }
    public function cliente(): BelongsTo         { return $this->belongsTo(Cliente::class); }
    public function descuentoConcepto(): BelongsTo { return $this->belongsTo(DescuentoConcepto::class); }
    public function items(): HasMany             { return $this->hasMany(VentaItem::class); }
    public function pagos(): HasMany             { return $this->hasMany(VentaPago::class); }
    public function descuentosLog(): HasMany     { return $this->hasMany(DescuentoLog::class); }

    public function scopeCompletadas(Builder $q): Builder        { return $q->where('estado', 'completada'); }
    public function scopeAnuladas(Builder $q): Builder           { return $q->where('estado', 'anulada'); }
    public function scopeDeEmpresa(Builder $q, int $id): Builder { return $q->where('empresa_id', $id); }

    public function calcularTotales(): void
    {
        // subtotal = suma de precios tal como se ingresaron (pueden incluir IGV o no)
        $subtotal = $this->items->sum(fn ($i) =>
            ($i->precio_unitario - $i->descuento_item) * $i->cantidad
        );

        // base para IGV: extraer precio neto de items que ya incluyen IGV
        $baseItems = $this->items->sum(fn ($i) =>
            $i->incluye_igv
                ? (($i->precio_unitario - $i->descuento_item) * $i->cantidad) / 1.18
                : ($i->precio_unitario - $i->descuento_item) * $i->cantidad
        );

        $base = max(0, $baseItems - (float) $this->descuento_total);
        $igv  = round($base * 0.18, 2);

        $this->update([
            'subtotal' => $subtotal,
            'igv'      => $igv,
            'total'    => round($base + $igv, 2),
        ]);
    }

    public static function generarNumero(int $turnoId): string
    {
        // Numerar por turno: cada turno arranca en V-0001
        // lockForUpdate sobre la subquery evita duplicados en concurrencia
        $sub = DB::table('ventas')
            ->select(DB::raw("CAST(SUBSTRING(numero FROM 3) AS INTEGER) as n"))
            ->where('turno_id', $turnoId)
            ->lockForUpdate();

        $max = DB::table(DB::raw("({$sub->toSql()}) as sub"))
            ->mergeBindings($sub)
            ->max('n');

        $siguiente = ($max ?? 0) + 1;

        return 'V-' . str_pad($siguiente, 4, '0', STR_PAD_LEFT);
    }
}
