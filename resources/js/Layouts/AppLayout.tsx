import React, { useEffect, useRef, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { Toaster } from 'react-hot-toast';
import { Bell, ChevronDown, ChevronRight, LogOut, Menu, User, X } from 'lucide-react';
import type { PageProps, ModuloMenu } from '@/types';
import DynamicIcon from '@/Components/DynamicIcon';
import { ColorPaletteProvider } from '@/Components/ColorPaletteProvider';
import ColorPaletteEditor from '@/Components/ColorPaletteEditor';
import RouterLoadingOverlay from '@/Components/RouterLoadingOverlay';
import AgenteConfigButton from '@/Components/AgenteConfigButton';

interface AppLayoutProps {
    children: React.ReactNode;
    title?: string;
}

const SIDEBAR_STORAGE_KEY = 'macsoft_sidebar_open';

function SidebarItem({ item, onNavigate }: { item: ModuloMenu; onNavigate: () => void }) {
    const { url } = usePage();
    const hasChildren = item.hijos && item.hijos.length > 0;
    const isActive = item.ruta ? url.startsWith(item.ruta) : false;
    const isChildActive = item.hijos?.some(h => h.ruta && url.startsWith(h.ruta)) ?? false;
    const [expanded, setExpanded] = useState(isChildActive);

    if (hasChildren) {
        return (
            <div>
                <button
                    onClick={() => setExpanded(e => !e)}
                    className="group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-200"
                    style={{
                        color: isChildActive ? 'var(--sidebar-text-active)' : 'var(--sidebar-text)',
                        backgroundColor: isChildActive ? 'var(--sidebar-item-active)' : 'transparent',
                    }}
                >
                    {item.icono && (
                        <DynamicIcon
                            name={item.icono}
                            size={18}
                            className="flex-shrink-0 transition-transform duration-200 group-hover:scale-110"
                        />
                    )}
                    <span className="flex-1 text-left">{item.nombre}</span>
                    <span
                        className="transition-transform duration-300 ease-out"
                        style={{ transform: expanded ? 'rotate(90deg)' : 'rotate(0deg)' }}
                    >
                        <ChevronRight size={14} />
                    </span>
                </button>
                <div
                    className="overflow-hidden transition-all duration-300 ease-in-out"
                    style={{
                        maxHeight: expanded ? '600px' : '0px',
                        opacity: expanded ? 1 : 0,
                    }}
                >
                    <div className="ml-4 mt-1 space-y-0.5 border-l pl-3" style={{ borderColor: 'var(--sidebar-border)' }}>
                        {item.hijos!.map(child => (
                            <SidebarItem key={child.id} item={child} onNavigate={onNavigate} />
                        ))}
                    </div>
                </div>
            </div>
        );
    }

    return (
        <Link
            href={item.ruta ?? '#'}
            onClick={onNavigate}
            className="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-200"
            style={{
                color: isActive ? 'var(--sidebar-text-active)' : 'var(--sidebar-text)',
                backgroundColor: isActive ? 'var(--sidebar-item-active)' : 'transparent',
            }}
        >
            {item.icono && (
                <DynamicIcon
                    name={item.icono}
                    size={18}
                    className="flex-shrink-0 transition-transform duration-200 group-hover:scale-110"
                />
            )}
            <span>{item.nombre}</span>
        </Link>
    );
}

function Sidebar({
    modules,
    open,
    onClose,
}: {
    modules: ModuloMenu[];
    open: boolean;
    onClose: () => void;
}) {
    const { auth } = usePage<PageProps>().props;

    return (
        <aside
            aria-hidden={!open}
            className="fixed top-0 left-0 z-40 flex h-full w-72 flex-col shadow-2xl transition-transform duration-300 ease-out will-change-transform"
            style={{
                backgroundColor: 'var(--sidebar-bg)',
                transform: open ? 'translateX(0)' : 'translateX(-100%)',
            }}
        >
            {/* Logo */}
            <div
                className="flex items-center justify-between border-b px-4 py-4 flex-shrink-0"
                style={{ borderColor: 'var(--sidebar-border)' }}
            >
                <img
                    src="/logo-full-white.svg"
                    alt="ventoryPOS"
                    className="h-10 w-auto select-none"
                    draggable={false}
                />
                <button
                    onClick={onClose}
                    className="rounded-md p-1.5 text-white/70 transition-colors duration-200 hover:bg-white/10 hover:text-white"
                    aria-label="Cerrar menú"
                >
                    <X size={18} />
                </button>
            </div>

            {/* Nav */}
            <nav className="flex-1 overflow-y-auto px-3 py-3 space-y-0.5">
                {modules.map(mod => (
                    <SidebarItem key={mod.id} item={mod} onNavigate={onClose} />
                ))}
            </nav>

            {/* User */}
            <div
                className="border-t px-3 py-3 flex-shrink-0"
                style={{ borderColor: 'var(--sidebar-border)' }}
            >
                <div className="flex items-center gap-3">
                    <div
                        className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-white text-sm font-semibold shadow-md"
                        style={{ backgroundColor: 'var(--sidebar-accent)' }}
                    >
                        {auth.user.name.charAt(0).toUpperCase()}
                    </div>
                    <div className="flex-1 min-w-0">
                        <p className="text-sm font-medium text-white truncate">{auth.user.name}</p>
                        <p className="text-xs truncate" style={{ color: 'var(--sidebar-text)' }}>
                            {auth.user.empresa?.nombre_comercial ?? auth.user.empresa?.razon_social}
                        </p>
                    </div>
                </div>
            </div>
        </aside>
    );
}

function HamburgerButton({ open, onClick }: { open: boolean; onClick: () => void }) {
    return (
        <button
            onClick={onClick}
            aria-label={open ? 'Cerrar menú' : 'Abrir menú'}
            aria-expanded={open}
            className="relative flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg transition-all duration-200 hover:bg-black/5 active:scale-95"
            style={{ color: 'var(--color-text)' }}
        >
            <Menu
                size={20}
                className="absolute transition-all duration-300 ease-out"
                style={{
                    opacity: open ? 0 : 1,
                    transform: open ? 'rotate(-90deg) scale(0.6)' : 'rotate(0) scale(1)',
                }}
            />
            <X
                size={20}
                className="absolute transition-all duration-300 ease-out"
                style={{
                    opacity: open ? 1 : 0,
                    transform: open ? 'rotate(0) scale(1)' : 'rotate(90deg) scale(0.6)',
                }}
            />
        </button>
    );
}

function UserMenu({ onLogout }: { onLogout: () => void }) {
    const { auth } = usePage<PageProps>().props;
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;
        const onDoc = (e: MouseEvent) => {
            if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
        };
        const onEsc = (e: KeyboardEvent) => {
            if (e.key === 'Escape') setOpen(false);
        };
        document.addEventListener('mousedown', onDoc);
        document.addEventListener('keydown', onEsc);
        return () => {
            document.removeEventListener('mousedown', onDoc);
            document.removeEventListener('keydown', onEsc);
        };
    }, [open]);

    const initial = auth.user.name.charAt(0).toUpperCase();
    const companyName = auth.user.empresa?.nombre_comercial ?? auth.user.empresa?.razon_social ?? '';

    return (
        <div className="relative" ref={ref}>
            <button
                onClick={() => setOpen(o => !o)}
                className="group flex items-center gap-2.5 rounded-lg px-2 py-1.5 transition-all duration-200 hover:bg-black/5 active:scale-[0.98]"
            >
                <div
                    className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full text-white text-sm font-semibold shadow-sm transition-transform duration-200 group-hover:scale-105"
                    style={{ backgroundColor: 'var(--color-primary)' }}
                >
                    {initial}
                </div>
                <div className="hidden sm:flex flex-col items-start leading-tight">
                    <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>
                        {auth.user.name}
                    </span>
                    {companyName && (
                        <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                            {companyName}
                        </span>
                    )}
                </div>
                <ChevronDown
                    size={14}
                    className="hidden sm:block transition-transform duration-300 ease-out"
                    style={{
                        color: 'var(--color-text-muted)',
                        transform: open ? 'rotate(180deg)' : 'rotate(0)',
                    }}
                />
            </button>

            <div
                className="absolute right-0 mt-2 w-64 origin-top-right rounded-xl border shadow-xl transition-all duration-200 ease-out"
                style={{
                    backgroundColor: 'var(--color-surface)',
                    borderColor: 'var(--color-border)',
                    opacity: open ? 1 : 0,
                    transform: open ? 'translateY(0) scale(1)' : 'translateY(-8px) scale(0.95)',
                    pointerEvents: open ? 'auto' : 'none',
                }}
            >
                <div className="px-4 py-3 border-b" style={{ borderColor: 'var(--color-border)' }}>
                    <div className="flex items-center gap-3">
                        <div
                            className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-white text-sm font-semibold"
                            style={{ backgroundColor: 'var(--color-primary)' }}
                        >
                            {initial}
                        </div>
                        <div className="flex-1 min-w-0">
                            <p className="text-sm font-semibold truncate" style={{ color: 'var(--color-text)' }}>
                                {auth.user.name}
                            </p>
                            <p className="text-xs truncate" style={{ color: 'var(--color-text-muted)' }}>
                                {auth.user.email}
                            </p>
                        </div>
                    </div>
                    {companyName && (
                        <div className="mt-2 flex items-center gap-1.5 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                            <span className="inline-block h-1.5 w-1.5 rounded-full" style={{ backgroundColor: 'var(--color-success)' }} />
                            <span className="truncate">{companyName}</span>
                        </div>
                    )}
                </div>
                <div className="py-1">
                    <button
                        onClick={() => {
                            setOpen(false);
                            onLogout();
                        }}
                        className="flex w-full items-center gap-2 px-4 py-2.5 text-sm transition-colors duration-150 hover:bg-black/5"
                        style={{ color: 'var(--color-danger)' }}
                    >
                        <LogOut size={15} />
                        Cerrar sesión
                    </button>
                </div>
            </div>
        </div>
    );
}

