import React from 'react';

type Variant = 'primary' | 'success' | 'danger' | 'warning' | 'secondary' | 'info';

interface BadgeProps {
    variant?: Variant;
    children: React.ReactNode;
    className?: string;
}

const variantStyles: Record<Variant, { backgroundColor: string; color: string }> = {
    primary: { backgroundColor: 'rgba(59,130,246,0.1)', color: 'var(--color-primary)' },
    success: { backgroundColor: 'rgba(16,185,129,0.1)', color: 'var(--color-success)' },
    danger: { backgroundColor: 'rgba(239,68,68,0.1)', color: 'var(--color-danger)' },
    warning: { backgroundColor: 'rgba(245,158,11,0.1)', color: 'var(--color-warning)' },
    secondary: { backgroundColor: 'rgba(239,68,68,0.1)', color: 'var(--color-danger)' },
    info: { backgroundColor: 'rgba(99,102,241,0.1)', color: 'var(--color-primary)' },
};

export default function Badge({ variant = 'secondary', children, className = '' }: BadgeProps) {
    return (
        <span
            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${className}`}
            style={variantStyles[variant]}
        >
            {children}
        </span>
    );
}
