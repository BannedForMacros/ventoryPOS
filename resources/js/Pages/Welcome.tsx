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
    MessageCircle,
    Pill,
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
import { ReactNode, useEffect, useRef, useState } from 'react';

// Canal para pedir la demo. Cuando exista el número comercial de WhatsApp,
// cámbialo por `https://wa.me/51XXXXXXXXX?text=...` y el resto de la página
// no se toca: todos los CTA apuntan aquí.
const DEMO_URL =
    'mailto:cam27mac@gmail.com?subject=Quiero%20mi%20demo%20gratis%20de%20ventoryPOS&body=Hola%2C%20quiero%20una%20demo%20gratis%20de%20ventoryPOS%20para%20mi%20negocio.';

const palette = {
    primary: '#2563eb',
    primarySoft: '#3b82f6',
    success: '#10b981',
    warning: '#f59e0b',
    danger: '#ef4444',
    secondary: '#8b5cf6',
    bg: '#f8fafc',
    surface: '#ffffff',
    border: '#e2e8f0',
    text: '#0f172a',
    textMuted: '#64748b',
};

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
    { icon: Tag, label: 'Ferreterías', desc: 'Unidades múltiples y crédito' },
];

const features: Feature[] = [
    {
        icon: Zap,
        title: 'POS súper rápido',
        desc: 'Vende en segundos con atajos de teclado, lector de códigos y multi-pago.',
        accent: palette.primary,
    },
    {
        icon: Boxes,
        title: 'Inventario inteligente',
        desc: 'Stock por local, kardex trazable, unidades múltiples y alertas de bajo stock.',
        accent: palette.success,
    },
    {
        icon: BarChart3,
        title: 'Reportes en tiempo real',
        desc: 'Ventas, márgenes, utilidad, productos top y rendimiento por cajero.',
        accent: palette.warning,
    },
    {
        icon: CreditCard,
        title: 'Todos los métodos de pago',
        desc: 'Efectivo, tarjetas, Yape, Plin, transferencias y vuelto automático.',
        accent: palette.secondary,
    },
    {
        icon: Users,
        title: 'Clientes & crédito',
        desc: 'Historial de compras, cuentas por cobrar, anticipos y estado de cuenta.',
        accent: palette.danger,
    },
    {
        icon: ShieldCheck,
        title: 'Auditoría completa',
        desc: 'Cierre de caja, arqueo, balance diario y trazabilidad de cada movimiento.',
        accent: palette.primary,
    },
];

const stats = [
    { value: 3, suffix: ' seg', label: 'para cerrar una venta' },
    { value: 100, suffix: '%', label: 'de tu caja cuadrada, siempre' },
    { value: 24, suffix: '/7', label: 'tu negocio en tu bolsillo' },
];

/* ------------------------------------------------------------------ */
/* Animación: reveal on scroll (IntersectionObserver, una sola vez)    */
/* ------------------------------------------------------------------ */

function Reveal({
    children,
    delay = 0,
    className = '',
}: {
    children: ReactNode;
    delay?: number;
    className?: string;
}) {
    const ref = useRef<HTMLDivElement>(null);
    const [vis, setVis] = useState(false);

    useEffect(() => {
        const el = ref.current;
        if (!el) return;
        const obs = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setVis(true);
                    obs.disconnect();
                }
            },
            { threshold: 0.15, rootMargin: '0px 0px -40px 0px' },
        );
        obs.observe(el);
        return () => obs.disconnect();
    }, []);

    return (
        <div ref={ref} className={`reveal ${vis ? 'in' : ''} ${className}`} style={{ transitionDelay: `${delay}ms` }}>
            {children}
        </div>
    );
}

