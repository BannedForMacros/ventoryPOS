import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    BadgeCheck,
    BarChart3,
    Boxes,
    Cat,
    CheckCircle2,
    Coffee,
    CreditCard,
    FileCheck2,
    Heart,
    MessageCircle,
    Percent,
    Pill,
    Receipt,
    RotateCcw,
    ShieldCheck,
    ShoppingBag,
    Sparkles,
    Store,
    Truck,
    UserCheck,
    Users,
    Wallet,
    Wrench,
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

/* Todo lo que se afirma abajo existe en el sistema: POS con turnos y arqueo,
   comprobantes electrónicos, kardex, tesorería, CxC/CxP, reportes, multimoneda,
   multi-local, roles y auditoría. Si se promociona algo nuevo, primero se
   construye, después se anuncia. */

interface Rubro {
    icon: typeof Cat;
    label: string;
    desc: string;
}

const rubros: Rubro[] = [
    { icon: Store, label: 'Bodegas y minimarkets', desc: 'Venta rápida con lector de códigos' },
    { icon: Wrench, label: 'Ferreterías', desc: 'Unidades múltiples y venta a crédito' },
    { icon: Pill, label: 'Farmacias', desc: 'Catálogo grande y rotación controlada' },
    { icon: Cat, label: 'Veterinarias', desc: 'Productos, servicios y citas' },
    { icon: ShoppingBag, label: 'Retail y boutiques', desc: 'Multi-local con stock por tienda' },
    { icon: Coffee, label: 'Cafés y restobar', desc: 'Combos, servicios y comandas' },
    { icon: Truck, label: 'Distribuidoras', desc: 'Cotizaciones, anticipos y CxC' },
    { icon: Users, label: 'Servicios', desc: 'Vende servicios igual que productos' },
];

interface Pilar {
    icon: typeof BarChart3;
    accent: string;
    kicker: string;
    title: string;
    desc: string;
    puntos: string[];
}

const pilares: Pilar[] = [
    {
        icon: Receipt,
        accent: palette.primary,
        kicker: 'Vende',
        title: 'Cobra en segundos, con comprobante electrónico',
        desc: 'El POS está pensado para la cola de las 6 de la tarde: buscas por nombre o código, cobras con varios métodos a la vez y el comprobante sale solo.',
        puntos: [
            'Efectivo, tarjeta, Yape, Plin y transferencia en una misma venta, con vuelto automático',
            'Boleta y factura electrónica emitidas desde la misma pantalla de venta',
            'Cotizaciones que envías por WhatsApp y conviertes en venta con un clic',
            'Descuentos por producto o por venta, con registro de quién los autorizó',
            'Venta a crédito y anticipos de clientes, conectados a cuentas por cobrar',
        ],
    },
    {
        icon: Boxes,
        accent: palette.success,
        kicker: 'Controla',
        title: 'Tu inventario, movimiento por movimiento',
        desc: 'Cada entrada, venta, transferencia o ajuste queda en el kardex. Cuando el stock no cuadra, no adivinas: revisas la historia completa del producto.',
        puntos: [
            'Stock por local y por almacén, con alertas de stock bajo',
            'Entradas de mercadería con costos, pagos y deuda al proveedor',
            'Transferencias entre almacenes y ajustes con motivo',
            'Kardex trazable: quién movió qué, cuándo y por qué documento',
            'Cierre de inventario con diferencias valorizadas (faltantes y sobrantes)',
        ],
    },
    {
        icon: Wallet,
        accent: palette.warning,
        kicker: 'Decide',
        title: 'Caja cuadrada y utilidad real, todos los días',
        desc: 'Turnos con apertura, cierre y arqueo; tesorería por cuenta; y un balance diario que te dice cuánto vendiste, cuánto gastaste y cuánto ganaste.',
        puntos: [
            'Turnos de caja con arqueo y retiros a administración',
            'Cuentas por cobrar y por pagar, deudas y gastos por categoría',
            'Balance diario: lo que tienes, lo que te deben y lo que debes',
            'Utilidad calculada con el costo real de cada venta',
            '8 reportes: ventas, caja, utilidad, kardex, productos, gastos, devoluciones y auditoría',
        ],
    },
];

