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
        'es_credito', 'monto_pagado', 'saldo_pendiente', 'fecha_vencimiento',
        'moneda', 'tipo_cambio', 'monto_moneda',
    ];

    protected function casts(): array
    {
        return [
            'fecha_venta'       => 'datetime',
            'subtotal'          => 'decimal:2',
            'descuento_total'   => 'decimal:2',
            'igv'               => 'decimal:2',
            'total'             => 'decimal:2',
            'es_credito'        => 'boolean',
            'monto_pagado'      => 'decimal:2',
            'saldo_pendiente'   => 'decimal:2',
            'fecha_vencimiento' => 'date:Y-m-d',
            'tipo_cambio'       => 'decimal:6',
            'monto_moneda'      => 'decimal:2',
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
    public function abonos(): HasMany            { return $this->hasMany(VentaAbono::class); }
    public function descuentosLog(): HasMany     { return $this->hasMany(DescuentoLog::class); }
    /** Anticipos "pendiente por entregar" creados por esta venta desde el POS. */
    public function anticipos(): HasMany         { return $this->hasMany(ClienteAnticipo::class, 'venta_id'); }

    public function scopeCompletadas(Builder $q): Builder        { return $q->where('estado', 'completada'); }
    public function scopeAnuladas(Builder $q): Builder           { return $q->where('estado', 'anulada'); }
    public function scopeDeEmpresa(Builder $q, int $id): Builder { return $q->where('empresa_id', $id); }

    /** Cuentas por cobrar: ventas a crédito completadas con saldo pendiente. */
    public function scopeConSaldoPendiente(Builder $q): Builder
    {
        return $q->where('es_credito', true)
                 ->where('estado', 'completada')
                 ->where('saldo_pendiente', '>', 0);
    }

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

        // El descuento_total está en SOLES BRUTOS (lo que debe bajar el total, IGV
        // incluido). Se prorratea por el BRUTO de cada base; en la parte gravada se
        // convierte a neto dividiéndolo entre (1+tasa) ANTES de restarlo a la base
        // sin IGV, para que al re-sumar el IGV el total baje EXACTAMENTE el descuento
        // (antes bajaba descuento×(1+tasa) y el cajero tenía que compensar a mano).
        $descuento   = (float) $this->descuento_total;
        $brutoGravado = $baseGravadaRaw * (1 + $tasa);
        $totalBruto   = $brutoGravado + $baseExonerada;

        if ($totalBruto > 0 && $descuento > 0) {
            $descGravadoBruto = $descuento * ($brutoGravado / $totalBruto);
            $descExonerado    = $descuento * ($baseExonerada / $totalBruto);
            $descGravadoNeto  = $tasa > 0 ? $descGravadoBruto / (1 + $tasa) : $descGravadoBruto;
        } else {
            $descGravadoNeto = $descExonerado = 0.0;
        }

        $baseGravadaFinal = max(0, $baseGravadaRaw - $descGravadoNeto);
        $baseExonFinal    = max(0, $baseExonerada  - $descExonerado);

        $igv   = round($baseGravadaFinal * $tasa, 2);
        $total = round($baseGravadaFinal + $igv + $baseExonFinal, 2);

        $this->update([
            'subtotal' => round($subtotal, 2),
            'igv'      => $igv,
            'total'    => $total,
        ]);
    }

    /**
     * Calcula el siguiente número correlativo para un turno: V-0001, V-0002, ...
     *
     * NOTA importante sobre concurrencia: esta funcion NO bloquea filas.
     * En su lugar, la garantía de unicidad la da el UNIQUE constraint
     * `ventas_turno_id_numero_unique`. Si dos requests calculan el mismo
     * número y ambos intentan insertar, el segundo recibe
     * UniqueConstraintViolationException y `VentaService::crear` reintenta
     * (patrón optimistic-insert + retry). La query MAX es solo el cálculo
     * inicial — la BD es la fuente final de la verdad.
     */
    public static function generarNumero(int $turnoId): string
    {
        $max = (int) DB::table('ventas')
            ->where('turno_id', $turnoId)
            ->selectRaw('COALESCE(MAX(CAST(SUBSTRING(numero FROM 3) AS INTEGER)), 0) as n')
            ->value('n');

        return 'V-' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
