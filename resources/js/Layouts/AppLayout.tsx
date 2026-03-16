import React, { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { Toaster } from 'react-hot-toast';
import { Menu, X, ChevronDown, ChevronRight, LogOut, User } from 'lucide-react';
import type { PageProps, ModuloMenu } from '@/types';
import DynamicIcon from '@/Components/DynamicIcon';
import { ColorPaletteProvider } from '@/Components/ColorPaletteProvider';
import ColorPaletteEditor from '@/Components/ColorPaletteEditor';

interface AppLayoutProps {
    children: React.ReactNode;
    title?: string;
}

function SidebarItem({ item, collapsed }: { item: ModuloMenu; collapsed: boolean }) {
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
                    className="flex w-full items-center gap-3 rounded px-3 py-2 text-sm font-medium transition-colors duration-150"
                    style={{
                        color: isChildActive ? 'var(--sidebar-text-active)' : 'var(--sidebar-text)',
                        backgroundColor: isChildActive ? 'var(--sidebar-item-active)' : 'transparent',
                    }}
                >
                    {item.icono && <DynamicIcon name={item.icono} size={18} className="flex-shrink-0" />}
                    {!collapsed && (
                        <>
                            <span className="flex-1 text-left">{item.nombre}</span>
                            {expanded ? <ChevronDown size={14} /> : <ChevronRight size={14} />}
                        </>
                    )}
                </button>
                {!collapsed && expanded && (
                    <div className="ml-4 mt-0.5 space-y-0.5 border-l pl-3" style={{ borderColor: 'var(--sidebar-border)' }}>
                        {item.hijos!.map(child => (
                            <SidebarItem key={child.id} item={child} collapsed={false} />
                        ))}
                    </div>
                )}
            </div>
        );
    }

    return (
        <Link
            href={item.ruta ?? '#'}
            className="flex items-center gap-3 rounded px-3 py-2 text-sm font-medium transition-colors duration-150"
            style={{
                color: isActive ? 'var(--sidebar-text-active)' : 'var(--sidebar-text)',
                backgroundColor: isActive ? 'var(--sidebar-item-active)' : 'transparent',
            }}
            title={collapsed ? item.nombre : undefined}
        >
            {item.icono && <DynamicIcon name={item.icono} size={18} className="flex-shrink-0" />}
            {!collapsed && <span>{item.nombre}</span>}
        </Link>
    );
}

function Sidebar({ modules, collapsed }: { modules: ModuloMenu[]; collapsed: boolean }) {
    const { auth } = usePage<PageProps>().props;

    return (
        <aside
            className="fixed top-0 left-0 h-full flex flex-col z-30 transition-all duration-200"
            style={{
                width: collapsed ? '64px' : '256px',
                backgroundColor: 'var(--sidebar-bg)',
            }}
        >
            {/* Logo */}
            <div
                className="flex items-center gap-2 px-3 py-4 border-b flex-shrink-0"
                style={{ borderColor: 'var(--sidebar-border)' }}
            >
                <div
                    className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded font-bold text-white text-sm"
                    style={{ backgroundColor: 'var(--sidebar-accent)' }}
                >
                    M
                </div>
                {!collapsed && (
                    <span className="font-semibold text-white truncate">MacSoft</span>
                )}
            </div>

            {/* Nav */}
            <nav className="flex-1 overflow-y-auto px-2 py-3 space-y-0.5">
                {modules.map(mod => (
                    <SidebarItem key={mod.id} item={mod} collapsed={collapsed} />
                ))}
            </nav>

            {/* User */}
            <div
                className="border-t px-3 py-3 flex-shrink-0"
                style={{ borderColor: 'var(--sidebar-border)' }}
            >
                <div className="flex items-center gap-2">
                    <div
                        className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full text-white text-xs font-semibold"
                        style={{ backgroundColor: 'var(--sidebar-accent)' }}
                    >
                        {auth.user.name.charAt(0).toUpperCase()}
                    </div>
                    {!collapsed && (
                        <div className="flex-1 min-w-0">
                            <p className="text-xs font-medium text-white truncate">{auth.user.name}</p>
                            <p className="text-xs truncate" style={{ color: 'var(--sidebar-text)' }}>
                                {auth.user.empresa?.nombre_comercial ?? auth.user.empresa?.razon_social}
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </aside>
    );
}

export default function AppLayout({ children, title }: AppLayoutProps) {
    const { auth, modules = [] } = usePage<PageProps>().props;
    const [collapsed, setCollapsed] = useState(false);
    const sidebarWidth = collapsed ? 64 : 256;

    const logout = () => {
        router.post(route('logout'));
    };

    return (
        <ColorPaletteProvider>
            <div className="min-h-screen" style={{ backgroundColor: 'var(--color-bg)' }}>
                <Sidebar modules={modules} collapsed={collapsed} />

                {/* Main */}
                <div
                    className="flex flex-col min-h-screen transition-all duration-200"
                    style={{ marginLeft: `${sidebarWidth}px` }}
                >
                    {/* Header */}
                    <header
                        className="sticky top-0 z-20 flex items-center gap-4 px-6 py-3 border-b"
                        style={{
                            backgroundColor: 'var(--color-surface)',
                            borderColor: 'var(--color-border)',
                        }}
                    >
                        <button
                            onClick={() => setCollapsed(c => !c)}
                            className="rounded p-1.5 transition-colors duration-150"
                            style={{ color: 'var(--color-text-muted)' }}
                        >
                            {collapsed ? <Menu size={20} /> : <X size={20} />}
                        </button>

                        {title && (
                            <h1 className="flex-1 text-sm font-semibold" style={{ color: 'var(--color-text)' }}>
                                {title}
                            </h1>
                        )}
                        {!title && <span className="flex-1" />}

                        <div className="flex items-center gap-3">
                            <div className="flex items-center gap-2">
                                <User size={16} style={{ color: 'var(--color-text-muted)' }} />
                                <span className="text-sm" style={{ color: 'var(--color-text)' }}>
                                    {auth.user.name}
                                </span>
                                {auth.user.empresa && (
                                    <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                        · {auth.user.empresa.nombre_comercial ?? auth.user.empresa.razon_social}
                                    </span>
                                )}
                            </div>
                            <button
                                onClick={logout}
                                className="flex items-center gap-1.5 rounded px-2 py-1.5 text-xs transition-colors duration-150"
                                style={{ color: 'var(--color-text-muted)' }}
                            >
                                <LogOut size={14} />
                                Salir
                            </button>
                        </div>
                    </header>

                    {/* Content */}
                    <main className="flex-1 p-6">
                        {children}
                    </main>
                </div>

                <ColorPaletteEditor />
                <Toaster position="top-right" />
            </div>
        </ColorPaletteProvider>
    );
}