interface Detalle {
    icon: typeof BarChart3;
    title: string;
    desc: string;
    accent: string;
}

const detalles: Detalle[] = [
    {
        icon: UserCheck,
        title: 'Clientes en 2 segundos',
        desc: 'Escribes el DNI o RUC y los datos se completan solos desde el padrón. Nada de tipear nombres.',
        accent: palette.primary,
    },
    {
        icon: MessageCircle,
        title: 'Cotizaciones por WhatsApp',
        desc: 'Generas la cotización, la compartes por WhatsApp con enlace y PDF, y le das seguimiento hasta la venta.',
        accent: palette.success,
    },
    {
        icon: Percent,
        title: 'Descuentos con control',
        desc: 'Cada descuento tiene concepto, límite y registro. Tú decides quién puede rebajar y cuánto.',
        accent: palette.secondary,
    },
    {
        icon: RotateCcw,
        title: 'Devoluciones ordenadas',
        desc: 'Con motivo y efecto automático en caja y stock. Sin ventas fantasma ni plata que desaparece.',
        accent: palette.danger,
    },
    {
        icon: CreditCard,
        title: 'Soles y dólares',
        desc: 'Vende y compra en dos monedas con tipo de cambio congelado por operación. Los montos no bailan.',
        accent: palette.warning,
    },
    {
        icon: ShieldCheck,
        title: 'Roles, permisos y auditoría',
        desc: 'Cada usuario ve solo lo que le toca, y cada acción queda registrada: quién, cuándo y qué cambió.',
        accent: palette.primary,
    },
];

