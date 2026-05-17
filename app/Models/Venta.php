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
        'numero', 'idempotency_key', 'tipo_comprobante',
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

    /**
     * Calcula y persiste totales separando base gravada (afecta IGV) de la base
     * exonerada (no afecta IGV). Esto es critico porque cada producto define
     * `incluye_igv`: cuando es false, el producto esta exonerado (medicamentos
     * vet, ciertos alimentos) y NUNCA debe pagar IGV.
     *
     * Reglas de calculo:
     *   - subtotal: suma bruta tal como se ingresaron los precios (auditoria).
     *   - Por item, derivamos la base neta:
     *       incluye_igv=true  → base = importe / (1 + tasa)   (precio bruto contiene IGV)
     *       incluye_igv=false → base = importe                (exonerado)
     *   - descuento_total se reparte proporcionalmente entre la base gravada
     *     y la exonerada, para no desplazar la carga tributaria.
     *   - IGV final = base_gravada (post-descuento) * tasa.
     *   - total     = base_gravada + igv + base_exonerada.
     *
     * La tasa se lee de la empresa de la venta (tasa_igv, default 18.00).
     */
    public function calcularTotales(): void
    {
        $this->loadMissing('empresa');
        $tasa = (float) ($this->empresa?->tasa_igv ?? 18) / 100;

        $subtotal       = 0.0;
        $baseGravadaRaw = 0.0;
        $baseExonerada  = 0.0;

        foreach ($this->items as $i) {
            $importe = ((float) $i->precio_unitario - (float) $i->descuento_item) * (float) $i->cantidad;
            $subtotal += $importe;

            if ($i->incluye_igv) {
                $baseGravadaRaw += $tasa > 0 ? $importe / (1 + $tasa) : $importe;
            } else {
                $baseExonerada += $importe;
            }
        }

        // Repartir descuento_total proporcional a cada base. Si solo hay una,
        // se aplica completo ahi. Evita "regalar" IGV al cobrar descuentos.
        $descuento = (float) $this->descuento_total;
        $totalBases = $baseGravadaRaw + $baseExonerada;

        if ($totalBases > 0 && $descuento > 0) {
            $descGravado   = $descuento * ($baseGravadaRaw / $totalBases);
            $descExonerado = $descuento * ($baseExonerada  / $totalBases);
        } else {
            $descGravado = $descExonerado = 0.0;
        }

        $baseGravadaFinal = max(0, $baseGravadaRaw - $descGravado);
        $baseExonFinal    = max(0, $baseExonerada  - $descExonerado);

        $igv   = round($baseGravadaFinal * $tasa, 2);
        $total = round($baseGravadaFinal + $igv + $baseExonFinal, 2);

        $this->update([
            'subtotal' => round($subtotal, 2),
            'igv'      => $igv,
            'total'    => $total,
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
