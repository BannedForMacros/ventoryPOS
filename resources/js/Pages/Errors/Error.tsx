import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Home, LogIn } from 'lucide-react';
import RouterLoadingOverlay from '@/Components/RouterLoadingOverlay';

interface Props {
    status: number;
    message?: string | null;
}

interface ErrorContent {
    titulo: string;
    descripcion: string;
}

const CONTENIDO: Record<number, ErrorContent> = {
    401: {
        titulo: 'Necesitas iniciar sesión',
        descripcion: 'Tu sesión ha caducado o aún no has ingresado. Vuelve a iniciar sesión para continuar.',
    },
    403: {
        titulo: 'Oops... no tienes permiso',
        descripcion: 'Tu rol no incluye esta acción. Pide al administrador del sistema que ajuste tus permisos.',
    },
    404: {
        titulo: '¿Y esa página?',
        descripcion: 'La dirección que buscas no existe o fue movida a otra parte. Revisa el enlace o vuelve al inicio.',
    },
    419: {
        titulo: 'La página expiró',
        descripcion: 'Pasaste demasiado tiempo en esta pantalla. Recarga la página y vuelve a intentarlo.',
    },
    429: {
        titulo: 'Demasiadas solicitudes',
        descripcion: 'Estás haciendo peticiones muy seguido. Espera unos segundos antes de volver a intentar.',
    },
    500: {
        titulo: 'Algo salió mal',
        descripcion: 'Tuvimos un problema al procesar tu solicitud. Intenta de nuevo en un momento o avisa al soporte.',
    },
    503: {
        titulo: 'Estamos en mantenimiento',
        descripcion: 'El sistema está temporalmente fuera de servicio. Vuelve en unos minutos.',
    },
};

const FALLBACK: ErrorContent = {
    titulo: 'Algo no funcionó',
    descripcion: 'Ocurrió un error inesperado. Intenta de nuevo en unos segundos.',
};

/** Carita triste con lágrima animada. Inline SVG: sin assets externos. */
function CaritaTriste() {
    return (
        <svg
            viewBox="0 0 240 240"
            className="h-44 w-44 sm:h-56 sm:w-56"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <defs>
                <radialGradient id="cara" cx="50%" cy="40%" r="60%">
                    <stop offset="0%" stopColor="#fde68a" />
                    <stop offset="100%" stopColor="#fbbf24" />
                </radialGradient>
                <linearGradient id="lagrima" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" stopColor="#60a5fa" stopOpacity="0.9" />
                    <stop offset="100%" stopColor="#2563eb" />
                </linearGradient>
            </defs>

            {/* sombra */}
            <ellipse cx="120" cy="218" rx="70" ry="8" fill="#000" opacity="0.08" />

            {/* cara */}
            <circle cx="120" cy="115" r="92" fill="url(#cara)" stroke="#f59e0b" strokeWidth="3" />

            {/* ceja izquierda */}
            <path d="M65 85 Q85 70 100 80" stroke="#7c2d12" strokeWidth="6" fill="none" strokeLinecap="round" />
            {/* ceja derecha */}
            <path d="M140 80 Q155 70 175 85" stroke="#7c2d12" strokeWidth="6" fill="none" strokeLinecap="round" />

            {/* ojo izquierdo cerrado */}
            <path d="M70 110 Q85 122 100 110" stroke="#1f2937" strokeWidth="5" fill="none" strokeLinecap="round" />
            {/* ojo derecho cerrado */}
            <path d="M140 110 Q155 122 170 110" stroke="#1f2937" strokeWidth="5" fill="none" strokeLinecap="round" />

            {/* boca triste */}
            <path d="M80 168 Q120 140 160 168" stroke="#1f2937" strokeWidth="6" fill="none" strokeLinecap="round" />

            {/* lágrima animada */}
            <g style={{ transformOrigin: '85px 130px' }}>
                <path
                    d="M85 130 Q78 145 85 155 Q92 145 85 130 Z"
                    fill="url(#lagrima)"
                >
                    <animateTransform
                        attributeName="transform"
                        type="translate"
                        values="0,0; 0,40; 0,0"
                        keyTimes="0; 0.7; 1"
                        dur="2.4s"
                        repeatCount="indefinite"
                    />
                    <animate
                        attributeName="opacity"
                        values="0; 1; 1; 0"
                        keyTimes="0; 0.15; 0.7; 1"
                        dur="2.4s"
                        repeatCount="indefinite"
                    />
                </path>
            </g>
        </svg>
    );
}

export default function ErrorPage({ status, message }: Props) {
    const { titulo, descripcion } = CONTENIDO[status] ?? FALLBACK;
    const mensajeBackend = message?.trim();

    const esDeAuth = status === 401;
    const accionPrimaria = esDeAuth
        ? { href: route('login'), texto: 'Iniciar sesión', icon: <LogIn size={18} /> }
        : { href: route('dashboard'), texto: 'Ir al dashboard', icon: <Home size={18} /> };

    return (
        <>
            <Head title={`Error ${status}`} />
            <RouterLoadingOverlay />

            <div
                className="flex min-h-screen flex-col items-center justify-center px-6 py-12"
                style={{ backgroundColor: 'var(--color-background, #f3f4f6)' }}
            >
                <div className="w-full max-w-xl text-center">
                    <div className="flex justify-center">
                        <CaritaTriste />
                    </div>

                    <p
                        className="mt-6 text-7xl font-black tracking-tight sm:text-8xl"
                        style={{ color: 'var(--color-primary, #2563eb)' }}
                    >
                        {status}
                    </p>

                    <h1
                        className="mt-2 text-2xl font-bold sm:text-3xl"
                        style={{ color: 'var(--color-text, #111827)' }}
                    >
                        {titulo}
                    </h1>

                    <p
                        className="mt-3 text-base leading-relaxed"
                        style={{ color: 'var(--color-text-muted, #6b7280)' }}
                    >
                        {descripcion}
                    </p>

                    {mensajeBackend && mensajeBackend !== titulo && (
                        <p
                            className="mt-4 inline-block rounded-md px-3 py-2 text-sm"
                            style={{
                                backgroundColor: 'var(--color-surface, #fff)',
                                border: '1px solid var(--color-border, #e5e7eb)',
                                color: 'var(--color-text-muted, #6b7280)',
                            }}
                        >
                            {mensajeBackend}
                        </p>
                    )}

                    <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        <Link
                            href={accionPrimaria.href}
                            className="inline-flex items-center justify-center gap-2 rounded-md px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2"
                            style={{
                                backgroundColor: 'var(--color-primary, #2563eb)',
                            }}
                        >
                            {accionPrimaria.icon}
                            {accionPrimaria.texto}
                        </Link>

                        {!esDeAuth && (
                            <button
                                type="button"
                                onClick={() => window.history.back()}
                                className="inline-flex items-center justify-center gap-2 rounded-md border px-5 py-2.5 text-sm font-medium transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2"
                                style={{
                                    borderColor: 'var(--color-border, #d1d5db)',
                                    color: 'var(--color-text, #374151)',
                                    backgroundColor: 'var(--color-surface, #fff)',
                                }}
                            >
                                <ArrowLeft size={18} />
                                Volver atrás
                            </button>
                        )}
                    </div>
                </div>

                <p className="mt-12 text-xs" style={{ color: 'var(--color-text-muted, #9ca3af)' }}>
                    ventoryPOS · Si el problema persiste, contacta al administrador.
                </p>
            </div>
        </>
    );
}
