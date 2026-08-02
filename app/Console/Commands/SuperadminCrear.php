<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Crea (o promueve) el superadmin global: el usuario del PROVEEDOR que entra a
 * /admin a dar de alta empresas y usuarios. No pertenece a ninguna empresa
 * (empresa_id NULL) ni tiene rol de tenant: el middleware `superadmin` es lo
 * único que lo autoriza.
 *
 * Se hace por comando y no por seeder ni por el panel: nadie puede escalarse a
 * superadmin desde la interfaz (es_superadmin no es fillable), solo quien tiene
 * acceso a la consola del servidor.
 *
 *   php artisan superadmin:crear soporte@macsoft.pe "MacSoft" --password="secreta"
 */
class SuperadminCrear extends Command
{
    protected $signature = 'superadmin:crear {email} {name?} {--password=}';

    protected $description = 'Crea o promueve al superadmin global del panel /admin';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));

        if ($existente = User::where('email', $email)->first()) {
            $existente->forceFill([
                'es_superadmin'     => true,
                'email_verified_at' => $existente->email_verified_at ?? now(),
                'activo'            => true,
            ])->save();

            $this->info("«{$existente->name}» ({$email}) promovido a superadmin.");
            if ($existente->empresa_id) {
                $this->warn('Ojo: este usuario pertenece a una empresa y a partir de ahora el login lo mandará a /admin, no al panel de su empresa. Lo usual es un usuario dedicado sin empresa.');
            }
            return self::SUCCESS;
        }

        $password = $this->option('password') ?: Str::password(16);

        $user = new User([
            'name'     => $this->argument('name') ?? 'Superadmin',
            'email'    => $email,
            'password' => $password,
            'activo'   => true,
        ]);
        $user->forceFill([
            'empresa_id'        => null,
            'local_id'          => null,
            'rol_id'            => null,
            'es_superadmin'     => true,
            'email_verified_at' => now(),
        ])->save();

        $this->info("Superadmin creado: {$email}");
        if (!$this->option('password')) {
            $this->warn("Contraseña generada: {$password}  (cámbiala tras el primer ingreso)");
        }
        return self::SUCCESS;
    }
}
