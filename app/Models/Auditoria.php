<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de auditoria. Solo lectura desde la app — los inserts viven en
 * AuditoriaService::log(). Esto evita que se escriban auditorias accidentales
 * desde controllers/seeders.
 */
class Auditoria extends Model
{
    protected $table = 'auditoria';

    public $timestamps = false; // solo created_at lo asigna la BD

    protected $fillable = [
        'empresa_id', 'user_id', 'user_name', 'accion',
        'modelo_tipo', 'modelo_id', 'contexto', 'ip', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'contexto'   => 'array',
            'modelo_id'  => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }

    public function scopeDeEmpresa(Builder $q, int $id): Builder { return $q->where('empresa_id', $id); }

    /**
     * Etiqueta legible para mostrar en UI. Mapea slugs de accion a texto humano.
     */
    public function getAccionLabelAttribute(): string
    {
        return self::accionLabels()[$this->accion] ?? $this->accion;
    }

    public static function accionLabels(): array
    {
        return [
            'venta.anulada'           => 'Venta anulada',
            'devolucion.creada'       => 'Devolución creada',
            'devolucion.aprobada'     => 'Devolución aprobada',
            'devolucion.rechazada'    => 'Devolución rechazada',
            'devolucion.anulada'      => 'Devolución anulada',
            'turno.cerrado'           => 'Turno cerrado',
            'turno.reabierto'         => 'Turno reabierto',
            'stock.recalculado'       => 'Stock recalculado',
            'permisos.modificados'    => 'Permisos modificados',
            'usuario.creado'          => 'Usuario creado',
            'usuario.actualizado'     => 'Usuario actualizado',
            'usuario.eliminado'       => 'Usuario eliminado',
            'producto.eliminado'      => 'Producto eliminado',
            'transferencia.anulada'   => 'Transferencia anulada',
            'entrada.confirmada'      => 'Entrada confirmada',
            'cita.creada'             => 'Cita creada',
            'cita.confirmada'         => 'Cita confirmada',
            'cita.iniciada'           => 'Cita iniciada (en atención)',
            'cita.cancelada'          => 'Cita cancelada',
            'cita.no_asistio'         => 'Cita marcada como no asistió',
            'cita.completada'         => 'Cita completada (cobrada)',
        ];
    }
}
