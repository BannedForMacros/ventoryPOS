<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurnoArqueoMetodo extends Model
{
    protected $table = 'turno_arqueo_metodos';

    protected $fillable = ['turno_id', 'metodo_pago_id', 'monto_declarado'];

    public function turno(): BelongsTo      { return $this->belongsTo(Turno::class); }
    public function metodoPago(): BelongsTo { return $this->belongsTo(MetodoPago::class); }
}
