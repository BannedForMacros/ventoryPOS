import React from 'react';

interface PageHeaderProps {
    title: string;
    subtitle?: string;
    actions?: React.ReactNode;
}

export default function PageHeader({ title, subtitle, actions }: PageHeaderProps) {
    return (
        <div
            className="flex items-center justify-between pb-4 mb-4 border-b"
            style={{ borderColor: 'var(--color-border)' }}
        >
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
            {actions && <div className="flex items-center gap-2">{actions}</div>}
        </div>
    );
}
