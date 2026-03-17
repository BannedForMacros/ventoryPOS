import { useEffect, useRef, useState } from 'react';

interface TabItem<T extends string> {
    value: T;
    label: string;
}

interface TabsProps<T extends string> {
    tabs: TabItem<T>[];
    value: T;
    onChange: (value: T) => void;
    error?: string;
}

export default function Tabs<T extends string>({ tabs, value, onChange, error }: TabsProps<T>) {
    const containerRef = useRef<HTMLDivElement>(null);
    const buttonRefs = useRef<(HTMLButtonElement | null)[]>([]);
    const [indicator, setIndicator] = useState({ left: 0, width: 0, ready: false });

    useEffect(() => {
        const activeIndex = tabs.findIndex(t => t.value === value);
        const btn = buttonRefs.current[activeIndex];
        const container = containerRef.current;
        if (btn && container) {
            const containerRect = container.getBoundingClientRect();
            const btnRect = btn.getBoundingClientRect();
            setIndicator({
                left: btnRect.left - containerRect.left,
                width: btnRect.width,
                ready: true,
            });
        }
    }, [value, tabs]);

    return (
        <div>
            <div
                ref={containerRef}
                className="inline-flex rounded-xl p-1 gap-1 relative"
                style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)' }}
            >
                {/* Sliding indicator */}
                {indicator.ready && (
                    <div
                        className="absolute top-1 bottom-1 rounded-lg pointer-events-none"
                        style={{
                            left: indicator.left + 'px',
                            width: indicator.width + 'px',
                            backgroundColor: 'var(--color-surface)',
                            boxShadow: '0 1px 4px rgba(0,0,0,0.08)',
                            transition: 'left 200ms ease-in-out, width 200ms ease-in-out',
                        }}
                    />
                )}

                {tabs.map((tab, idx) => {
                    const active = tab.value === value;
                    return (
                        <button
                            key={tab.value}
                            ref={el => { buttonRefs.current[idx] = el; }}
                            type="button"
                            onClick={() => onChange(tab.value)}
                            className="relative z-10 rounded-lg px-5 py-2 text-sm font-medium"
                            style={{
                                color: active ? 'var(--color-primary)' : 'var(--color-text-muted)',
                                fontWeight: active ? 600 : 400,
                                transition: 'color 200ms ease-in-out, font-weight 200ms ease-in-out',
                                backgroundColor: 'transparent',
                            }}
                        >
                            {tab.label}
                        </button>
                    );
                })}
            </div>
            {error && <p className="mt-1 text-xs" style={{ color: 'var(--color-danger)' }}>{error}</p>}
        </div>
    );
}
