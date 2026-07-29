<?php

namespace Database\Factories;

use App\Models\Empresa;
use App\Models\Local;
use App\Models\Rol;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * ventoryPOS es multiempresa: `users.empresa_id` es NOT NULL y el producto
     * entero (middleware `permiso`, el share de Inertia, los scopes por empresa)
     * da por hecho que un usuario cuelga de una empresa. El usuario "suelto" que
     * generaba el andamiaje de Breeze no existe aquí: ni siquiera entra en la
     * tabla, revienta con 23502 not-null violation en `empresa_id`.
     *
     * Por eso la factory crea la tenencia mínima que hace válido a un usuario:
     * empresa + local + rol. Es el mismo esqueleto que arma Tests\Support\TestEnv,
     * recortado a lo que necesita la capa de autenticación (TestEnv sigue siendo
     * la vía para lo demás: cajas, almacenes, productos, métodos de pago…).
     *
     * Las claves se resuelven en orden y cada closure recibe lo ya resuelto, así
     * que si quien llama sobreescribe `empresa_id`, el local y el rol se crean
     * dentro de ESA empresa en lugar de inventarse otra.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'empresa_id' => fn () => Empresa::create([
                'razon_social' => fake()->company(),
                'ruc'          => $this->rucUnico(),
            ])->id,

            'local_id' => fn (array $attributes) => Local::create([
                'empresa_id'   => $attributes['empresa_id'],
                'nombre'       => 'Local Principal',
                'es_principal' => true,
            ])->id,

            // es_admin => cortocircuita User::tienePermiso, igual que el admin de
            // TestEnv. Un rol sin permisos dejaría al usuario sin poder abrir nada.
            'rol_id' => fn (array $attributes) => Rol::create([
                'empresa_id' => $attributes['empresa_id'],
                'nombre'     => 'Administrador',
                'es_admin'   => true,
            ])->id,

            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'activo' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * `empresas.ruc` tiene índice único y la BD de tests es la real del proyecto
     * (con sus empresas ya dentro), así que no basta con un random a ciegas.
     */
    private function rucUnico(): string
    {
        do {
            $ruc = '20' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
        } while (Empresa::where('ruc', $ruc)->exists());

        return $ruc;
    }
}
