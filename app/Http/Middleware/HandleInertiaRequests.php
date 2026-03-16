<?php

namespace App\Http\Middleware;

use App\Models\Modulo;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? $user->load([
                    'empresa',
                    'local',
                    'rol.permisos.modulo',
                ]) : null,
            ],
            'modules' => fn () => $user ? $this->buildModulesTree($user) : [],
            'flash' => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ];
    }

    private function buildModulesTree($user): array
    {
        $modulos = Modulo::with('hijos')
            ->whereNull('padre_id')
            ->where('activo', true)
            ->orderBy('orden')
            ->get();

        return $modulos->map(fn ($mod) => $this->formatModulo($mod, $user))->filter()->values()->toArray();
    }

    private function formatModulo($modulo, $user): ?array
    {
        $rol = $user->rol;

        if ($modulo->hijos->isNotEmpty()) {
            $hijos = $modulo->hijos
                ->where('activo', true)
                ->sortBy('orden')
                ->map(fn ($h) => $this->formatModulo($h, $user))
                ->filter()
                ->values()
                ->toArray();

            if (empty($hijos)) return null;

            return [
                'id'     => $modulo->id,
                'nombre' => $modulo->nombre,
                'slug'   => $modulo->slug,
                'icono'  => $modulo->icono,
                'ruta'   => $modulo->ruta,
                'orden'  => $modulo->orden,
                'hijos'  => $hijos,
            ];
        }

        if ($rol && !$rol->es_admin) {
            $tiene = $rol->permisos->first(fn ($p) => $p->modulo?->slug === $modulo->slug);
            if (!$tiene || !$tiene->ver) return null;
        }

        return [
            'id'     => $modulo->id,
            'nombre' => $modulo->nombre,
            'slug'   => $modulo->slug,
            'icono'  => $modulo->icono,
            'ruta'   => $modulo->ruta,
            'orden'  => $modulo->orden,
            'hijos'  => [],
        ];
    }
}