const stats = [
    { value: 8, suffix: '', label: 'reportes en tiempo real para decidir con datos' },
    { value: 2, suffix: '', label: 'monedas: soles y dólares, con tipo de cambio congelado' },
    { value: 1, suffix: '', label: 'solo sistema para caja, inventario, clientes y finanzas' },
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
                el.style.transform = `translateY(${y * 0.16}px) scale(${1 - p * 0.12})`;
                el.style.opacity = String(1 - p * 0.55);
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
            <Head title="ventoryPOS — El punto de venta para cualquier negocio que vende" />

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
                        animation: marquee 40s linear infinite;
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

                {/* Hero — 2 columnas en desktop para que titular + CTA + logo entren
                    arriba del fold; en móvil, texto y CTA primero, logo después */}
                <section className="relative z-10 mx-auto max-w-7xl px-4 pb-8 pt-20 sm:px-6 sm:pt-24 lg:px-8 lg:pt-28">
                    <div className="grid items-center gap-8 lg:grid-cols-[1.1fr_1fr] lg:gap-12">
                        <div
                            className="text-center lg:text-left"
                            style={{
                                opacity: mounted ? 1 : 0,
                                transform: mounted ? 'none' : 'translateY(24px)',
                                transition: 'opacity 900ms var(--ease-out-strong), transform 900ms var(--ease-out-strong)',
                            }}
                        >
                            <div
                                className="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium"
                                style={{ borderColor: palette.border, backgroundColor: palette.surface, color: palette.textMuted }}
                            >
                                <Sparkles size={14} style={{ color: palette.warning }} />
                                <span>Sistema de punto de venta · Hecho en Perú</span>
                            </div>

                            <h1 className="mt-5 text-[2.4rem] font-extrabold leading-[1.05] tracking-tight sm:text-5xl lg:text-6xl">
                                Vende. Controla.
                                <br />
                                <span className="shimmer-text">Crece.</span>
                            </h1>

                            <p className="mx-auto mt-5 max-w-xl text-base sm:text-lg lg:mx-0" style={{ color: palette.textMuted }}>
                                ventoryPOS une tu caja, inventario, clientes y finanzas en un solo sistema. Cobras en segundos,
                                emites boleta o factura electrónica y sabes cuánto ganas cada día. Si tu negocio vende, es para ti.
                            </p>

                            <div className="mt-7 flex flex-col items-center gap-3 sm:flex-row sm:justify-center lg:justify-start">
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
                                        className="cta-demo group inline-flex w-full items-center justify-center gap-2 rounded-full px-7 py-4 text-base font-bold text-white transition-all duration-200 hover:scale-[1.03] active:scale-[0.97] sm:w-auto"
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
                                        className="inline-flex w-full items-center justify-center gap-2 rounded-full border px-7 py-4 text-base font-semibold transition-all duration-200 hover:scale-[1.02] active:scale-[0.97] sm:w-auto"
                                        style={{ borderColor: palette.border, backgroundColor: palette.surface }}
                                    >
                                        Crear cuenta
                                    </Link>
                                )}
                            </div>

                            <div
                                className="mt-6 flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-xs lg:justify-start"
                                style={{ color: palette.textMuted }}
                            >
                                <span className="inline-flex items-center gap-1.5">
                                    <BadgeCheck size={14} style={{ color: palette.success }} />
                                    Demo sin compromiso
                                </span>
                                <span className="inline-flex items-center gap-1.5">
                                    <FileCheck2 size={14} style={{ color: palette.success }} />
                                    Boleta y factura electrónica
                                </span>
                                <span className="inline-flex items-center gap-1.5">
                                    <Store size={14} style={{ color: palette.success }} />
                                    Multi-local y multi-usuario
                                </span>
                            </div>
                        </div>

                        {/* Logo 3D con parallax al scroll */}
                        <div
                            className="relative mx-auto w-full max-w-[300px] sm:max-w-[400px] lg:max-w-[520px]"
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
                                    className="float-slow relative mx-auto w-full select-none drop-shadow-2xl"
                                    draggable={false}
                                    loading="eager"
                                />
                            </div>

                            {/* chips flotantes alrededor del logo */}
                            <div
                                className="absolute -left-6 top-4 hidden items-center gap-2 rounded-xl border p-3 shadow-xl lg:flex"
                                style={{ backgroundColor: palette.surface, borderColor: palette.border, animation: 'float 6s ease-in-out infinite' }}
                            >
                                <div className="flex h-9 w-9 items-center justify-center rounded-lg text-white" style={{ backgroundColor: palette.success }}>
                                    <FileCheck2 size={18} />
                                </div>
                                <div>
                                    <p className="text-xs" style={{ color: palette.textMuted }}>Factura F001-2841</p>
                                    <p className="text-sm font-bold">Aceptada</p>
                                </div>
                            </div>
                            <div
                                className="absolute -right-4 bottom-6 hidden items-center gap-2 rounded-xl border p-3 shadow-xl lg:flex"
                                style={{ backgroundColor: palette.surface, borderColor: palette.border, animation: 'float 7s ease-in-out infinite reverse' }}
                            >
                                <div className="flex h-9 w-9 items-center justify-center rounded-lg text-white" style={{ backgroundColor: palette.warning }}>
                                    <Boxes size={18} />
                                </div>
                                <div>
                                    <p className="text-xs" style={{ color: palette.textMuted }}>Alerta de stock</p>
                                    <p className="text-sm font-bold">3 productos</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Declaración grande, estilo Apple */}
                <section className="relative z-10 mx-auto max-w-5xl px-4 py-14 text-center sm:px-6 sm:py-20 lg:px-8">
                    <Reveal>
                        <h2 className="text-3xl font-extrabold leading-tight tracking-tight sm:text-5xl">
                            Menos clics. <span style={{ color: palette.primary }}>Más ventas.</span>
                            <br />
                            Cero cuadernos.
                        </h2>
                        <p className="mx-auto mt-5 max-w-2xl text-base sm:text-lg" style={{ color: palette.textMuted }}>
                            Diseñado por gente que ha estado detrás de una caja registradora. Sin pantallas confusas, sin pasos de
                            más: abres turno, vendes y al cierre comparas lo contado contra lo que dice el sistema.
                        </p>
                    </Reveal>

                    <div className="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-3">
                        {stats.map((s, i) => (
                            <Reveal key={s.label} delay={i * 120}>
                                <div
                                    className="h-full rounded-2xl border p-6"
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

                {/* Pilares — lo que el sistema hace de verdad, en detalle */}
                <section className="relative z-10 mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
                    <div className="space-y-14 sm:space-y-20">
                        {pilares.map((p, i) => {
                            const Icon = p.icon;
                            const invertido = i % 2 === 1;
                            return (
                                <Reveal key={p.kicker}>
                                    <div className={`grid items-center gap-8 lg:grid-cols-2 lg:gap-14 ${invertido ? 'lg:[&>*:first-child]:order-2' : ''}`}>
                                        <div>
                                            <p className="text-xs font-semibold uppercase tracking-widest" style={{ color: p.accent }}>
                                                {p.kicker}
                                            </p>
                                            <h3 className="mt-2 text-2xl font-extrabold leading-tight tracking-tight sm:text-3xl">
                                                {p.title}
                                            </h3>
                                            <p className="mt-3 text-sm leading-relaxed sm:text-base" style={{ color: palette.textMuted }}>
                                                {p.desc}
                                            </p>
                                        </div>
                                        <div
                                            className="rounded-2xl border p-5 shadow-sm sm:p-6"
                                            style={{ borderColor: palette.border, backgroundColor: palette.surface }}
                                        >
                                            <div
                                                className="mb-4 flex h-11 w-11 items-center justify-center rounded-xl"
                                                style={{ backgroundColor: `${p.accent}15`, color: p.accent }}
                                            >
                                                <Icon size={22} />
                                            </div>
                                            <ul className="space-y-2.5">
                                                {p.puntos.map((punto) => (
                                                    <li key={punto} className="flex items-start gap-2.5 text-sm leading-relaxed">
                                                        <CheckCircle2 size={16} className="mt-0.5 shrink-0" style={{ color: p.accent }} />
                                                        <span style={{ color: palette.textMuted }}>{punto}</span>
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    </div>
                                </Reveal>
                            );
                        })}
                    </div>
                </section>

                {/* Rubros — marquee continuo */}
                <section className="relative z-10 py-10 sm:py-14">
                    <Reveal className="mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
                        <p className="text-xs font-semibold uppercase tracking-widest" style={{ color: palette.primary }}>
                            Sin encasillarte
                        </p>
                        <h2 className="mt-2 text-2xl font-bold tracking-tight sm:text-4xl">Un POS que se adapta a tu rubro</h2>
                        <p className="mx-auto mt-3 max-w-xl text-sm sm:text-base" style={{ color: palette.textMuted }}>
                            Productos o servicios, mostrador o delivery, un local o varios: si vendes, funciona.
                        </p>
                    </Reveal>

                    <div className="marquee mt-10 overflow-hidden" style={{ maskImage: 'linear-gradient(90deg, transparent, black 8%, black 92%, transparent)' }}>
                        <div className="marquee-track flex w-max gap-4 pr-4">
                            {[...rubros, ...rubros].map((r, i) => {
                                const Icon = r.icon;
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
                                        <p className="mt-3 text-sm font-semibold">{r.label}</p>
                                        <p className="mt-0.5 text-xs leading-relaxed" style={{ color: palette.textMuted }}>
                                            {r.desc}
                                        </p>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </section>

                {/* Detalles que enamoran */}
                <section className="relative z-10 mx-auto max-w-7xl px-4 py-14 sm:px-6 sm:py-20 lg:px-8">
                    <Reveal className="text-center">
                        <p className="text-xs font-semibold uppercase tracking-widest" style={{ color: palette.primary }}>
                            Los detalles importan
                        </p>
                        <h2 className="mt-2 text-2xl font-bold tracking-tight sm:text-4xl">Pequeñas cosas que ahorran horas</h2>
                    </Reveal>

                    <div className="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        {detalles.map((f, i) => {
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
                                    className="float-slow mx-auto w-36 select-none drop-shadow-xl sm:w-48"
                                    draggable={false}
                                    loading="lazy"
                                />
                                <h2 className="mt-6 text-3xl font-extrabold tracking-tight text-white sm:text-5xl">
                                    Míralo funcionando con tus propios productos
                                </h2>
                                <p className="mx-auto mt-4 max-w-xl text-white/85 sm:text-lg">
                                    Cuéntanos qué vendes y te mostramos, en vivo, cómo quedaría tu caja, tu inventario y tus
                                    reportes. Sin compromiso.
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
                                <p className="mt-5 text-xs text-white/70">Demo gratis · Sin compromiso · Configuración en minutos</p>
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
