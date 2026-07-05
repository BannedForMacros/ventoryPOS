import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { ArrowLeft, Plus, Trash2, CheckCircle2, Lock, RefreshCw } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Badge from '@/Components/UI/Badge';
import Modal from '@/Components/UI/Modal';
import type { PageProps } from '@/types';

interface Item {
    id: number;
    seccion: 'favor' | 'contra';
    categoria: string;
    descripcion: string;
    monto: string;
    es_manual: boolean;
    conciliado: boolean;
}

interface Balance {
    id: number;
    fecha: string;
    estado: 'borrador' | 'confirmado';
    total_favor: string;
    total_contra: string;
    balance_neto: string;
    balance_anterior: string | null;
    diferencia: string | null;
    gastos_dia: string;
    utilidad_real: string | null;
    items: Item[];
    user?: { name: string } | null;
}

interface Gasto {
    id: number;
    monto: string;
    comentario: string | null;
    tipo?: { nombre: string } | null;
    concepto?: { nombre: string } | null;
}

interface Props extends PageProps {
    balance: Balance;
    gastos: Gasto[];
}

const money = (v: unknown) => `S/ ${Number(v ?? 0).toFixed(2)}`;

const CATEGORIA_LABEL: Record<string, string> = {
    cuenta_bancaria:    'Cuenta',
    efectivo:           'Efectivo',
    stock:              'Stock',
    cxc:                'Por cobrar',
    adelanto_proveedor: 'Adelanto prov.',
    prestamo_otorgado:  'Préstamo otorgado',
    otro_favor:         'Otro',
    cxp:                'Proveedores',
    anticipo_cliente:   'Anticipos',
    deuda:              'Deuda',
    personal:           'Personal',
    otro_contra:        'Otro',
};