/* Contador que anima al entrar en pantalla (ease-out, ~1s) */
function Counter({ to, suffix = '' }: { to: number; suffix?: string }) {
    const ref = useRef<HTMLSpanElement>(null);
    const [val, setVal] = useState(0);

    useEffect(() => {
        const el = ref.current;
        if (!el) return;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            setVal(to);
            return;
        }
        let raf = 0;
        const obs = new IntersectionObserver(
            ([entry]) => {
                if (!entry.isIntersecting) return;
                obs.disconnect();
                const start = performance.now();
                const dur = 1100;
                const tick = (now: number) => {
                    const t = Math.min((now - start) / dur, 1);
                    const eased = 1 - Math.pow(1 - t, 3);
                    setVal(Math.round(to * eased));
                    if (t < 1) raf = requestAnimationFrame(tick);
                };
                raf = requestAnimationFrame(tick);
            },
            { threshold: 0.5 },
        );
        obs.observe(el);
        return () => {
            obs.disconnect();
            cancelAnimationFrame(raf);
        };
    }, [to]);

    return (
        <span ref={ref}>
            {val}
            {suffix}
        </span>
    );
}

// `canRegister` sale de `Route::has('register')` (routes/web.php). El registro
// público está desactivado —las cuentas las crea el proveedor—, así que llega
// `false` y los CTA de alta no se pintan. El camino comercial del visitante
// anónimo es pedir la demo (DEMO_URL). Si algún día se reactiva la ruta,
// los botones de alta vuelven solos: no hay que tocar esta página.
export default function Welcome({
    auth,
    canRegister = false,
}: PageProps<{ laravelVersion?: string; phpVersion?: string; canRegister?: boolean }>) {
    const [mounted, setMounted] = useState(false);
    const [scrolled, setScrolled] = useState(false);
    const heroLogoRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const t = setTimeout(() => setMounted(true), 50);
        return () => clearTimeout(t);
    }, []);

    // Parallax del logo al hacer scroll, estilo Apple: se aleja, encoge y
    // desvanece suavemente. Todo por rAF sobre transform/opacity (compositor),
    // y apagado si el usuario pide menos movimiento.
    useEffect(() => {
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let raf = 0;
        const onScroll = () => {
            setScrolled(window.scrollY > 8);
            if (reduced) return;
            cancelAnimationFrame(raf);
            raf = requestAnimationFrame(() => {
                const el = heroLogoRef.current;
                if (!el) return;
                const y = window.scrollY;
                const p = Math.min(y / 640, 1);
                el.style.transform = `translateY(${y * 0.22}px) scale(${1 - p * 0.14})`;
                el.style.opacity = String(1 - p * 0.6);
            });
        };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => {
            window.removeEventListener('scroll', onScroll);
            cancelAnimationFrame(raf);
        };
    }, []);

    return (
        <>
            <Head title="ventoryPOS — El punto de venta que tu negocio merece" />

            <div className="relative min-h-screen overflow-x-hidden" style={{ backgroundColor: palette.bg, color: palette.text }}>
                <style>{`
                    :root {
                        --ease-out-strong: cubic-bezier(0.23, 1, 0.32, 1);
                    }
                    .reveal {
                        opacity: 0;
                        transform: translateY(28px) scale(0.99);
                        transition: opacity 800ms var(--ease-out-strong), transform 800ms var(--ease-out-strong);
                        will-change: opacity, transform;
                    }
                    .reveal.in {
                        opacity: 1;
                        transform: translateY(0) scale(1);
                    }
                    @keyframes blob {
                        0%, 100% { transform: translate(0, 0) scale(1); }
                        33% { transform: translate(40px, -30px) scale(1.05); }
                        66% { transform: translate(-30px, 30px) scale(0.95); }
                    }
                    @keyframes float {
                        0%, 100% { transform: translateY(0); }
                        50% { transform: translateY(-10px); }
                    }
                    @keyframes glow-pulse {
                        0%, 100% { box-shadow: 0 10px 40px -8px ${palette.primary}66; }
                        50% { box-shadow: 0 10px 56px -4px ${palette.primary}99; }
                    }
                    @keyframes marquee {
                        from { transform: translateX(0); }
                        to { transform: translateX(-50%); }
                    }
                    .float-slow { animation: float 5s ease-in-out infinite; }
                    .cta-demo { animation: glow-pulse 3s ease-in-out infinite; }
                    .marquee-track {
                        animation: marquee 36s linear infinite;
                    }
                    .marquee:hover .marquee-track { animation-play-state: paused; }
                    .shimmer-text {
                        background: linear-gradient(90deg, ${palette.primary} 0%, ${palette.success} 55%, ${palette.primary} 100%);
                        background-size: 200% auto;
                        background-clip: text;
                        -webkit-background-clip: text;
                        -webkit-text-fill-color: transparent;
                    }
                    .hero-logo-glow {
                        background:
                            radial-gradient(closest-side, ${palette.primary}2e, transparent 70%),
                            radial-gradient(closest-side, ${palette.success}1f, transparent 60%);
                    }
                    .safe-top { padding-top: max(0.75rem, env(safe-area-inset-top)); }
                    .safe-bottom { padding-bottom: max(2rem, env(safe-area-inset-bottom)); }
                    @media (prefers-reduced-motion: reduce) {
                        .float-slow, .cta-demo, .marquee-track, [style*="animation: blob"] {
                            animation: none !important;
                        }
                        .reveal { transition: none; opacity: 1; transform: none; }
                    }
                `}</style>

                {/* Blobs decorativos (ocultos en móvil para ahorrar GPU) */}
                <div aria-hidden="true" className="pointer-events-none absolute inset-0 hidden overflow-hidden sm:block">
                    <div
                        className="absolute -left-32 -top-32 h-[460px] w-[460px] rounded-full opacity-25 blur-3xl"
                        style={{
                            background: `radial-gradient(circle, ${palette.primarySoft} 0%, transparent 70%)`,
                            animation: 'blob 22s ease-in-out infinite',
                        }}
                    />
                    <div
                        className="absolute -right-32 top-48 h-[400px] w-[400px] rounded-full opacity-20 blur-3xl"
                        style={{
                            background: `radial-gradient(circle, ${palette.success} 0%, transparent 70%)`,
                            animation: 'blob 26s ease-in-out infinite reverse',
                        }}
                    />
                </div>

                {/* Nav — fija, con blur cuando hay scroll (estilo Apple) */}
                <header
                    className="safe-top fixed inset-x-0 top-0 z-50 transition-all duration-300"
                    style={{
                        backgroundColor: scrolled ? 'rgba(248, 250, 252, 0.82)' : 'transparent',
                        backdropFilter: scrolled ? 'blur(16px) saturate(1.4)' : 'none',
                        WebkitBackdropFilter: scrolled ? 'blur(16px) saturate(1.4)' : 'none',
                        borderBottom: `1px solid ${scrolled ? palette.border : 'transparent'}`,
                    }}
                >
                    <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
                        <Link href="/" className="flex items-center select-none">
                            <img src="/logo-full.svg" alt="ventoryPOS" className="h-8 w-auto sm:h-9" draggable={false} />
                        </Link>

                        <nav className="flex items-center gap-2 sm:gap-3">
                            {auth?.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="group inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold text-white shadow-lg transition-all duration-200 hover:shadow-xl hover:scale-[1.02] active:scale-[0.97]"
                                    style={{ backgroundColor: palette.primary }}
                                >
                                    Ir al Dashboard
                                    <ArrowRight size={16} className="transition-transform duration-200 group-hover:translate-x-1" />
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={route('login')}
                                        className="rounded-full px-3 py-2 text-sm font-medium transition-colors duration-200 hover:bg-slate-200/60"
                                    >
                                        Iniciar sesión
                                    </Link>
                                    <a
                                        href={DEMO_URL}
                                        className="hidden items-center gap-1.5 rounded-full px-4 py-2 text-sm font-semibold text-white shadow-lg transition-all duration-200 hover:shadow-xl hover:scale-[1.02] active:scale-[0.97] sm:inline-flex"
                                        style={{ backgroundColor: palette.primary }}
                                    >
                                        Pide tu demo gratis
                                        <ArrowRight size={14} />
                                    </a>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                {/* Hero */}
                <section className="relative z-10 mx-auto max-w-7xl px-4 pb-6 pt-24 sm:px-6 sm:pt-28 lg:px-8">
                    <div
                        className="mx-auto max-w-3xl text-center"
                        style={{
                            opacity: mounted ? 1 : 0,
                            transform: mounted ? 'none' : 'translateY(24px)',
                            transition: 'opacity 900ms var(--ease-out-strong), transform 900ms var(--ease-out-strong)',
                        }}
                    >
                        <div
                            className="mx-auto inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium"
                            style={{ borderColor: palette.border, backgroundColor: palette.surface, color: palette.textMuted }}
                        >
                            <Sparkles size={14} style={{ color: palette.warning }} />
                            <span>Hecho en Perú para negocios que crecen</span>
                        </div>

                        <h1 className="mt-6 text-4xl font-extrabold leading-[1.05] tracking-tight sm:text-6xl lg:text-7xl">
                            Vende más rápido.
                            <br />
                            <span className="shimmer-text">Controla todo.</span>
                        </h1>

                        <p className="mx-auto mt-6 max-w-2xl text-base sm:text-xl" style={{ color: palette.textMuted }}>
                            El punto de venta todo-en-uno para <strong style={{ color: palette.text }}>veterinarias</strong>,{' '}
                            <strong style={{ color: palette.text }}>farmacias</strong>,{' '}
                            <strong style={{ color: palette.text }}>ferreterías</strong> y retail. Caja, inventario, crédito y
                            reportes en un solo lugar.
                        </p>

                        <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                            {auth?.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="group inline-flex w-full items-center justify-center gap-2 rounded-full px-8 py-4 text-base font-bold text-white shadow-xl transition-all duration-200 hover:scale-[1.03] active:scale-[0.97] sm:w-auto"
                                    style={{ backgroundColor: palette.primary }}
                                >
                                    Ir al Dashboard
                                    <ArrowRight size={18} className="transition-transform duration-200 group-hover:translate-x-1" />
                                </Link>
                            ) : (
                                <a
                                    href={DEMO_URL}
                                    className="cta-demo group inline-flex w-full items-center justify-center gap-2 rounded-full px-8 py-4 text-base font-bold text-white transition-all duration-200 hover:scale-[1.03] active:scale-[0.97] sm:w-auto sm:text-lg"
                                    style={{ backgroundColor: palette.primary }}
                                >
                                    <MessageCircle size={20} />
                                    Pide tu demo gratis ahora
                                    <ArrowRight size={18} className="transition-transform duration-200 group-hover:translate-x-1" />
                                </a>
                            )}
                            {canRegister && !auth?.user && (
                                <Link
                                    href={route('register')}
                                    className="inline-flex w-full items-center justify-center gap-2 rounded-full border px-8 py-4 text-base font-semibold transition-all duration-200 hover:scale-[1.02] active:scale-[0.97] sm:w-auto"
                                    style={{ borderColor: palette.border, backgroundColor: palette.surface }}
                                >
                                    Crear cuenta
                                </Link>
                            )}
                        </div>

                        <div className="mt-6 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-xs" style={{ color: palette.textMuted }}>
                            <span className="inline-flex items-center gap-1.5">
                                <BadgeCheck size={14} style={{ color: palette.success }} />
                                Demo sin compromiso
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

                    {/* Logo 3D como pieza central del hero, con parallax al scroll */}
                    <div
                        className="relative mx-auto mt-6 max-w-3xl sm:mt-8"
                        style={{
                            opacity: mounted ? 1 : 0,
                            transform: mounted ? 'none' : 'translateY(40px) scale(0.97)',
                            transition: 'opacity 1100ms var(--ease-out-strong) 150ms, transform 1100ms var(--ease-out-strong) 150ms',
                        }}
                    >
                        <div ref={heroLogoRef} className="will-change-transform">
                            <div aria-hidden="true" className="hero-logo-glow absolute inset-x-0 top-1/2 mx-auto h-[70%] w-[85%] -translate-y-1/2 rounded-full blur-2xl" />
                            <img
                                src="/logo.png"
                                alt="ventoryPOS — venta registrada y verificada"
                                className="float-slow relative mx-auto w-full max-w-[560px] select-none drop-shadow-2xl"
                                draggable={false}
                                loading="eager"
                            />
                        </div>

                        {/* chips flotantes alrededor del logo */}
                        <div
                            className="absolute left-0 top-8 hidden items-center gap-2 rounded-xl border p-3 shadow-xl sm:flex lg:-left-16"
                            style={{ backgroundColor: palette.surface, borderColor: palette.border, animation: 'float 6s ease-in-out infinite' }}
                        >
                            <div className="flex h-9 w-9 items-center justify-center rounded-lg text-white" style={{ backgroundColor: palette.success }}>
                                <BarChart3 size={18} />
                            </div>
                            <div>
                                <p className="text-xs" style={{ color: palette.textMuted }}>Ventas hoy</p>
                                <p className="text-sm font-bold">+ 18.4%</p>
                            </div>
                        </div>
                        <div
                            className="absolute bottom-10 right-0 hidden items-center gap-2 rounded-xl border p-3 shadow-xl sm:flex lg:-right-16"
                            style={{ backgroundColor: palette.surface, borderColor: palette.border, animation: 'float 7s ease-in-out infinite reverse' }}
                        >
                            <div className="flex h-9 w-9 items-center justify-center rounded-lg text-white" style={{ backgroundColor: palette.warning }}>
                                <Boxes size={18} />
                            </div>
                            <div>
                                <p className="text-xs" style={{ color: palette.textMuted }}>Stock bajo</p>
                                <p className="text-sm font-bold">3 productos</p>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Declaración grande, estilo Apple */}
                <section className="relative z-10 mx-auto max-w-5xl px-4 py-16 text-center sm:px-6 sm:py-24 lg:px-8">
                    <Reveal>
                        <h2 className="text-3xl font-extrabold leading-tight tracking-tight sm:text-5xl">
                            Menos clics.{' '}
                            <span style={{ color: palette.primary }}>Más ventas.</span>
                            <br />
                            Cero cuadernos.
                        </h2>
                        <p className="mx-auto mt-5 max-w-2xl text-base sm:text-lg" style={{ color: palette.textMuted }}>
                            Diseñado por gente que ha estado detrás de una caja registradora. Sin pantallas confusas, sin pasos de
                            más: abres, vendes y al final del día tu caja cuadra sola.
                        </p>
                    </Reveal>

                    <div className="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-3">
                        {stats.map((s, i) => (
                            <Reveal key={s.label} delay={i * 120}>
                                <div
                                    className="rounded-2xl border p-6"
                                    style={{ borderColor: palette.border, backgroundColor: palette.surface }}
                                >
                                    <p className="text-4xl font-extrabold tracking-tight sm:text-5xl" style={{ color: palette.primary }}>
                                        <Counter to={s.value} suffix={s.suffix} />
                                    </p>
                                    <p className="mt-2 text-sm" style={{ color: palette.textMuted }}>
                                        {s.label}
                                    </p>
                                </div>
                            </Reveal>
                        ))}
                    </div>
                </section>

                {/* Industrias — marquee continuo */}
                <section className="relative z-10 py-10 sm:py-14">
                    <Reveal className="mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
                        <p className="text-xs font-semibold uppercase tracking-widest" style={{ color: palette.primary }}>
                            Hecho a tu medida
                        </p>
                        <h2 className="mt-2 text-2xl font-bold tracking-tight sm:text-4xl">Un POS para cada tipo de negocio</h2>
                    </Reveal>

                    <div className="marquee mt-10 overflow-hidden" style={{ maskImage: 'linear-gradient(90deg, transparent, black 8%, black 92%, transparent)' }}>
                        <div className="marquee-track flex w-max gap-4 pr-4">
                            {[...industries, ...industries].map((ind, i) => {
                                const Icon = ind.icon;
                                return (
                                    <div
                                        key={i}
                                        className="w-56 shrink-0 rounded-2xl border p-5 transition-transform duration-300 hover:-translate-y-1"
                                        style={{ borderColor: palette.border, backgroundColor: palette.surface }}
                                    >
                                        <div
                                            className="flex h-11 w-11 items-center justify-center rounded-xl"
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
                    </div>
                </section>

                {/* Features */}
                <section className="relative z-10 mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
                    <Reveal className="text-center">
                        <p className="text-xs font-semibold uppercase tracking-widest" style={{ color: palette.primary }}>
                            Funcionalidades
                        </p>
                        <h2 className="mt-2 text-2xl font-bold tracking-tight sm:text-4xl">Todo lo que necesitas para vender mejor</h2>
                        <p className="mx-auto mt-3 max-w-xl text-sm sm:text-base" style={{ color: palette.textMuted }}>
                            Caja, inventario, finanzas y reportes trabajando juntos, en tiempo real.
                        </p>
                    </Reveal>

                    <div className="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        {features.map((f, i) => {
                            const Icon = f.icon;
                            return (
                                <Reveal key={f.title} delay={(i % 3) * 110}>
                                    <div
                                        className="group relative h-full overflow-hidden rounded-2xl border p-6 transition-all duration-300 hover:-translate-y-1.5 hover:shadow-xl"
                                        style={{ borderColor: palette.border, backgroundColor: palette.surface }}
                                    >
                                        <div
                                            className="absolute inset-0 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                                            style={{ background: `radial-gradient(600px circle at 0% 0%, ${f.accent}10, transparent 40%)` }}
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
                                </Reveal>
                            );
                        })}
                    </div>
                </section>

                {/* CTA final */}
                <section className="relative z-10 mx-auto max-w-7xl px-4 pb-16 sm:px-6 sm:pb-24 lg:px-8">
                    <Reveal>
                        <div
                            className="relative overflow-hidden rounded-3xl px-6 py-12 text-center shadow-2xl sm:px-12 sm:py-16"
                            style={{ background: `linear-gradient(135deg, ${palette.primary} 0%, #1d4ed8 60%, ${palette.success} 160%)` }}
                        >
                            <div className="pointer-events-none absolute inset-0 opacity-20">
                                <div className="absolute -left-10 -top-10 h-44 w-44 rounded-full bg-white blur-3xl" />
                                <div className="absolute -right-10 bottom-0 h-44 w-44 rounded-full bg-white blur-3xl" />
                            </div>

                            <div className="relative">
                                <img
                                    src="/logo.png"
                                    alt=""
                                    aria-hidden="true"
                                    className="float-slow mx-auto w-40 select-none drop-shadow-xl sm:w-52"
                                    draggable={false}
                                    loading="lazy"
                                />
                                <h2 className="mt-6 text-3xl font-extrabold tracking-tight text-white sm:text-5xl">
                                    Tu negocio merece este nivel de control
                                </h2>
                                <p className="mx-auto mt-4 max-w-xl text-white/85 sm:text-lg">
                                    Te lo mostramos funcionando con tus propios productos. Configuración en minutos, no en días.
                                </p>
                                <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                                    <a
                                        href={DEMO_URL}
                                        className="group inline-flex w-full items-center justify-center gap-2 rounded-full bg-white px-8 py-4 text-base font-bold shadow-xl transition-all duration-200 hover:scale-[1.03] active:scale-[0.97] sm:w-auto sm:text-lg"
                                        style={{ color: palette.primary }}
                                    >
                                        <MessageCircle size={20} />
                                        Pide tu demo gratis ahora
                                        <ArrowRight size={18} className="transition-transform duration-200 group-hover:translate-x-1" />
                                    </a>
                                    <Link
                                        href={route('login')}
                                        className="inline-flex w-full items-center justify-center gap-2 rounded-full border-2 border-white/30 px-8 py-4 text-base font-semibold text-white transition-all duration-200 hover:bg-white/10 sm:w-auto"
                                    >
                                        Ya tengo cuenta
                                    </Link>
                                </div>
                                <p className="mt-5 text-xs text-white/70">Sin tarjeta de crédito · Sin compromiso · Respuesta el mismo día</p>
                            </div>
                        </div>
                    </Reveal>
                </section>

                {/* Footer */}
                <footer className="safe-bottom relative z-10 border-t" style={{ borderColor: palette.border }}>
                    <div
                        className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 pb-2 pt-6 text-xs sm:flex-row sm:px-6 sm:pb-4 sm:pt-8 lg:px-8"
                        style={{ color: palette.textMuted }}
                    >
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
