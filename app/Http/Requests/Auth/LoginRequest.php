<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public const MENSAJE_USUARIO_INACTIVO = 'Tu usuario está desactivado. Habla con el administrador.';
    public const MENSAJE_EMPRESA_INACTIVA = 'Tu empresa está desactivada. Habla con el proveedor del sistema.';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // 'activo' => true: un usuario desactivado no inicia sesión aunque su
        // contraseña sea correcta.
        if (! Auth::attempt([...$this->only('email', 'password'), 'activo' => true], $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => $this->credencialesDeUsuarioInactivo()
                    ? self::MENSAJE_USUARIO_INACTIVO
                    : trans('auth.failed'),
            ]);
        }

        // Empresa desactivada (desde /admin): nadie de ella entra. El
        // superadmin no tiene empresa y no se ve afectado.
        $user = Auth::user();
        if (! $user->es_superadmin && $user->empresa_id && ! $user->empresa?->activo) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => self::MENSAJE_EMPRESA_INACTIVA,
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * ¿El intento falló solo porque el usuario está desactivado? Únicamente
     * se distingue si la contraseña es correcta: así el mensaje específico no
     * le sirve a nadie para averiguar qué correos existen.
     */
    private function credencialesDeUsuarioInactivo(): bool
    {
        $user = User::where('email', $this->string('email'))->first();

        return $user && ! $user->activo && Hash::check((string) $this->string('password'), $user->password);
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
