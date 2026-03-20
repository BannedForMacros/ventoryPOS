import React from 'react';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface PageHeaderProps {
    title: React.ReactNode;
    subtitle?: string;
    actions?: React.ReactNode;
    backHref?: string;
}

export default function PageHeader({ title, subtitle, actions, backHref }: PageHeaderProps) {
    return (
        <div
            className="flex items-center justify-between pb-4 mb-4 border-b"
            style={{ borderColor: 'var(--color-border)' }}
        >
            <div className="flex items-center gap-3">
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
                <div>
                    <h1 className="text-xl font-semibold" style={{ color: 'var(--color-text)' }}>
                        {title}
                    </h1>
                    {subtitle && (
                        <p className="mt-0.5 text-sm" style={{ color: 'var(--color-text-muted)' }}>
                            {subtitle}
                        </p>
                    )}
                </div>
            </div>
            {actions && <div className="flex items-center gap-2">{actions}</div>}
        </div>
    );
}
