import React, { useId } from 'react';

interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
    label?: string;
    error?: string;
    hint?: string;
}

export default function Input({ label, error, hint, required, className = '', ...props }: InputProps) {
    const inputId = useId();

    return (
        <div className="flex flex-col gap-1.5 w-full">
            {/* Label */}
            {label && (
                <label 
                    htmlFor={inputId}
                    className="text-sm font-medium transition-colors" 
                    style={{ color: error ? 'var(--color-danger)' : 'var(--color-text)' }}
                >
                    {label}
                    {required && <span className="ml-1 text-red-500">*</span>}
                </label>
            )}

            {/* Input Wrapper */}
            <div className="relative w-full">
                <input
                    id={inputId}
                    className={`
                        w-full rounded-xl border-2 px-4 py-2.5 text-sm outline-none 
                        transition-all duration-200 ease-in-out
                        disabled:cursor-not-allowed disabled:opacity-50
                        hover:opacity-90 hover:border-[var(--color-primary)]
                        focus:border-[var(--color-primary)] focus:shadow-[0_0_0_3px_color-mix(in_srgb,var(--color-primary)_20%,transparent)]
                        ${error ? '!border-[var(--color-danger)] focus:!shadow-[0_0_0_3px_color-mix(in_srgb,var(--color-danger)_20%,transparent)]' : 'border-[var(--color-border)]'}
                        ${className}
                    `}
                    style={{
                        backgroundColor: 'var(--color-surface)',
                        color: 'var(--color-text)',
                    }}
                    required={required}
                    {...props}
                />
            </div>

            {/* Mensajes de Error o Ayuda */}
            {error ? (
                <p className="text-xs font-medium animate-in fade-in slide-in-from-top-1" style={{ color: 'var(--color-danger)' }}>
                    {error}
                </p>
            ) : hint ? (
                <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                    {hint}
                </p>
            ) : null}
        </div>
    );
}