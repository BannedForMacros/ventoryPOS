import React from 'react';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface PageHeaderProps {
    title: React.ReactNode;
    subtitle?: string;
    actions?: React.ReactNode;
    backHref?: string;
    /** Ícono lucide del módulo (~20-22px). Se muestra en un chip degradado a la izquierda del título. */
    icon?: React.ReactNode;
}

export default function PageHeader({ title, subtitle, actions, backHref, icon }: PageHeaderProps) {
    return (
        <div
            className="flex flex-wrap items-center justify-between gap-3 pb-4 mb-5 border-b"
            style={{ borderColor: 'var(--color-border)' }}
        >
            <div className="flex items-center gap-3.5 min-w-0">
                {backHref && (
                    <Link
                        href={backHref}
                        className="flex items-center justify-center h-8 w-8 rounded-lg transition-colors duration-150 flex-shrink-0"
                        style={{ color: 'var(--color-text-muted)', backgroundColor: 'transparent' }}
                        onMouseEnter={e => {
                            e.currentTarget.style.backgroundColor = 'var(--color-border)';
                            e.currentTarget.style.color = 'var(--color-text)';
                        }}
                        onMouseLeave={e => {
                            e.currentTarget.style.backgroundColor = 'transparent';
                            e.currentTarget.style.color = 'var(--color-text-muted)';
                        }}
                    >
                        <ArrowLeft size={18} />
                    </Link>
                )}
                {icon && (
                    <span
                        className="flex h-11 w-11 items-center justify-center rounded-2xl flex-shrink-0"
                        style={{
                            color: 'var(--color-primary)',
                            background: 'linear-gradient(135deg, color-mix(in srgb, var(--color-primary) 14%, var(--color-surface)) 0%, color-mix(in srgb, var(--color-primary) 5%, var(--color-surface)) 100%)',
                            border: '1px solid color-mix(in srgb, var(--color-primary) 20%, transparent)',
                            boxShadow: '0 1px 3px 0 color-mix(in srgb, var(--color-primary) 12%, transparent)',
                        }}
                    >
                        {icon}
                    </span>
                )}
                <div className="min-w-0">
                    <h1 className="text-2xl font-bold tracking-tight truncate" style={{ color: 'var(--color-text)' }}>
                        {title}
                    </h1>
                    {subtitle && (
                        <p className="mt-0.5 text-sm" style={{ color: 'var(--color-text-muted)' }}>
                            {subtitle}
                        </p>
                    )}
                </div>
            </div>
            {actions && <div className="flex items-center gap-2 flex-shrink-0">{actions}</div>}
        </div>
    );
}
