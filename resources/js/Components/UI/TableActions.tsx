import React, { useState } from 'react';
import { Eye, Pencil, Trash2, LucideIcon } from 'lucide-react';

// ── Tipos ──────────────────────────────────────────────────────────────────────
type ActionVariant = 'view' | 'edit' | 'delete' | 'custom';

interface ActionConfig {
    icon: LucideIcon;
    label: string;
    hoverColor: string;
    hoverBg: string;
}

interface TableActionButtonProps {
    variant: ActionVariant;
    onClick: () => void;
    disabled?: boolean;
    icon?: LucideIcon;
    label?: string;
}

interface TableActionsProps {
    onView?: () => void;
    onEdit?: () => void;
    onDelete?: () => void;
    extra?: TableActionButtonProps[];
    disableView?: boolean;
    disableEdit?: boolean;
    disableDelete?: boolean;
}

// ── Config por variante ────────────────────────────────────────────────────────
const ACTION_CONFIG: Record<Exclude<ActionVariant, 'custom'>, ActionConfig> = {
    view: {
        icon: Eye,
        label: 'Ver',
        hoverColor: 'var(--color-primary)',
        hoverBg: 'color-mix(in srgb, var(--color-primary) 10%, transparent)',
    },
    edit: {
        icon: Pencil,
        label: 'Editar',
        hoverColor: '#f59e0b',
        hoverBg: 'color-mix(in srgb, #f59e0b 10%, transparent)',
    },
    delete: {
        icon: Trash2,
        label: 'Eliminar',
        hoverColor: 'var(--color-danger)',
        hoverBg: 'color-mix(in srgb, var(--color-danger) 10%, transparent)',
    },
};

// ── Botón individual ───────────────────────────────────────────────────────────
function ActionButton({ variant, onClick, disabled = false, icon: CustomIcon, label: customLabel }: TableActionButtonProps) {
    const [hovered, setHovered] = useState(false);
    const [tooltipVisible, setTooltipVisible] = useState(false);

    const config = variant !== 'custom' ? ACTION_CONFIG[variant] : null;
    const Icon = CustomIcon ?? config?.icon ?? Eye;
    const label = customLabel ?? config?.label ?? '';
    const hoverColor = config?.hoverColor ?? 'var(--color-primary)';
    const hoverBg = config?.hoverBg ?? 'color-mix(in srgb, var(--color-primary) 10%, transparent)';

    return (
        <div className="relative inline-flex">
            <button
                onClick={onClick}
                disabled={disabled}
                onMouseEnter={() => { setHovered(true); setTooltipVisible(true); }}
                onMouseLeave={() => { setHovered(false); setTooltipVisible(false); }}
                className="relative flex items-center justify-center rounded-lg border p-1.5 transition-all duration-150 disabled:cursor-not-allowed disabled:opacity-40"
                style={{
                    borderColor: hovered ? hoverColor : 'var(--color-border)',
                    color: hovered ? hoverColor : 'var(--color-text-muted)',
                    backgroundColor: hovered ? hoverBg : 'transparent',
                }}
            >
                <Icon size={15} strokeWidth={1.8} />
            </button>

            {/* Tooltip */}
            {tooltipVisible && (
                <div
                    className="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 rounded-md px-2 py-1 text-xs font-medium text-white whitespace-nowrap z-50"
                    style={{ backgroundColor: 'var(--color-text)' }}
                >
                    {label}
                    {/* Arrow */}
                    <span
                        className="absolute left-1/2 -translate-x-1/2 -bottom-1 border-4 border-transparent"
                        style={{ borderTopColor: 'var(--color-text)' }}
                    />
                </div>
            )}
        </div>
    );
}

// ── TableActions ───────────────────────────────────────────────────────────────
export default function TableActions({
    onView,
    onEdit,
    onDelete,
    extra = [],
    disableView = false,
    disableEdit = false,
    disableDelete = false,
}: TableActionsProps) {
    return (
        <div className="flex items-center gap-1.5">
            {onView && (
                <ActionButton variant="view" onClick={onView} disabled={disableView} />
            )}
            {onEdit && (
                <ActionButton variant="edit" onClick={onEdit} disabled={disableEdit} />
            )}
            {onDelete && (
                <ActionButton variant="delete" onClick={onDelete} disabled={disableDelete} />
            )}
            {extra.map((action, i) => (
                <ActionButton key={i} {...action} />
            ))}
        </div>
    );
}