import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    BadgeCheck,
    BarChart3,
    Boxes,
    Cat,
    Coffee,
    CreditCard,
    Heart,
    Pill,
    Receipt,
    ShieldCheck,
    ShoppingBag,
    Smartphone,
    Sparkles,
    Store,
    Tag,
    Users,
    Wifi,
    Zap,
} from 'lucide-react';
import { useEffect, useState } from 'react';

interface Industry {
    icon: typeof Cat;
    label: string;
    desc: string;
}

interface Feature {
    icon: typeof BarChart3;
    title: string;
    desc: string;
    accent: string;
}

const industries: Industry[] = [
    { icon: Cat, label: 'Veterinarias', desc: 'Productos, servicios y fichas clínicas' },
    { icon: Pill, label: 'Farmacias', desc: 'Lotes, vencimientos y recetas' },
    { icon: ShoppingBag, label: 'Retail', desc: 'Tiendas, boutiques y franquicias' },
    { icon: Store, label: 'Bodegas', desc: 'Minimarkets y tiendas de barrio' },
    { icon: Coffee, label: 'Cafés & Restobar', desc: 'Mesas, combos y comandas' },
    { icon: Tag, label: 'Mini-markets', desc: 'Códigos de barra y rotación' },
];

const features: Feature[] = [
    {
        icon: Zap,
        title: 'POS súper rápido',
        desc: 'Vende en segundos con atajos de teclado, lector de códigos y multi-pago.',
        accent: 'var(--color-primary)',
    },
    {
        icon: Boxes,
        title: 'Inventario inteligente',
        desc: 'Stock por local, unidades de medida múltiples y alertas de bajo stock.',
        accent: 'var(--color-success)',
    },
    {
        icon: BarChart3,
        title: 'Reportes en tiempo real',
        desc: 'Ventas, márgenes, productos top y rendimiento por cajero.',
        accent: 'var(--color-warning)',
    },
    {
        icon: CreditCard,
        title: 'Múltiples métodos de pago',
        desc: 'Efectivo, tarjetas, Yape, Plin, transferencias y vuelto automático.',
        accent: 'var(--color-secondary)',
    },
    {
        icon: Users,
        title: 'Clientes & fidelización',
        desc: 'Historial de compras, descuentos por concepto y aprobaciones.',
        accent: 'var(--color-danger)',
    },
    {
        icon: ShieldCheck,
        title: 'Auditoría completa',
        desc: 'Cierre de caja, arqueo, anulaciones con motivo y trazabilidad total.',
        accent: 'var(--color-primary)',
    },
];

