<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurnoArqueo extends Model
{
    protected $table = 'turno_arqueo';

    protected $fillable = ['turno_id', 'denominacion', 'cantidad'];

    const DENOMINACIONES_PEN = [200, 100, 50, 20, 10, 5, 2, 1, 0.50, 0.20, 0.10];

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }
}