export default function BalanceDiarioDetalle({ balance, gastos }: Props) {
    const { flash } = usePage<Props>().props;
    const editable = balance.estado === 'borrador';

    const [agregandoEn, setAgregandoEn]   = useState<'favor' | 'contra' | null>(null);
    const [confirmando, setConfirmando]   = useState(false);
    const [saving, setSaving]             = useState(false);
    const [errors, setErrors]             = useState<Record<string, string>>({});
    const [formLinea, setFormLinea]       = useState({ descripcion: '', monto: '' });
    // Montos en edición local (se envían al perder foco / Enter)
    const [montosEdit, setMontosEdit]     = useState<Record<number, string>>({});

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    const fechaLabel = new Date(balance.fecha.slice(0, 10) + 'T00:00:00')
        .toLocaleDateString('es-PE', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });

    function guardarMonto(item: Item) {
        const valor = montosEdit[item.id];
        if (valor === undefined || Number(valor) === Number(item.monto)) return;
        router.put(route('finanzas.balance.items.update', item.id), { monto: valor } as any, {
            preserveScroll: true,
            onSuccess: () => setMontosEdit(m => { const c = { ...m }; delete c[item.id]; return c; }),
        });
    }

    function toggleConciliado(item: Item) {
        router.put(route('finanzas.balance.items.update', item.id), { conciliado: !item.conciliado } as any, {
            preserveScroll: true,
        });
    }

    function eliminarLinea(item: Item) {
        router.delete(route('finanzas.balance.items.destroy', item.id), { preserveScroll: true });
    }

    function agregarLinea() {
        if (!agregandoEn) return;
        setSaving(true);
        router.post(route('finanzas.balance.items.store', balance.id), {
            seccion: agregandoEn, ...formLinea,
        } as any, {
            preserveScroll: true,
            onSuccess: () => { setAgregandoEn(null); setFormLinea({ descripcion: '', monto: '' }); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    function confirmarBalance() {
        setSaving(true);
        router.post(route('finanzas.balance.confirmar', balance.id), {}, {
            onSuccess: () => { setConfirmando(false); setSaving(false); },
            onError:   () => setSaving(false),
        });
    }

    function renderSeccion(seccion: 'favor' | 'contra') {
        const items = balance.items.filter(i => i.seccion === seccion);
        const esFavor = seccion === 'favor';

        return (
            <div
                className="rounded-2xl overflow-hidden flex-1 min-w-0"
                style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}
            >
                <div
                    className="flex items-center justify-between px-4 py-3"
                    style={{
                        backgroundColor: esFavor
                            ? 'color-mix(in srgb, var(--color-success, #16a34a) 12%, var(--color-bg))'
                            : 'color-mix(in srgb, var(--color-danger) 12%, var(--color-bg))',
                    }}
                >
                    <h3 className="font-bold text-sm uppercase tracking-wider"
                        style={{ color: esFavor ? 'var(--color-success)' : 'var(--color-danger)' }}>
                        {esFavor ? 'A favor' : 'En contra'}
                    </h3>
                    <span className="font-bold" style={{ color: esFavor ? 'var(--color-success)' : 'var(--color-danger)' }}>
                        {money(esFavor ? balance.total_favor : balance.total_contra)}
                    </span>
                </div>

                <div className="divide-y" style={{ borderColor: 'var(--color-border)' }}>
                    {items.map(item => (
                        <div key={item.id} className="flex items-center gap-2 px-3 py-2">
                            {/* Conciliado: el "OK" del Excel */}
                            <button
                                onClick={() => editable && toggleConciliado(item)}
                                disabled={!editable}
                                title={item.conciliado ? 'Conciliado' : 'Marcar como conciliado (OK)'}
                                className="flex-shrink-0 disabled:cursor-default"
                            >
                                <CheckCircle2
                                    size={17}
                                    style={{ color: item.conciliado ? 'var(--color-success)' : 'var(--color-border)' }}
                                />
                            </button>

                            <div className="flex-1 min-w-0">
                                <p className="text-sm font-medium truncate" style={{ color: 'var(--color-text)' }}>
                                    {item.descripcion}
                                </p>
                                <p className="text-[10px] uppercase tracking-wider" style={{ color: 'var(--color-text-muted)' }}>
                                    {CATEGORIA_LABEL[item.categoria] ?? item.categoria}{item.es_manual ? ' · manual' : ' · automático'}
                                </p>
                            </div>

                            {item.es_manual && editable ? (
                                <input
                                    type="number"
                                    step="0.01"
                                    className="w-28 text-right text-sm rounded-lg px-2 py-1 border outline-none font-semibold"
                                    style={{
                                        borderColor: 'var(--color-border)',
                                        backgroundColor: 'var(--color-bg)',
                                        color: 'var(--color-text)',
                                    }}
                                    value={montosEdit[item.id] ?? String(Number(item.monto))}
                                    onChange={e => setMontosEdit(m => ({ ...m, [item.id]: e.target.value }))}
                                    onBlur={() => guardarMonto(item)}
                                    onKeyDown={e => { if (e.key === 'Enter') (e.target as HTMLInputElement).blur(); }}
                                />
                            ) : (
                                <span className="text-sm font-semibold whitespace-nowrap">{money(item.monto)}</span>
                            )}

                            {item.es_manual && editable && (item.categoria === 'otro_favor' || item.categoria === 'otro_contra') && (
                                <button onClick={() => eliminarLinea(item)} title="Eliminar línea"
                                    className="p-1 rounded hover:bg-black/5 flex-shrink-0"
                                    style={{ color: 'var(--color-danger)' }}>
                                    <Trash2 size={14} />
                                </button>
                            )}
                        </div>
                    ))}
                    {items.length === 0 && (
                        <p className="text-sm text-center py-6" style={{ color: 'var(--color-text-muted)' }}>Sin líneas</p>
                    )}
                </div>

                {editable && (
                    <div className="px-3 py-2 flex items-center justify-between gap-2" style={{ borderTop: '1px solid var(--color-border)' }}>
                        <button
                            onClick={() => { setErrors({}); setFormLinea({ descripcion: '', monto: '' }); setAgregandoEn(seccion); }}
                            className="flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-lg hover:bg-black/5"
                            style={{ color: 'var(--color-primary)' }}
                        >
                            <Plus size={13} />Agregar línea manual
                        </button>
                        {esFavor && (
                            <button
                                onClick={() => router.visit(route('finanzas.tesoreria.index'))}
                                className="text-[11px] underline hover:opacity-80"
                                style={{ color: 'var(--color-text-muted)' }}
                                title="El efectivo y las cuentas se calculan solos; si no cuadran, registra un ajuste con motivo"
                            >
                                ¿Efectivo no cuadra? Ajustar en Tesorería
                            </button>
                        )}
                    </div>
                )}
            </div>
        );
    }

    const stats: { label: string; valor: string | null; color?: string; signo?: boolean }[] = [
        { label: 'Balance hoy',  valor: balance.balance_neto },
        { label: 'Balance ayer', valor: balance.balance_anterior },
        { label: 'Diferencia',   valor: balance.diferencia, signo: true },
        { label: 'Gastos del día', valor: balance.gastos_dia, color: 'var(--color-warning)' },
        { label: 'Utilidad real',  valor: balance.utilidad_real, signo: true },
    ];

    return (
        <AppLayout title={`Balance ${balance.fecha}`}>
            {/* Cabecera */}
            <div className="flex flex-wrap items-center justify-between gap-3 mb-5">
                <div className="flex items-center gap-3">
                    <button onClick={() => router.visit(route('finanzas.balance.index'))}
                        className="p-2 rounded-lg hover:bg-black/5" style={{ color: 'var(--color-text-muted)' }}>
                        <ArrowLeft size={18} />
                    </button>
                    <div>
                        <h1 className="text-xl font-bold capitalize" style={{ color: 'var(--color-text)' }}>{fechaLabel}</h1>
                        <div className="flex items-center gap-2 mt-0.5">
                            <Badge variant={editable ? 'warning' : 'success'}>
                                {editable ? 'Borrador — editable' : 'Confirmado'}
                            </Badge>
                            {!editable && <Lock size={13} style={{ color: 'var(--color-text-muted)' }} />}
                        </div>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    {editable && (
                        <>
                            <Button variant="ghost" onClick={() => router.reload()}>
                                <RefreshCw size={14} className="mr-1" />Recalcular
                            </Button>
                            <Button onClick={() => setConfirmando(true)}>
                                <CheckCircle2 size={15} className="mr-1" />Confirmar balance
                            </Button>
                        </>
                    )}
                </div>
            </div>

            {/* Resumen */}
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-5">
                {stats.map(s => {
                    const n = s.valor !== null ? Number(s.valor) : null;
                    const color = s.color ?? (s.signo && n !== null
                        ? (n >= 0 ? 'var(--color-success)' : 'var(--color-danger)')
                        : 'var(--color-text)');
                    return (
                        <div key={s.label} className="rounded-2xl px-4 py-3"
                            style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                            <p className="text-[10px] font-semibold uppercase tracking-wider" style={{ color: 'var(--color-text-muted)' }}>
                                {s.label}
                            </p>
                            <p className="text-lg font-bold" style={{ color }}>
                                {n === null ? '—' : `${s.signo && n > 0 ? '+' : ''}${money(n)}`}
                            </p>
                        </div>
                    );
                })}
            </div>

            {/* Secciones favor / contra */}
            <div className="flex flex-col lg:flex-row gap-4 items-start">
                {renderSeccion('favor')}
                {renderSeccion('contra')}

                {/* Panel gastos del día */}
                <div className="w-full lg:w-72 flex-shrink-0 rounded-2xl overflow-hidden"
                    style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-surface)' }}>
                    <div className="flex items-center justify-between px-4 py-3"
                        style={{ backgroundColor: 'color-mix(in srgb, var(--color-warning, #f59e0b) 12%, var(--color-bg))' }}>
                        <h3 className="font-bold text-sm uppercase tracking-wider" style={{ color: 'var(--color-warning)' }}>
                            Gastos del día
                        </h3>
                        <span className="font-bold" style={{ color: 'var(--color-warning)' }}>{money(balance.gastos_dia)}</span>
                    </div>
                    <div className="divide-y" style={{ borderColor: 'var(--color-border)' }}>
                        {gastos.length === 0 ? (
                            <p className="text-sm text-center py-6" style={{ color: 'var(--color-text-muted)' }}>Sin gastos este día</p>
                        ) : gastos.map(g => (
                            <div key={g.id} className="px-3 py-2 flex justify-between items-start text-sm">
                                <div className="min-w-0">
                                    <p className="font-medium truncate">{g.concepto?.nombre ?? g.tipo?.nombre ?? 'Gasto'}</p>
                                    {g.comentario && (
                                        <p className="text-xs truncate" style={{ color: 'var(--color-text-muted)' }}>{g.comentario}</p>
                                    )}
                                </div>
                                <span className="font-semibold whitespace-nowrap ml-2">{money(g.monto)}</span>
                            </div>
                        ))}
                    </div>
                    <p className="px-3 py-2 text-[11px]" style={{ color: 'var(--color-text-muted)', borderTop: '1px solid var(--color-border)' }}>
                        Utilidad real = (balance hoy − balance ayer) + gastos del día
                    </p>
                </div>
            </div>

            {/* Modal agregar línea manual */}
            <Modal isOpen={agregandoEn !== null} onClose={() => setAgregandoEn(null)}
                title={`Nueva línea ${agregandoEn === 'favor' ? 'A FAVOR' : 'EN CONTRA'}`} size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setAgregandoEn(null)}>Cancelar</Button>
                        <Button onClick={agregarLinea} disabled={saving}>{saving ? 'Guardando...' : 'Agregar'}</Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <div className="rounded-xl px-3 py-2 text-xs leading-relaxed"
                        style={{ backgroundColor: 'color-mix(in srgb, var(--color-warning, #f59e0b) 10%, var(--color-bg))', color: 'var(--color-text)' }}>
                        <strong>Usa esto solo para casos puntuales.</strong> Lo recurrente debe ir en su módulo
                        para no perder trazabilidad: préstamos y cuotas (ej. la moto de un trabajador) →
                        <em> Deudas y préstamos</em>; dinero adelantado → <em>Anticipos/Adelantos</em>;
                        dinero en cuentas → <em>Tesorería</em>. Esta línea quedará registrada en auditoría
                        con tu usuario.
                    </div>
                    <Input label="Descripción" required placeholder='Ej: "Depósito Oscar Alberto", "16 fierros 3/4"'
                        value={formLinea.descripcion}
                        onChange={e => setFormLinea(f => ({ ...f, descripcion: e.target.value }))}
                        error={errors.descripcion}
                    />
                    <Input label="Monto" required type="number" min="0" step="0.01"
                        value={formLinea.monto}
                        onChange={e => setFormLinea(f => ({ ...f, monto: e.target.value }))}
                        error={errors.monto}
                    />
                </div>
            </Modal>

            {/* Modal confirmar */}
            <Modal isOpen={confirmando} onClose={() => setConfirmando(false)} title="Confirmar balance del día" size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setConfirmando(false)}>Cancelar</Button>
                        <Button onClick={confirmarBalance} disabled={saving}>{saving ? 'Confirmando...' : 'Sí, confirmar'}</Button>
                    </>
                }
            >
                <div className="space-y-2 text-sm" style={{ color: 'var(--color-text)' }}>
                    <p>Al confirmar, el balance queda <strong>inmutable</strong> y será la referencia ("balance de ayer") para el siguiente día.</p>
                    <div className="rounded-xl px-3 py-2 space-y-1"
                        style={{ backgroundColor: 'color-mix(in srgb, var(--color-primary) 8%, var(--color-bg))' }}>
                        <div className="flex justify-between"><span>Balance del día</span><strong>{money(balance.balance_neto)}</strong></div>
                        {balance.utilidad_real !== null && (
                            <div className="flex justify-between">
                                <span>Utilidad real</span>
                                <strong style={{ color: Number(balance.utilidad_real) >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }}>
                                    {money(balance.utilidad_real)}
                                </strong>
                            </div>
                        )}
                    </div>
                </div>
            </Modal>
        </AppLayout>
    );
}