const palette = {
    primary: '#3b82f6',
    primaryHover: '#2563eb',
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

export default function Welcome({ auth }: PageProps<{ laravelVersion?: string; phpVersion?: string }>) {
    const [mounted, setMounted] = useState(false);

    useEffect(() => {
        const t = setTimeout(() => setMounted(true), 50);
        return () => clearTimeout(t);
    }, []);

    return (
        <>
            <Head title="ventoryPOS — Punto de venta para retail, farmacias y veterinarias" />

            <div className="relative min-h-screen overflow-x-hidden" style={{ backgroundColor: palette.bg, color: palette.text }}>
                {/* Decorative animated blobs */}
                <div aria-hidden="true" className="pointer-events-none absolute inset-0 overflow-hidden">
                    <div
                        className="absolute -left-32 -top-32 h-[480px] w-[480px] rounded-full opacity-30 blur-3xl"
                        style={{
                            background: `radial-gradient(circle, ${palette.primary} 0%, transparent 70%)`,
                            animation: 'blob 18s ease-in-out infinite',
                        }}
                    />
                    <div
                        className="absolute -right-32 top-40 h-[420px] w-[420px] rounded-full opacity-25 blur-3xl"
                        style={{
                            background: `radial-gradient(circle, ${palette.success} 0%, transparent 70%)`,
                            animation: 'blob 22s ease-in-out infinite reverse',
                        }}
                    />
                    <div
                        className="absolute left-1/3 bottom-0 h-[360px] w-[360px] rounded-full opacity-20 blur-3xl"
                        style={{
                            background: `radial-gradient(circle, ${palette.warning} 0%, transparent 70%)`,
                            animation: 'blob 26s ease-in-out infinite',
                        }}
                    />
                </div>

                <style>{`
                    @keyframes blob {
                        0%, 100% { transform: translate(0, 0) scale(1); }
                        33% { transform: translate(40px, -30px) scale(1.05); }
                        66% { transform: translate(-30px, 30px) scale(0.95); }
                    }
                    @keyframes float {
                        0%, 100% { transform: translateY(0); }
                        50% { transform: translateY(-8px); }
                    }
                    @keyframes shimmer {
                        0% { background-position: -200% 0; }
                        100% { background-position: 200% 0; }
                    }
                    .float-slow { animation: float 4s ease-in-out infinite; }
                    .shimmer-text {
                        background: linear-gradient(90deg, ${palette.text} 0%, ${palette.primary} 50%, ${palette.text} 100%);
                        background-size: 200% auto;
                        background-clip: text;
                        -webkit-background-clip: text;
                        -webkit-text-fill-color: transparent;
                        animation: shimmer 4s linear infinite;
                    }
                    .fade-up {
                        opacity: 0;
                        transform: translateY(20px);
                        transition: opacity 700ms ease-out, transform 700ms ease-out;
                    }
                    .fade-up.in {
                        opacity: 1;
                        transform: translateY(0);
                    }
                `}</style>

                {/* Nav */}
                <header className="relative z-10 mx-auto flex max-w-7xl items-center justify-between px-4 py-5 sm:px-6 lg:px-8">
                    <Link href="/" className="flex items-center gap-2.5 select-none">
                        <img src="/logo-full.svg" alt="ventoryPOS" className="h-9 w-auto" draggable={false} />
                        <span className="text-lg font-bold tracking-tight" style={{ color: palette.text }}>
                            ventoryPOS
                        </span>
                    </Link>

                    <nav className="flex items-center gap-2 sm:gap-3">
                        {auth?.user ? (
                            <Link
                                href={route('dashboard')}
                                className="group inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-lg transition-all duration-200 hover:shadow-xl hover:scale-[1.02] active:scale-[0.98]"
                                style={{ backgroundColor: palette.primary }}
                            >
                                Ir al Dashboard
                                <ArrowRight size={16} className="transition-transform duration-200 group-hover:translate-x-1" />
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={route('login')}
                                    className="rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-200 hover:bg-slate-200/60"
                                    style={{ color: palette.text }}
                                >
                                    Iniciar sesión
                                </Link>
                                <Link
                                    href={route('register')}
                                    className="group inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-lg transition-all duration-200 hover:shadow-xl hover:scale-[1.02] active:scale-[0.98]"
                                    style={{ backgroundColor: palette.primary }}
                                >
                                    Empezar gratis
                                    <ArrowRight size={14} className="transition-transform duration-200 group-hover:translate-x-1" />
                                </Link>
                            </>
                        )}
                    </nav>
                </header>

                {/* Hero */}
                <section className="relative z-10 mx-auto max-w-7xl px-4 pb-16 pt-10 sm:px-6 sm:pt-16 lg:px-8">
                    <div className={`fade-up ${mounted ? 'in' : ''}`}>
                        <div className="mx-auto max-w-3xl text-center">
                            <div
                                className="mx-auto inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium"
                                style={{
                                    borderColor: palette.border,
                                    backgroundColor: palette.surface,
                                    color: palette.textMuted,
                                }}
                            >
                                <Sparkles size={14} style={{ color: palette.warning }} />
                                <span>Hecho en Perú para negocios que crecen</span>
                            </div>

                            <h1 className="mt-6 text-4xl font-extrabold leading-[1.05] tracking-tight sm:text-5xl lg:text-6xl">
                                El punto de venta que tu negocio
                                <br />
                                <span className="shimmer-text">realmente necesita.</span>
                            </h1>

                            <p
                                className="mx-auto mt-6 max-w-2xl text-base sm:text-lg"
                                style={{ color: palette.textMuted }}
                            >
                                Diseñado para <strong style={{ color: palette.text }}>veterinarias</strong>,{' '}
                                <strong style={{ color: palette.text }}>farmacias</strong>,{' '}
                                <strong style={{ color: palette.text }}>retail</strong> y todo tipo de comercio.
                                Vende más rápido, controla tu inventario y entiende tu negocio en tiempo real.
                            </p>

                            <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                                <Link
                                    href={auth?.user ? route('dashboard') : route('register')}
                                    className="group inline-flex w-full items-center justify-center gap-2 rounded-xl px-6 py-3 text-base font-semibold text-white shadow-xl transition-all duration-200 hover:shadow-2xl hover:scale-[1.02] active:scale-[0.98] sm:w-auto"
                                    style={{ backgroundColor: palette.primary }}
                                >
                                    {auth?.user ? 'Ir al Dashboard' : 'Empezar ahora'}
                                    <ArrowRight size={18} className="transition-transform duration-200 group-hover:translate-x-1" />
                                </Link>
                                <Link
                                    href={route('login')}
                                    className="inline-flex w-full items-center justify-center gap-2 rounded-xl border px-6 py-3 text-base font-semibold transition-all duration-200 hover:scale-[1.02] active:scale-[0.98] sm:w-auto"
                                    style={{
                                        borderColor: palette.border,
                                        backgroundColor: palette.surface,
                                        color: palette.text,
                                    }}
                                >
                                    Ya tengo cuenta
                                </Link>
                            </div>

                            <div className="mt-8 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-xs" style={{ color: palette.textMuted }}>
                                <span className="inline-flex items-center gap-1.5">
                                    <BadgeCheck size={14} style={{ color: palette.success }} />
                                    Sin tarjeta de crédito
                                </span>
                                <span className="inline-flex items-center gap-1.5">
                                    <Wifi size={14} style={{ color: palette.success }} />
                                    Funciona sin internet
                                </span>
                                <span className="inline-flex items-center gap-1.5">
                                    <Smartphone size={14} style={{ color: palette.success }} />
                                    Web, PWA y caja física
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Mock POS Receipt — floating card */}
                    <div className={`fade-up ${mounted ? 'in' : ''} mt-16`} style={{ transitionDelay: '200ms' }}>
                        <div className="relative mx-auto max-w-4xl">
                            <div
                                className="rounded-2xl border p-6 shadow-2xl float-slow"
                                style={{
                                    backgroundColor: palette.surface,
                                    borderColor: palette.border,
                                }}
                            >
                                <div className="flex flex-wrap items-center justify-between gap-3 border-b pb-4" style={{ borderColor: palette.border }}>
                                    <div className="flex items-center gap-2.5">
                                        <div
                                            className="flex h-9 w-9 items-center justify-center rounded-lg text-white"
                                            style={{ backgroundColor: palette.primary }}
                                        >
                                            <Receipt size={18} />
                                        </div>
                                        <div>
                                            <p className="text-sm font-semibold">Venta #00342</p>
                                            <p className="text-xs" style={{ color: palette.textMuted }}>Hoy · 14:32</p>
                                        </div>
                                    </div>
                                    <span
                                        className="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                                        style={{ backgroundColor: `${palette.success}15`, color: palette.success }}
                                    >
                                        <span className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: palette.success }} />
                                        Completada
                                    </span>
                                </div>

                                <div className="mt-4 grid gap-3 sm:grid-cols-3">
                                    {[
                                        { label: 'Royal Canin Adult 3kg', qty: '1', amount: 'S/ 89.90' },
                                        { label: 'Antipulgas Frontline', qty: '2', amount: 'S/ 71.80' },
                                        { label: 'Consulta veterinaria', qty: '1', amount: 'S/ 50.00' },
                                    ].map((item, i) => (
                                        <div
                                            key={i}
                                            className="rounded-lg border p-3"
                                            style={{ borderColor: palette.border, backgroundColor: palette.bg }}
                                        >
                                            <p className="truncate text-xs" style={{ color: palette.textMuted }}>
                                                {item.qty}× · {item.label}
                                            </p>
                                            <p className="mt-1 text-sm font-semibold">{item.amount}</p>
                                        </div>
                                    ))}
                                </div>

                                <div className="mt-4 flex items-center justify-between border-t pt-4" style={{ borderColor: palette.border }}>
                                    <div className="text-xs" style={{ color: palette.textMuted }}>
                                        Pagado con Yape · vuelto S/ 0.00
                                    </div>
                                    <div className="text-right">
                                        <p className="text-xs" style={{ color: palette.textMuted }}>Total</p>
                                        <p className="text-xl font-bold" style={{ color: palette.text }}>S/ 211.70</p>
                                    </div>
                                </div>
                            </div>

                            {/* floating stat chips */}
                            <div
                                className="absolute -left-4 -top-6 hidden rounded-xl border p-3 shadow-xl sm:flex sm:items-center sm:gap-2"
                                style={{ backgroundColor: palette.surface, borderColor: palette.border, animation: 'float 5s ease-in-out infinite' }}
                            >
                                <div
                                    className="flex h-9 w-9 items-center justify-center rounded-lg text-white"
                                    style={{ backgroundColor: palette.success }}
                                >
                                    <BarChart3 size={18} />
                                </div>
                                <div>
                                    <p className="text-xs" style={{ color: palette.textMuted }}>Ventas hoy</p>
                                    <p className="text-sm font-bold">+ 18.4%</p>
                                </div>
                            </div>

                            <div
                                className="absolute -bottom-6 -right-2 hidden rounded-xl border p-3 shadow-xl sm:flex sm:items-center sm:gap-2"
                                style={{ backgroundColor: palette.surface, borderColor: palette.border, animation: 'float 6s ease-in-out infinite reverse' }}
                            >
                                <div
                                    className="flex h-9 w-9 items-center justify-center rounded-lg text-white"
                                    style={{ backgroundColor: palette.warning }}
                                >
                                    <Boxes size={18} />
                                </div>
                                <div>
                                    <p className="text-xs" style={{ color: palette.textMuted }}>Stock bajo</p>
                                    <p className="text-sm font-bold">3 productos</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Industries */}
                <section className="relative z-10 mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                    <div className="text-center">
                        <p className="text-xs font-semibold uppercase tracking-widest" style={{ color: palette.primary }}>
                            Hecho a tu medida
                        </p>
                        <h2 className="mt-2 text-2xl font-bold sm:text-3xl">Un POS para cada tipo de negocio</h2>
                    </div>

                    <div className="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                        {industries.map((ind, i) => {
                            const Icon = ind.icon;
                            return (
                                <div
                                    key={i}
                                    className="group rounded-xl border p-4 transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
                                    style={{ borderColor: palette.border, backgroundColor: palette.surface }}
                                >
                                    <div
                                        className="flex h-11 w-11 items-center justify-center rounded-lg transition-transform duration-300 group-hover:scale-110"
                                        style={{ backgroundColor: `${palette.primary}15`, color: palette.primary }}
                                    >
                                        <Icon size={22} />
                                    </div>
                                    <p className="mt-3 text-sm font-semibold">{ind.label}</p>
                                    <p className="mt-0.5 text-xs leading-relaxed" style={{ color: palette.textMuted }}>
                                        {ind.desc}
                                    </p>
                                </div>
                            );
                        })}
                    </div>
                </section>

                {/* Features */}
                <section className="relative z-10 mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                    <div className="text-center">
                        <p className="text-xs font-semibold uppercase tracking-widest" style={{ color: palette.primary }}>
                            Funcionalidades
                        </p>
                        <h2 className="mt-2 text-2xl font-bold sm:text-3xl">Todo lo que necesitas para vender mejor</h2>
                        <p className="mx-auto mt-3 max-w-xl text-sm" style={{ color: palette.textMuted }}>
                            Diseñado por gente que ha estado en una caja registradora. Sin pantallas confusas, sin clics de más.
                        </p>
                    </div>

                    <div className="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        {features.map((f, i) => {
                            const Icon = f.icon;
                            return (
                                <div
                                    key={i}
                                    className="group relative overflow-hidden rounded-2xl border p-6 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl"
                                    style={{ borderColor: palette.border, backgroundColor: palette.surface }}
                                >
                                    <div
                                        className="absolute inset-0 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                                        style={{
                                            background: `radial-gradient(600px circle at 0% 0%, ${f.accent}10, transparent 40%)`,
                                        }}
                                    />
                                    <div
                                        className="relative flex h-12 w-12 items-center justify-center rounded-xl transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3"
                                        style={{ backgroundColor: `${f.accent}15`, color: f.accent }}
                                    >
                                        <Icon size={22} />
                                    </div>
                                    <h3 className="relative mt-4 text-base font-semibold">{f.title}</h3>
                                    <p className="relative mt-1.5 text-sm leading-relaxed" style={{ color: palette.textMuted }}>
                                        {f.desc}
                                    </p>
                                </div>
                            );
                        })}
                    </div>
                </section>

                {/* CTA */}
                <section className="relative z-10 mx-auto max-w-7xl px-4 pb-20 sm:px-6 lg:px-8">
                    <div
                        className="relative overflow-hidden rounded-3xl px-6 py-12 text-center shadow-2xl sm:px-12 sm:py-16"
                        style={{
                            background: `linear-gradient(135deg, ${palette.primary} 0%, ${palette.primaryHover} 100%)`,
                        }}
                    >
                        <div className="pointer-events-none absolute inset-0 opacity-20">
                            <div className="absolute -left-10 -top-10 h-40 w-40 rounded-full bg-white blur-3xl" />
                            <div className="absolute -right-10 bottom-0 h-40 w-40 rounded-full bg-white blur-3xl" />
                        </div>

                        <div className="relative">
                            <Heart size={32} className="mx-auto text-white/80" />
                            <h2 className="mt-4 text-3xl font-extrabold text-white sm:text-4xl">
                                Listo para llevar tu negocio al siguiente nivel
                            </h2>
                            <p className="mx-auto mt-3 max-w-xl text-white/85">
                                Empieza hoy mismo. Configuración en minutos, no en días.
                            </p>
                            <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                                <Link
                                    href={auth?.user ? route('dashboard') : route('register')}
                                    className="group inline-flex w-full items-center justify-center gap-2 rounded-xl bg-white px-7 py-3.5 text-base font-bold shadow-xl transition-all duration-200 hover:scale-[1.03] active:scale-[0.98] sm:w-auto"
                                    style={{ color: palette.primary }}
                                >
                                    {auth?.user ? 'Ir al Dashboard' : 'Crear cuenta gratis'}
                                    <ArrowRight size={18} className="transition-transform duration-200 group-hover:translate-x-1" />
                                </Link>
                                <Link
                                    href={route('login')}
                                    className="inline-flex w-full items-center justify-center gap-2 rounded-xl border-2 border-white/30 px-7 py-3.5 text-base font-semibold text-white transition-all duration-200 hover:bg-white/10 sm:w-auto"
                                >
                                    Iniciar sesión
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Footer */}
                <footer className="relative z-10 border-t" style={{ borderColor: palette.border }}>
                    <div className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 py-8 text-xs sm:flex-row sm:px-6 lg:px-8" style={{ color: palette.textMuted }}>
                        <div className="flex items-center gap-2">
                            <img src="/logo-full.svg" alt="ventoryPOS" className="h-6 w-auto opacity-80" draggable={false} />
                            <span>© {new Date().getFullYear()} ventoryPOS · MacSoft</span>
                        </div>
                        <div className="flex items-center gap-1.5">
                            <span>Hecho con</span>
                            <Heart size={12} style={{ color: palette.danger }} fill={palette.danger} />
                            <span>en Perú</span>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