/**
 * Campanita de cotizaciones: visible solo cuando hay algo que atender
 * (por vencer en ≤3 días o vencidas sin respuesta). Lleva al módulo con el
 * filtro "alerta" ya aplicado. El backend solo envía la prop a usuarios con
 * permiso de ver cotizaciones.
 */
function CampanitaCotizaciones() {
    const { alertasCotizaciones } = usePage<PageProps>().props;
    const porVencer = alertasCotizaciones?.por_vencer ?? 0;
    const vencidas  = alertasCotizaciones?.vencidas_sin_respuesta ?? 0;
    const total     = porVencer + vencidas;

    if (!alertasCotizaciones || total <= 0) return null;

    const detalle = [
        porVencer > 0 ? `${porVencer} cotización(es) por vencer en ≤3 días` : null,
        vencidas > 0 ? `${vencidas} vencida(s) sin respuesta del cliente` : null,
    ].filter(Boolean).join(' · ');

    return (
        <Link
            href={route('cotizaciones.index', { estado: 'alerta' })}
            title={detalle}
            aria-label={`Alertas de cotizaciones: ${detalle}`}
            className="relative flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg transition-all duration-200 hover:bg-black/5 active:scale-95"
            style={{ color: 'var(--color-text)' }}
        >
            <Bell size={19} />
            <span
                className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-bold text-white shadow-sm"
                style={{ backgroundColor: vencidas > 0 ? 'var(--color-danger)' : 'var(--color-warning)' }}
            >
                {total > 99 ? '99+' : total}
            </span>
        </Link>
    );
}

