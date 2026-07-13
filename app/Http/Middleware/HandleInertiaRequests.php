<?php

namespace App\Http\Middleware;

use App\Models\Cotizacion;
use App\Models\Modulo;
use App\Models\Turno;
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
            'turno_activo' => fn () => auth()->check()
                ? Turno::turnoActivoDelUsuario(auth()->id())?->load('caja')
                : null,
            'flash' => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
            // Campanita del header: cotizaciones por vencer / vencidas sin
            // respuesta. Solo para quien puede VER cotizaciones. Lazy: se
            // evalúa una única vez por request (queries baratas apoyadas en
            // los índices empresa+estado y empresa+fecha_vencimiento).
            'alertasCotizaciones' => fn () => $user && $user->tienePermiso('ventas.cotizaciones', 'ver')
                ? $this->alertasCotizaciones($user->empresa_id)
                : null,
        ];
    }

    /**
     * Conteos para la campanita de cotizaciones:
     *  - por_vencer: vigentes que vencen entre hoy y +3 días.
     *  - vencidas_sin_respuesta: vencidas (o vigentes ya pasadas de fecha,
     *    aún no marcadas por el update masivo del index) sin contacto
     *    registrado después del vencimiento.
     */
    private function alertasCotizaciones(int $empresaId): array
    {
        $hoy    = now()->toDateString();
        $limite = now()->addDays(3)->toDateString();

        $porVencer = Cotizacion::deEmpresa($empresaId)
            ->vigente()
            ->whereBetween('fecha_vencimiento', [$hoy, $limite])
            ->count();

        $vencidas = Cotizacion::deEmpresa($empresaId)
            ->where(fn ($q) => $q->where('estado', Cotizacion::ESTADO_VENCIDA)
                ->orWhere(fn ($v) => $v->vigente()->where('fecha_vencimiento', '<', $hoy)))
            ->where(fn ($q) => $q->whereNull('ultimo_contacto')
                ->orWhereColumn('ultimo_contacto', '<', 'fecha_vencimiento'))
            ->count();

        return [
            'por_vencer'             => $porVencer,
            'vencidas_sin_respuesta' => $vencidas,
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
