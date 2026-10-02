<?php

use Illuminate\Support\Facades\Broadcast;

/*
| Canales de tiempo real (Reverb). Se autorizan en POST /broadcasting/auth
| con la sesión del usuario.
*/

// Un canal por empresa: solo sus usuarios lo escuchan. Lleva los avisos de
// "cambió X" (App\Events\Cambio) para que las pantallas abiertas se pongan
// al día sin recargar.
Broadcast::channel('empresa.{empresaId}', function ($user, $empresaId) {
    return $user->empresa_id !== null && (int) $user->empresa_id === (int) $empresaId;
});
