import { useEffect, useMemo, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { ClipboardCheck, AlertTriangle, Coins, CreditCard, Check, ShieldCheck } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import Modal from '@/Components/UI/Modal';
import Tabs from '@/Components/UI/Tabs';
import Callout from '@/Components/UI/Callout';
import type { PageProps } from '@/types';

interface ArqueoMetodo {
    id: number;
    metodo_pago_id: number;
    monto_declarado: string;
    metodo_pago?: { nombre: string } | null;
}

interface ConsolidacionItem {
    id: number;
    etiqueta: string;
    declarado: string | null;
    esperado: string | null;
    contado: string;
    diferencia: string | null;
}

interface Consolidacion {
    id: number;
    efectivo_contado: string;
    diferencia_vs_esperado: string | null;
    user?: { name: string } | null;
    items: ConsolidacionItem[];
}

interface TurnoRow extends Record<string, unknown> {
    id: number;
    fecha_cierre: string | null;
    monto_apertura: string;
    monto_caja_chica: string;
    monto_cierre_declarado: string | null;
    monto_cierre_esperado: string | null;
    diferencia: string | null;
    caja?: { nombre: string } | null;
    user?: { name: string } | null;
    consolidacion?: Consolidacion | null;
    arqueo_metodos: ArqueoMetodo[];
}

interface EsperadoMetodo { metodo_pago_id: number; nombre: string; esperado: number; }

interface Paginado<T> { data: T[]; total: number; }

interface Props extends PageProps {
    turnos: Paginado<TurnoRow>;
    esperadosPorMetodo: Record<string, EsperadoMetodo[]>;
    estado: string;
    buscar?: string;
    requiereConsolidacion: boolean;
}

interface LineaConteo {
    metodo_pago_id: number | null; // null = efectivo
    etiqueta: string;
    declarado: number | null;
    esperado: number | null;
    contado: string;
}

const money = (v: unknown) => `S/ ${Number(v ?? 0).toFixed(2)}`;
const fdatetime = (s: string | null) => s ? new Date(s).toLocaleString('es-PE', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—';

export default function Consolidacion({ turnos, esperadosPorMetodo, estado, buscar, requiereConsolidacion }: Props) {
    const { flash } = usePage<Props>().props;
    const [consolidando, setConsolidando] = useState<TurnoRow | null>(null);
    const [lineas, setLineas]   = useState<LineaConteo[]>([]);
    const [saving, setSaving]   = useState(false);
    const [errors, setErrors]   = useState<Record<string, string>>({});
    const [obs, setObs]         = useState('');
    const [genDescuento, setGenDescuento] = useState(false);
    const [verDetalle, setVerDetalle]     = useState<TurnoRow | null>(null);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    /** Arma las líneas a verificar: Efectivo + cada método con declarado o esperado. */
    function abrir(t: TurnoRow) {
        const porMetodo = new Map<number, LineaConteo>();

        // Declarados por la cajera en el arqueo de métodos
        for (const am of t.arqueo_metodos) {
            porMetodo.set(am.metodo_pago_id, {
                metodo_pago_id: am.metodo_pago_id,
                etiqueta: am.metodo_pago?.nombre ?? `Método ${am.metodo_pago_id}`,
                declarado: Number(am.monto_declarado),
                esperado: null,
                contado: '',
            });
        }
        // Esperados por ventas del turno (puede haber método vendido pero no declarado)
        for (const em of (esperadosPorMetodo[String(t.id)] ?? [])) {
            const existente = porMetodo.get(em.metodo_pago_id);
            if (existente) {
                existente.esperado = em.esperado;
            } else {
                porMetodo.set(em.metodo_pago_id, {
                    metodo_pago_id: em.metodo_pago_id,
                    etiqueta: em.nombre,
                    declarado: null,
                    esperado: em.esperado,
                    contado: '',
                });
            }
        }

        setLineas([
            {
                metodo_pago_id: null,
                etiqueta: 'Efectivo (billetes + monedas)',
                declarado: t.monto_cierre_declarado !== null ? Number(t.monto_cierre_declarado) : null,
                esperado: t.monto_cierre_esperado !== null ? Number(t.monto_cierre_esperado) : null,
                contado: '',
            },
            ...Array.from(porMetodo.values()),
        ]);
        setObs(''); setGenDescuento(false); setErrors({});
        setConsolidando(t);
    }

    const faltanteTotal = useMemo(() => lineas.reduce((s, l) => {
        if (l.contado === '' || l.esperado === null) return s;
        const d = Number(l.contado) - l.esperado;
        return d < 0 ? s + Math.abs(d) : s;
    }, 0), [lineas]);

    const todasContadas = lineas.length > 0 && lineas.every(l => l.contado !== '');

    function submit() {
        if (!consolidando) return;
        setSaving(true);
        router.post(route('finanzas.consolidacion.consolidar', consolidando.id), {
            items: lineas.map(l => ({ metodo_pago_id: l.metodo_pago_id, contado: l.contado })),
            observacion: obs,
            generar_descuento: genDescuento,
        } as any, {
            onSuccess: () => { setConsolidando(null); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); toast.error(Object.values(errs)[0] as string ?? 'Error'); },
        });
    }

    const columns: Column<TurnoRow>[] = [
        { key: 'fecha_cierre', label: 'Cierre', sortable: true, render: (t) => <span className="text-sm">{fdatetime(t.fecha_cierre)}</span> },
        { key: 'caja', label: 'Caja', render: (t) => <span className="text-sm font-medium">{t.caja?.nombre ?? '—'}</span> },
        { key: 'user', label: 'Cajera', render: (t) => <span className="text-sm">{t.user?.name ?? '—'}</span> },
        { key: 'monto_caja_chica', label: 'Caja chica', align: 'right', render: (t) => <span className="text-sm">{money(t.monto_caja_chica)}</span> },
        {
            key: 'monto_cierre_declarado', label: 'Declaró (efectivo)', align: 'right',
            render: (t) => t.monto_cierre_declarado !== null
                ? <span className="font-semibold">{money(t.monto_cierre_declarado)}</span>
                : <Badge variant="secondary">Cierre rápido</Badge>,
        },
        {
            key: 'metodos', label: 'Otros métodos',
            render: (t) => t.arqueo_metodos.length > 0
                ? (
                    <div className="text-xs space-y-0.5">
                        {t.arqueo_metodos.map(m => (
                            <div key={m.id}>{m.metodo_pago?.nombre}: <strong>{money(m.monto_declarado)}</strong></div>
                        ))}
                    </div>
                )
                : <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>—</span>,
        },
        {
            key: 'estado_consolidacion', label: 'Consolidación',
            render: (t) => t.consolidacion
                ? (
                    <button onClick={() => setVerDetalle(t)} className="text-left hover:opacity-75">
                        <Badge variant="success">Consolidado</Badge>
                        <span className="block text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                            por {t.consolidacion.user?.name} · ver detalle
                        </span>
                    </button>
                )
                : <Badge variant="warning">Pendiente</Badge>,
        },
        {
            key: 'acciones', label: '',
            render: (t) => !t.consolidacion && (
                <button
                    onClick={() => abrir(t)}
                    className="p-1.5 rounded-lg hover:bg-black/5"
                    title="Consolidar"
                    style={{ color: 'var(--color-primary)' }}
                >
                    <ClipboardCheck size={15} />
                </button>
            ),
        },
    ];

    return (
        <AppLayout title="Consolidación de caja">
            <PageHeader
                icon={<ShieldCheck size={22} />}
                title="Consolidación de caja"
                subtitle="El supervisor verifica TODO el cierre: efectivo y cada método/cuenta. Su conteo es el que manda."
            />

            {!requiereConsolidacion && (
                <div className="mb-4 rounded-xl px-4 py-3 flex items-start gap-2 text-sm"
                    style={{ backgroundColor: 'color-mix(in srgb, var(--color-warning, #f59e0b) 12%, var(--color-bg))', color: 'var(--color-text)' }}>
                    <AlertTriangle size={16} className="flex-shrink-0 mt-0.5" style={{ color: 'var(--color-warning)' }} />
                    <span>
                        La consolidación está <strong>desactivada</strong>: el balance toma el cierre de la cajera directamente.
                        Actívala en <strong>Configuración → Empresas → Consolidación de caja</strong>. Si consolidas aquí igualmente, tu conteo reemplaza al de la cajera.
                    </span>
                </div>
            )}

            <div className="mb-5">
                <Tabs
                    tabs={[
                        { value: 'pendientes',   label: 'Pendientes de consolidar' },
                        { value: 'consolidados', label: 'Consolidados' },
                    ]}
                    value={estado}
                    onChange={(v) => router.get(route('finanzas.consolidacion.index'), { estado: v, buscar: buscar || undefined }, { preserveState: true, replace: true })}
                />
            </div>

            <Table data={turnos} columns={columns}
                searchPlaceholder="Buscar caja o cajera..."
                emptyMessage={estado === 'pendientes' ? 'No hay turnos cerrados pendientes de consolidar' : 'Aún no hay turnos consolidados'}
                initialSearch={buscar}
                onServerSearch={(t) => router.get(route('finanzas.consolidacion.index'),
                    { estado, buscar: t || undefined },
                    { preserveState: true, preserveScroll: true, replace: true })} />

            {/* Modal consolidar — TODAS las líneas */}
            <Modal isOpen={consolidando !== null} onClose={() => setConsolidando(null)}
                title={consolidando ? `Consolidar turno #${consolidando.id} — cajera: ${consolidando.user?.name ?? ''}` : ''} size="lg"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setConsolidando(null)}>Cancelar</Button>
                        <Button onClick={submit} disabled={saving || !todasContadas}
                            title={!todasContadas ? 'Ingresa el conteo de todas las líneas' : undefined}>
                            {saving ? 'Guardando...' : 'Registrar consolidación'}
                        </Button>
                    </>
                }
            >
                {consolidando && (
                    <div className="space-y-4">
                        <Callout variant="neutral">
                            Caja chica entregada: <strong>{money(consolidando.monto_caja_chica)}</strong> (inclúyela en el efectivo si tu empresa la cuenta en el cierre).
                            Verifica los métodos electrónicos contra la app del banco / billetera.
                        </Callout>

                        {/* Cabecera de la grilla */}
                        <div className="grid grid-cols-12 gap-2 px-1 text-[11px] font-semibold uppercase tracking-wider"
                            style={{ color: 'var(--color-text-muted)' }}>
                            <span className="col-span-4">Cuenta / método</span>
                            <span className="col-span-2 text-right">Cajera declaró</span>
                            <span className="col-span-2 text-right">Sistema espera</span>
                            <span className="col-span-2 text-right">Tu conteo</span>
                            <span className="col-span-2 text-right">Diferencia</span>
                        </div>

                        {lineas.map((l, idx) => {
                            const dif = l.contado !== '' && l.esperado !== null ? Number(l.contado) - l.esperado : null;
                            return (
                                <div key={idx} className="grid grid-cols-12 gap-2 items-center px-1">
                                    <span className="col-span-4 flex items-center gap-1.5 text-sm font-medium" style={{ color: 'var(--color-text)' }}>
                                        {l.metodo_pago_id === null
                                            ? <Coins size={14} style={{ color: 'var(--color-warning)' }} />
                                            : <CreditCard size={14} style={{ color: 'var(--color-primary)' }} />}
                                        {l.etiqueta}
                                    </span>
                                    <span className="col-span-2 text-right text-sm">
                                        {l.declarado !== null ? money(l.declarado) : <span style={{ color: 'var(--color-text-muted)' }}>—</span>}
                                    </span>
                                    <span className="col-span-2 text-right text-sm">
                                        {l.esperado !== null ? money(l.esperado) : <span style={{ color: 'var(--color-text-muted)' }}>—</span>}
                                    </span>
                                    <div className="col-span-2">
                                        <input
                                            type="number" min="0" step="0.01"
                                            value={l.contado}
                                            onChange={e => setLineas(prev => prev.map((x, i) => i === idx ? { ...x, contado: e.target.value } : x))}
                                            className="w-full text-right text-sm rounded-lg px-2 py-1.5 border outline-none font-semibold"
                                            style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }}
                                            placeholder="0.00"
                                        />
                                    </div>
                                    <span className="col-span-2 inline-flex items-center justify-end gap-1 text-right text-sm font-bold"
                                        style={{ color: dif === null ? 'var(--color-text-muted)' : Math.abs(dif) < 0.01 ? 'var(--color-success)' : dif < 0 ? 'var(--color-danger)' : 'var(--color-warning)' }}>
                                        {dif === null ? '—' : Math.abs(dif) < 0.01
                                            ? <><Check size={14} className="flex-shrink-0" />OK</>
                                            : `${dif > 0 ? '+' : ''}${dif.toFixed(2)}`}
                                    </span>
                                </div>
                            );
                        })}

                        {faltanteTotal >= 0.01 && (
                            <Callout variant="danger" title={`FALTANTE TOTAL: ${money(faltanteTotal)}`}>
                                <label className="flex items-start gap-2 text-sm cursor-pointer select-none mt-1">
                                    <input type="checkbox" checked={genDescuento}
                                        onChange={e => setGenDescuento(e.target.checked)}
                                        className="h-4 w-4 mt-0.5 accent-[var(--color-danger)]"
                                    />
                                    <span style={{ color: 'var(--color-text)' }}>
                                        Generar <strong>descuento de planilla</strong> a {consolidando.user?.name} por {money(faltanteTotal)}
                                    </span>
                                </label>
                            </Callout>
                        )}

                        <Input label="Observación" value={obs} onChange={e => setObs(e.target.value)} />
                    </div>
                )}
            </Modal>

            {/* Modal detalle de consolidación hecha */}
            <Modal isOpen={verDetalle !== null} onClose={() => setVerDetalle(null)}
                title={verDetalle ? `Consolidación — turno #${verDetalle.id}` : ''} size="md"
                footer={<Button variant="ghost" onClick={() => setVerDetalle(null)}>Cerrar</Button>}
            >
                {verDetalle?.consolidacion && (
                    <div className="divide-y" style={{ borderColor: 'var(--color-border)' }}>
                        {verDetalle.consolidacion.items.map(i => {
                            const dif = i.diferencia !== null ? Number(i.diferencia) : null;
                            return (
                                <div key={i.id} className="py-2 grid grid-cols-12 gap-2 items-center text-sm">
                                    <span className="col-span-4 font-medium">{i.etiqueta}</span>
                                    <span className="col-span-3 text-right text-xs" style={{ color: 'var(--color-text-muted)' }}>
                                        declaró {i.declarado !== null ? money(i.declarado) : '—'}<br />
                                        esperaba {i.esperado !== null ? money(i.esperado) : '—'}
                                    </span>
                                    <span className="col-span-3 text-right font-bold">{money(i.contado)}</span>
                                    <span className="col-span-2 inline-flex items-center justify-end gap-1 text-right font-bold"
                                        style={{ color: dif === null || Math.abs(dif) < 0.01 ? 'var(--color-success)' : dif < 0 ? 'var(--color-danger)' : 'var(--color-warning)' }}>
                                        {dif === null ? '—' : Math.abs(dif) < 0.01
                                            ? <Check size={14} className="flex-shrink-0" />
                                            : `${dif > 0 ? '+' : ''}${dif.toFixed(2)}`}
                                    </span>
                                </div>
                            );
                        })}
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}
