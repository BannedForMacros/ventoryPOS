<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'empresa_id',
        'local_id',
        'rol_id',
        'name',
        'email',
        'password',
        'activo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'activo'            => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function local(): BelongsTo
    {
        return $this->belongsTo(Local::class);
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class)->with('permisos.modulo');
    }

    public function tienePermiso(string $slug, string $accion): bool
    {
        $rol = $this->rol;
        if (!$rol) return false;
        if ($rol->es_admin) return true;

        $permiso = $rol->permisos->first(fn ($p) => $p->modulo?->slug === $slug);
        return $permiso && (bool) $permiso->{$accion};
    }
}