export default function AppLayout({ children, title }: AppLayoutProps) {
    const { modules = [] } = usePage<PageProps>().props;

    const [sidebarOpen, setSidebarOpen] = useState<boolean>(() => {
        if (typeof window === 'undefined') return false;
        try {
            return window.localStorage.getItem(SIDEBAR_STORAGE_KEY) === '1';
        } catch {
            return false;
        }
    });

    useEffect(() => {
        try {
            window.localStorage.setItem(SIDEBAR_STORAGE_KEY, sidebarOpen ? '1' : '0');
        } catch {
            // ignore
        }
    }, [sidebarOpen]);

    useEffect(() => {
        if (!sidebarOpen) return;
        const onEsc = (e: KeyboardEvent) => {
            if (e.key === 'Escape') setSidebarOpen(false);
        };
        document.addEventListener('keydown', onEsc);
        return () => document.removeEventListener('keydown', onEsc);
    }, [sidebarOpen]);

    const logout = () => {
        router.post(route('logout'));
    };

    return (
        <ColorPaletteProvider>
            <div className="min-h-screen" style={{ backgroundColor: 'var(--color-bg)' }}>
                {/* Backdrop */}
                <div
                    onClick={() => setSidebarOpen(false)}
                    aria-hidden={!sidebarOpen}
                    className="fixed inset-0 z-30 bg-slate-900/50 backdrop-blur-sm transition-opacity duration-300"
                    style={{
                        opacity: sidebarOpen ? 1 : 0,
                        pointerEvents: sidebarOpen ? 'auto' : 'none',
                    }}
                />

                <Sidebar
                    modules={modules}
                    open={sidebarOpen}
                    onClose={() => setSidebarOpen(false)}
                />

                <div className="flex flex-col min-h-screen">
                    {/* Header */}
                    <header
                        className="sticky top-0 z-20 border-b backdrop-blur-md"
                        style={{
                            backgroundColor: 'color-mix(in srgb, var(--color-surface) 92%, transparent)',
                            borderColor: 'var(--color-border)',
                            paddingTop: 'env(safe-area-inset-top)',
                        }}
                    >
                        <div className="flex items-center gap-3 px-4 sm:px-6 py-2.5">
                            <HamburgerButton
                                open={sidebarOpen}
                                onClick={() => setSidebarOpen(o => !o)}
                            />

                            {/* Logo + system name */}
                            <Link
                                href={route('dashboard') ?? '/'}
                                className="flex items-center gap-2 select-none transition-opacity duration-200 hover:opacity-80"
                            >
                                <img
                                    src="/logo-full.svg"
                                    alt="ventoryPOS"
                                    className="h-7 w-auto"
                                    draggable={false}
                                />
                                <span
                                    className="hidden md:inline text-base font-semibold tracking-tight"
                                    style={{ color: 'var(--color-text)' }}
                                >
                                    ventoryPOS
                                </span>
                            </Link>

                            {/* Divider + page title */}
                            {title && (
                                <div className="hidden md:flex items-center gap-3 min-w-0">
                                    <span
                                        className="h-5 w-px"
                                        style={{ backgroundColor: 'var(--color-border)' }}
                                    />
                                    <h1
                                        className="text-sm font-medium truncate"
                                        style={{ color: 'var(--color-text-muted)' }}
                                    >
                                        {title}
                                    </h1>
                                </div>
                            )}

                            <span className="flex-1" />

                            <CampanitaCotizaciones />

                            <AgenteConfigButton />

                            <UserMenu onLogout={logout} />
                        </div>

                        {/* Mobile page title */}
                        {title && (
                            <div
                                className="md:hidden px-4 pb-2 -mt-1 text-xs font-medium truncate"
                                style={{ color: 'var(--color-text-muted)' }}
                            >
                                {title}
                            </div>
                        )}
                    </header>

                    {/* Content */}
                    <main className="flex-1 p-4 sm:p-6">
                        {children}
                    </main>
                </div>

                <ColorPaletteEditor />
                <Toaster position="top-right" />
                <RouterLoadingOverlay />
            </div>
        </ColorPaletteProvider>
    );
}
