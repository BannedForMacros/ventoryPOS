import RouterLoadingOverlay from '@/Components/RouterLoadingOverlay';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowRight,
    BarChart3,
    Boxes,
    CheckCircle2,
    Eye,
    EyeOff,
    Lock,
    Mail,
    ShieldCheck,
    Sparkles,
    Zap,
} from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';

const palette = {
    primary: '#3b82f6',
    primaryHover: '#2563eb',
    primaryDark: '#1e40af',
    success: '#10b981',
    danger: '#ef4444',
    warning: '#f59e0b',
    sidebarBg: '#1e293b',
    bg: '#f8fafc',
    surface: '#ffffff',
    border: '#e2e8f0',
    text: '#0f172a',
    textMuted: '#64748b',
};

const highlights = [
    { icon: Zap, label: 'POS rápido', desc: 'Vende en segundos' },
    { icon: Boxes, label: 'Inventario', desc: 'Stock por local' },
    { icon: BarChart3, label: 'Reportes', desc: 'En tiempo real' },
    { icon: ShieldCheck, label: 'Auditoría', desc: 'Trazabilidad total' },
];

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const [showPassword, setShowPassword] = useState(false);
    const [mounted, setMounted] = useState(false);

    useEffect(() => {
        const t = setTimeout(() => setMounted(true), 30);
        return () => clearTimeout(t);
    }, []);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <>
            <Head title="Iniciar sesión — ventoryPOS" />
            <RouterLoadingOverlay />

            <style>{`
                @keyframes blob {
                    0%, 100% { transform: translate(0,0) scale(1); }
                    33% { transform: translate(30px,-20px) scale(1.05); }
                    66% { transform: translate(-20px,20px) scale(0.95); }
                }
                @keyframes pulse-ring {
                    0% { transform: scale(0.95); opacity: 0.6; }
                    100% { transform: scale(1.6); opacity: 0; }
                }
                @keyframes fade-up {
                    from { opacity: 0; transform: translateY(16px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .anim-fade { animation: fade-up 700ms ease-out both; }
            `}</style>

            <div className="flex min-h-screen" style={{ backgroundColor: palette.bg }}>
                {/* LEFT — Brand panel (hidden on mobile) */}
                <aside
                    className="relative hidden overflow-hidden lg:flex lg:w-1/2 lg:flex-col"
                    style={{
                        background: `linear-gradient(135deg, ${palette.sidebarBg} 0%, ${palette.primaryDark} 60%, ${palette.primary} 100%)`,
                    }}
                >
                    {/* Decorative blobs */}
                    <div aria-hidden="true" className="pointer-events-none absolute inset-0">
                        <div
                            className="absolute -left-24 top-1/4 h-96 w-96 rounded-full opacity-30 blur-3xl"
                            style={{
                                background: `radial-gradient(circle, ${palette.primary} 0%, transparent 70%)`,
                                animation: 'blob 18s ease-in-out infinite',
                            }}
                        />
                        <div
                            className="absolute right-0 bottom-0 h-96 w-96 rounded-full opacity-25 blur-3xl"
                            style={{
                                background: `radial-gradient(circle, ${palette.success} 0%, transparent 70%)`,
                                animation: 'blob 22s ease-in-out infinite reverse',
                            }}
                        />
                        <div
                            className="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 h-2 w-2 rounded-full"
                            style={{
                                background: 'white',
                                boxShadow: `0 0 0 0 ${palette.primary}`,
                                animation: 'pulse-ring 3s ease-out infinite',
                            }}
                        />
                    </div>

                    {/* Top: logo */}
                    <div className="relative z-10 flex items-center gap-2.5 px-12 pt-10">
                        <img src="/logo-full-white.svg" alt="ventoryPOS" className="h-10 w-auto" draggable={false} />
                        <span className="text-xl font-bold tracking-tight text-white">ventoryPOS</span>
                    </div>

                    {/* Middle: pitch */}
                    <div className="relative z-10 flex flex-1 flex-col justify-center px-12">
                        <div className="anim-fade">
                            <div className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-medium text-white/90 backdrop-blur-sm">
                                <Sparkles size={14} className="text-yellow-300" />
                                Punto de venta moderno
                            </div>
                            <h2 className="mt-6 text-4xl font-extrabold leading-tight text-white">
                                Vende más rápido,
                                <br />
                                <span className="text-transparent bg-clip-text bg-gradient-to-r from-cyan-200 via-white to-emerald-200">
                                    decide mejor.
                                </span>
                            </h2>
                            <p className="mt-4 max-w-md text-base text-white/80">
                                Para veterinarias, farmacias, retail y todo comercio que quiere crecer con
                                un sistema serio y simple a la vez.
                            </p>
                        </div>

                        <div className="anim-fade mt-10 grid grid-cols-2 gap-3" style={{ animationDelay: '120ms' }}>
                            {highlights.map((h, i) => {
                                const Icon = h.icon;
                                return (
                                    <div
                                        key={i}
                                        className="group flex items-center gap-3 rounded-xl border border-white/15 bg-white/5 p-3 backdrop-blur-sm transition-all duration-300 hover:bg-white/10 hover:border-white/30"
                                    >
                                        <div className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-white/15 text-white transition-transform duration-300 group-hover:scale-110">
                                            <Icon size={18} />
                                        </div>
                                        <div className="min-w-0">
                                            <p className="text-sm font-semibold text-white">{h.label}</p>
                                            <p className="truncate text-xs text-white/60">{h.desc}</p>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Bottom: footer */}
                    <div className="relative z-10 px-12 pb-10 text-xs text-white/60">
                        © {new Date().getFullYear()} ventoryPOS · MacSoft
                    </div>
                </aside>

                {/* RIGHT — Form panel */}
                <main className="flex w-full flex-col lg:w-1/2">
                    {/* Mobile top bar with logo */}
                    <div
                        className="flex items-center justify-between border-b px-5 py-4 lg:hidden"
                        style={{ borderColor: palette.border, backgroundColor: palette.surface }}
                    >
                        <Link href="/" className="flex items-center gap-2">
                            <img src="/logo-full.svg" alt="ventoryPOS" className="h-8 w-auto" draggable={false} />
                            <span className="text-base font-bold" style={{ color: palette.text }}>
                                ventoryPOS
                            </span>
                        </Link>
                        <Link
                            href="/"
                            className="text-xs font-medium"
                            style={{ color: palette.textMuted }}
                        >
                            ← Inicio
                        </Link>
                    </div>

                    <div className="flex flex-1 items-center justify-center px-5 py-8 sm:px-8 lg:px-12">
                        <div
                            className={`w-full max-w-md transition-all duration-700 ease-out ${
                                mounted ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'
                            }`}
                        >
                            {/* Header */}
                            <div className="mb-8 text-center lg:text-left">
                                <h1 className="text-3xl font-extrabold tracking-tight" style={{ color: palette.text }}>
                                    Bienvenido de vuelta 👋
                                </h1>
                                <p className="mt-2 text-sm" style={{ color: palette.textMuted }}>
                                    Ingresa tus credenciales para acceder a tu punto de venta.
                                </p>
                            </div>

                            {/* Status banner (e.g. password reset) */}
                            {status && (
                                <div
                                    className="mb-5 flex items-start gap-2.5 rounded-xl border p-3.5 text-sm"
                                    style={{
                                        borderColor: `${palette.success}40`,
                                        backgroundColor: `${palette.success}10`,
                                        color: palette.success,
                                    }}
                                >
                                    <CheckCircle2 size={18} className="mt-0.5 flex-shrink-0" />
                                    <p className="font-medium">{status}</p>
                                </div>
                            )}

                            <form onSubmit={submit} className="space-y-4" noValidate>
                                {/* Email */}
                                <div>
                                    <label
                                        htmlFor="email"
                                        className="mb-1.5 block text-sm font-medium"
                                        style={{ color: palette.text }}
                                    >
                                        Correo electrónico
                                    </label>
                                    <div className="relative">
                                        <Mail
                                            size={18}
                                            className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2"
                                            style={{ color: errors.email ? palette.danger : palette.textMuted }}
                                        />
                                        <input
                                            id="email"
                                            type="email"
                                            name="email"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                            autoComplete="username"
                                            autoFocus
                                            placeholder="tu@empresa.com"
                                            className="block w-full rounded-xl border bg-white py-2.5 pl-10 pr-3 text-sm shadow-sm outline-none transition-all duration-200 placeholder:text-slate-400 focus:ring-2"
                                            style={{
                                                borderColor: errors.email ? palette.danger : palette.border,
                                                color: palette.text,
                                                // @ts-expect-error CSS var for focus ring
                                                '--tw-ring-color': errors.email ? `${palette.danger}40` : `${palette.primary}40`,
                                            }}
                                        />
                                    </div>
                                    {errors.email && (
                                        <p
                                            className="mt-1.5 flex items-center gap-1 text-xs font-medium"
                                            style={{ color: palette.danger }}
                                        >
                                            <AlertCircle size={13} />
                                            {errors.email}
                                        </p>
                                    )}
                                </div>

                                {/* Password */}
                                <div>
                                    <div className="mb-1.5 flex items-center justify-between">
                                        <label
                                            htmlFor="password"
                                            className="text-sm font-medium"
                                            style={{ color: palette.text }}
                                        >
                                            Contraseña
                                        </label>
                                        {canResetPassword && (
                                            <Link
                                                href={route('password.request')}
                                                className="text-xs font-medium transition-colors duration-150 hover:underline"
                                                style={{ color: palette.primary }}
                                            >
                                                ¿Olvidaste tu contraseña?
                                            </Link>
                                        )}
                                    </div>
                                    <div className="relative">
                                        <Lock
                                            size={18}
                                            className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2"
                                            style={{ color: errors.password ? palette.danger : palette.textMuted }}
                                        />
                                        <input
                                            id="password"
                                            type={showPassword ? 'text' : 'password'}
                                            name="password"
                                            value={data.password}
                                            onChange={(e) => setData('password', e.target.value)}
                                            autoComplete="current-password"
                                            placeholder="••••••••"
                                            className="block w-full rounded-xl border bg-white py-2.5 pl-10 pr-11 text-sm shadow-sm outline-none transition-all duration-200 placeholder:text-slate-400 focus:ring-2"
                                            style={{
                                                borderColor: errors.password ? palette.danger : palette.border,
                                                color: palette.text,
                                                // @ts-expect-error CSS var for focus ring
                                                '--tw-ring-color': errors.password ? `${palette.danger}40` : `${palette.primary}40`,
                                            }}
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowPassword((s) => !s)}
                                            aria-label={showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                                            className="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg p-1.5 transition-colors duration-150 hover:bg-slate-100"
                                            style={{ color: palette.textMuted }}
                                        >
                                            {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                                        </button>
                                    </div>
                                    {errors.password && (
                                        <p
                                            className="mt-1.5 flex items-center gap-1 text-xs font-medium"
                                            style={{ color: palette.danger }}
                                        >
                                            <AlertCircle size={13} />
                                            {errors.password}
                                        </p>
                                    )}
                                </div>

                                {/* Remember */}
                                <label className="flex cursor-pointer select-none items-center gap-2.5 pt-1">
                                    <span className="relative inline-flex">
                                        <input
                                            type="checkbox"
                                            name="remember"
                                            checked={data.remember}
                                            onChange={(e) => setData('remember', (e.target.checked || false) as false)}
                                            className="peer h-4 w-4 cursor-pointer appearance-none rounded border bg-white transition-colors duration-200 checked:bg-[var(--cb)] checked:border-[var(--cb)]"
                                            style={{
                                                borderColor: palette.border,
                                                // @ts-expect-error custom var
                                                '--cb': palette.primary,
                                            }}
                                        />
                                        <svg
                                            className="pointer-events-none absolute left-0 top-0 h-4 w-4 stroke-white opacity-0 transition-opacity duration-150 peer-checked:opacity-100"
                                            viewBox="0 0 16 16"
                                            fill="none"
                                            strokeWidth="2.5"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <polyline points="3.5 8.5 6.5 11.5 12.5 5" />
                                        </svg>
                                    </span>
                                    <span className="text-sm" style={{ color: palette.textMuted }}>
                                        Recordarme en este equipo
                                    </span>
                                </label>

                                {/* Submit */}
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="group relative mt-2 inline-flex w-full items-center justify-center gap-2 overflow-hidden rounded-xl px-5 py-3 text-sm font-semibold text-white shadow-lg transition-all duration-200 hover:shadow-xl active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-70"
                                    style={{ backgroundColor: palette.primary }}
                                >
                                    <span
                                        className="absolute inset-0 opacity-0 transition-opacity duration-200 group-hover:opacity-100"
                                        style={{
                                            background: `linear-gradient(135deg, ${palette.primary} 0%, ${palette.primaryHover} 100%)`,
                                        }}
                                    />
                                    <span className="relative inline-flex items-center gap-2">
                                        {processing ? (
                                            <>
                                                <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="3" />
                                                    <path
                                                        className="opacity-75"
                                                        fill="currentColor"
                                                        d="M4 12a8 8 0 0 1 8-8v3a5 5 0 0 0-5 5H4z"
                                                    />
                                                </svg>
                                                Ingresando...
                                            </>
                                        ) : (
                                            <>
                                                Ingresar
                                                <ArrowRight
                                                    size={16}
                                                    className="transition-transform duration-200 group-hover:translate-x-1"
                                                />
                                            </>
                                        )}
                                    </span>
                                </button>
                            </form>

                            {/* Footer / Register */}
                            <p className="mt-6 text-center text-sm" style={{ color: palette.textMuted }}>
                                ¿Aún no tienes cuenta?{' '}
                                <Link
                                    href={route('register')}
                                    className="font-semibold transition-colors duration-150 hover:underline"
                                    style={{ color: palette.primary }}
                                >
                                    Crear cuenta gratis
                                </Link>
                            </p>

                            <div
                                className="mt-8 flex items-center justify-center gap-1.5 text-xs"
                                style={{ color: palette.textMuted }}
                            >
                                <ShieldCheck size={13} style={{ color: palette.success }} />
                                Conexión segura · Tus datos están cifrados
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
