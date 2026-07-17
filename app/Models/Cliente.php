<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Cliente extends Model
{
    protected $fillable = [
        'empresa_id',
        'tipo_documento',
        'numero_documento',
        'nombres',
        'apellidos',
        'razon_social',
        'telefono',
        'email',
        'direccion',
        'fecha_nacimiento',
        'activo',
        'es_cliente_general',
    ];

    /** Accessor calculado que el frontend necesita para mostrar el nombre en la tabla. */
    protected $appends = ['nombre_completo'];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento'   => 'date',
            'activo'             => 'boolean',
            'es_cliente_general' => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeActivo(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeDeEmpresa(Builder $query, int $empresaId): Builder
    {
        return $query->where('empresa_id', $empresaId);
    }

    // ── Accessors ───────────────────────────────────────────────────────────

    public function getNombreCompletoAttribute(): string
    {
        if ($this->razon_social) {
            return $this->razon_social;
        }

        return trim($this->nombres . ' ' . $this->apellidos);
    }

    /**
     * Retorna el Cliente General de la empresa (el "cliente por defecto" para
     * ventas sin cliente identificado). Fuente de verdad: la columna
     * `es_cliente_general` (A15). La convención anterior "DNI 99999999"
     * quedó atrás porque dependía de una regla SUNAT puntual.
     */
    public static function generalDeEmpresa(int $empresaId): ?self
    {
        return static::where('empresa_id', $empresaId)
            ->where('es_cliente_general', true)
            ->first();
    }

    /**
     * Garantiza que la empresa tenga su Cliente General ("Clientes varios") y
     * lo devuelve. Idempotente: el índice único parcial
     * (clientes_un_general_por_empresa) asegura a lo sumo uno por empresa, y
     * firstOrCreate no lo duplica en llamadas repetidas. Útil para tenants que
     * nunca tuvieron el DNI 99999999 del backfill (A15), de modo que módulos
     * como Cotizaciones/Anticipos siempre puedan ofrecer "Clientes varios".
     */
    public static function asegurarGeneralDeEmpresa(int $empresaId): self
    {
        return static::firstOrCreate(
            ['empresa_id' => $empresaId, 'es_cliente_general' => true],
            ['nombres' => 'Clientes varios', 'apellidos' => '', 'activo' => true],
        );
    }
}
