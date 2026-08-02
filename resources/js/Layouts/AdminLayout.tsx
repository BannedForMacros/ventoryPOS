import React from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { Toaster } from 'react-hot-toast';
import { Building2, LogOut, ShieldCheck } from 'lucide-react';
import type { PageProps } from '@/types';
import { ColorPaletteProvider } from '@/Components/ColorPaletteProvider';
import RouterLoadingOverlay from '@/Components/RouterLoadingOverlay';

interface AdminLayoutProps {
    children: React.ReactNode;
    title?: string;
}

/**
 * Layout del panel del superadmin (/admin). Deliberadamente mínimo: aquí no
 * hay sidebar de módulos ni turno ni campanitas — el proveedor solo administra
 * empresas y sus usuarios. La navegación de tenant (AppLayout) ni se toca.
 */
export default function AdminLayout({ children, title }: AdminLayoutProps) {
    const { auth } = usePage<PageProps>().props;
    const { url } = usePage();

    const logout = () => router.post(route('logout'));

    return (
        <ColorPaletteProvider>
            <div className="min-h-screen" style={{ backgroundColor: 'var(--color-bg)' }}>
                <header
                    className="sticky top-0 z-20 border-b backdrop-blur-md"
                    style={{
                        backgroundColor: 'color-mix(in srgb, var(--color-surface) 92%, transparent)',
                        borderColor: 'var(--color-border)',
                    }}
                >
                    <div className="flex items-center gap-3 px-4 sm:px-6 py-2.5">
                        <Link
                            href={route('admin.empresas.index')}
                            className="flex items-center gap-2 select-none transition-opacity duration-200 hover:opacity-80"
                        >
                            <img src="/logo-full.svg" alt="ventoryPOS" className="h-7 w-auto" draggable={false} />
                            <span className="hidden md:inline text-base font-semibold tracking-tight" style={{ color: 'var(--color-text)' }}>
                                ventoryPOS
                            </span>
                        </Link>

                        <span
                            className="flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                            style={{
                                color: 'var(--color-primary)',
                                backgroundColor: 'color-mix(in srgb, var(--color-primary) 12%, transparent)',
                            }}
                        >
                            <ShieldCheck size={13} />
                            Administración
                        </span>

                        <nav className="ml-2 flex items-center gap-1">
                            <Link
                                href={route('admin.empresas.index')}
                                className="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition-colors duration-150"
                                style={{
                                    color: url.startsWith('/admin/empresas') ? 'var(--color-primary)' : 'var(--color-text-muted)',
                                    backgroundColor: url.startsWith('/admin/empresas')
                                        ? 'color-mix(in srgb, var(--color-primary) 10%, transparent)'
                                        : 'transparent',
                                }}
                            >
                                <Building2 size={15} />
                                Empresas
                            </Link>
                        </nav>

                        <span className="flex-1" />

                        <div className="hidden sm:flex flex-col items-end leading-tight">
                            <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>
                                {auth.user.name}
                            </span>
                            <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                Superadmin
                            </span>
                        </div>
                        <button
                            onClick={logout}
                            title="Cerrar sesión"
                            className="flex h-10 w-10 items-center justify-center rounded-lg transition-all duration-200 hover:bg-black/5 active:scale-95"
                            style={{ color: 'var(--color-danger)' }}
                        >
                            <LogOut size={18} />
                        </button>
                    </div>
                    {title && (
                        <div className="px-4 sm:px-6 pb-2 -mt-1 text-xs font-medium truncate" style={{ color: 'var(--color-text-muted)' }}>
                            {title}
                        </div>
                    )}
                </header>

                <main className="p-4 sm:p-6">{children}</main>

                <Toaster position="top-right" />
                <RouterLoadingOverlay />
            </div>
        </ColorPaletteProvider>
    );
}
