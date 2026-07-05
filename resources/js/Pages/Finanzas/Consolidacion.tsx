import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { ClipboardCheck, AlertTriangle } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Table, { Column } from '@/Components/UI/Table';
import Badge from '@/Components/UI/Badge';
import Modal from '@/Components/UI/Modal';
import Tabs from '@/Components/UI/Tabs';
import type { PageProps } from '@/types';

interface ArqueoMetodo {
    id: number;
    monto_declarado: string;
    metodo_pago?: { nombre: string } | null;
}

interface Consolidacion {
    id: number;
    fecha: string;
    efectivo_declarado: string | null;
    efectivo_esperado: string | null;
    efectivo_contado: string;
    diferencia_vs_declarado: string | null;
    diferencia_vs_esperado: string | null;
    observacion: string | null;
    user?: { name: string } | null;
}

interface TurnoRow extends Record<string, unknown> {
    id: number;
    fecha_apertura: string;
    fecha_cierre: string | null;
    monto_apertura: string;
    monto_caja_chica: string;
    monto_cierre_declarado: string | null;
    monto_cierre_esperado: string | null;
    diferencia: string | null;
    caja?: { nombre: string } | null;
    user?: { name: string } | null;       // cajera
    user_cierre?: { name: string } | null;
    consolidacion?: Consolidacion | null;
    arqueo_metodos: ArqueoMetodo[];
}

interface Paginado<T> { data: T[]; total: number; }

interface Props extends PageProps {
    turnos: Paginado<TurnoRow>;
    estado: string;
    requiereConsolidacion: boolean;
}

const money = (v: unknown) => `S/ ${Number(v ?? 0).toFixed(2)}`;
const fdatetime = (s: string | null) => s ? new Date(s).toLocaleString('es-PE', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—';

export default function Consolidacion({ turnos, estado, requiereConsolidacion }: Props) {
    const { flash } = usePage<Props>().props;
    const [consolidando, setConsolidando] = useState<TurnoRow | null>(null);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [form, setForm] = useState({ efectivo_contado: '', observacion: '', generar_descuento: false });

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    function abrir(t: TurnoRow) {
        setErrors({});
        setForm({ efectivo_contado: '', observacion: '', generar_descuento: false });
        setConsolidando(t);
    }

    function submit() {
        if (!consolidando) return;
        setSaving(true);
        router.post(route('finanzas.consolidacion.consolidar', consolidando.id), form as any, {
            onSuccess: () => { setConsolidando(null); setSaving(false); },
            onError:   (errs: any) => { setErrors(errs); setSaving(false); },
        });
    }

    // Diferencia en vivo del modal (contado del consolidador vs esperado del sistema)
    const esperado = consolidando?.monto_cierre_esperado !== null && consolidando !== null
        ? Number(consolidando.monto_cierre_esperado) : null;
    const contadoNum = form.efectivo_contado === '' ? null : Number(form.efectivo_contado);
    const difReal = esperado !== null && contadoNum !== null ? contadoNum - esperado : null;
    const hayFaltante = difReal !== null && difReal < -0.009;

    const columns: Column<TurnoRow>[] = [
        {
            key: 'fecha_cierre', label: 'Cierre', sortable: true,
            render: (t) => <span className="text-sm">{fdatetime(t.fecha_cierre)}</span>,
        },
        { key: 'caja', label: 'Caja', render: (t) => <span className="text-sm font-medium">{t.caja?.nombre ?? '—'}</span> },
        { key: 'user', label: 'Cajera', render: (t) => <span className="text-sm">{t.user?.name ?? '—'}</span> },
        { key: 'monto_caja_chica', label: 'Caja chica', render: (t) => <span className="text-sm">{money(t.monto_caja_chica)}</span> },
        {
            key: 'monto_cierre_declarado', label: 'Declaró (cajera)',
            render: (t) => t.monto_cierre_declarado !== null
                ? <span className="font-semibold">{money(t.monto_cierre_declarado)}</span>
                : <Badge variant="secondary">Cierre rápido</Badge>,
        },
        { key: 'monto_cierre_esperado', label: 'Esperado (sistema)', render: (t) => <span>{money(t.monto_cierre_esperado)}</span> },
        {
            key: 'estado_consolidacion', label: 'Consolidación',
            render: (t) => t.consolidacion
                ? (
                    <div className="text-sm">
                        <span className="font-bold">{money(t.consolidacion.efectivo_contado)}</span>
                        <span className="block text-xs" style={{ color: 'var(--color-text-muted)' }}>
                            por {t.consolidacion.user?.name}
                            {t.consolidacion.diferencia_vs_esperado !== null && Math.abs(Number(t.consolidacion.diferencia_vs_esperado)) >= 0.01 && (
                                <span style={{ color: Number(t.consolidacion.diferencia_vs_esperado) < 0 ? 'var(--color-danger)' : 'var(--color-success)' }}>
                                    {' '}({Number(t.consolidacion.diferencia_vs_esperado) > 0 ? '+' : ''}{Number(t.consolidacion.diferencia_vs_esperado).toFixed(2)})
                                </span>
                            )}
                        </span>
                    </div>
                )
                : <Badge variant="warning">Pendiente</Badge>,
        },
        {
            key: 'acciones', label: '',
            render: (t) => !t.consolidacion && (
                <Button onClick={() => abrir(t)}>
                    <ClipboardCheck size={14} className="mr-1" />Consolidar
                </Button>
            ),
        },
    ];

    return (
        <AppLayout title="Consolidación de caja">
            <PageHeader
                title="Consolidación de caja"
                subtitle="Segundo conteo del supervisor sobre cada cierre de turno. Su monto es el que manda en el balance."
            />

            {!requiereConsolidacion && (
                <div className="mb-4 rounded-xl px-4 py-3 flex items-start gap-2 text-sm"
                    style={{ backgroundColor: 'color-mix(in srgb, var(--color-warning, #f59e0b) 12%, var(--color-bg))', color: 'var(--color-text)' }}>
                    <AlertTriangle size={16} className="flex-shrink-0 mt-0.5" style={{ color: 'var(--color-warning)' }} />
                    <span>
                        La consolidación está <strong>desactivada</strong> en la configuración de la empresa: hoy el balance toma el
                        cierre de la cajera directamente. Puedes activarla en <strong>Configuración → Empresas → Consolidación de caja</strong>.
                        Aun así puedes consolidar turnos aquí y tu conteo reemplazará al de la cajera.
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
                    onChange={(v) => router.get(route('finanzas.consolidacion.index'), { estado: v }, { preserveState: true, replace: true })}
                />
            </div>

            <Table data={turnos.data} columns={columns}
                searchPlaceholder="Buscar caja o cajera..."
                emptyMessage={estado === 'pendientes' ? 'No hay turnos cerrados pendientes de consolidar' : 'Aún no hay turnos consolidados'} />

            {/* Modal consolidar */}
            <Modal isOpen={consolidando !== null} onClose={() => setConsolidando(null)}
                title={consolidando ? `Consolidar turno #${consolidando.id} — ${consolidando.user?.name ?? ''}` : ''} size="md"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setConsolidando(null)}>Cancelar</Button>
                        <Button onClick={submit} disabled={saving}>{saving ? 'Guardando...' : 'Registrar mi conteo'}</Button>
                    </>
                }
            >
                {consolidando && (
                    <div className="space-y-4">
                        {/* Lo declarado por la cajera (visible según lo acordado) */}
                        <div className="rounded-xl px-3 py-2 space-y-1 text-sm"
                            style={{ border: '1px solid var(--color-border)', backgroundColor: 'var(--color-bg)' }}>
                            <div className="flex justify-between">
                                <span style={{ color: 'var(--color-text-muted)' }}>Cajera declaró (efectivo)</span>
                                <strong>{consolidando.monto_cierre_declarado !== null ? money(consolidando.monto_cierre_declarado) : 'cierre rápido'}</strong>
                            </div>
                            <div className="flex justify-between">
                                <span style={{ color: 'var(--color-text-muted)' }}>Sistema esperaba</span>
                                <strong>{money(consolidando.monto_cierre_esperado)}</strong>
                            </div>
                            <div className="flex justify-between">
                                <span style={{ color: 'var(--color-text-muted)' }}>Caja chica entregada</span>
                                <strong>{money(consolidando.monto_caja_chica)}</strong>
                            </div>
                            {consolidando.arqueo_metodos.length > 0 && (
                                <div className="pt-1 mt-1" style={{ borderTop: '1px dashed var(--color-border)' }}>
                                    <p className="text-xs mb-1" style={{ color: 'var(--color-text-muted)' }}>Otros métodos declarados (verificar contra el banco):</p>
                                    {consolidando.arqueo_metodos.map(m => (
                                        <div key={m.id} className="flex justify-between text-xs">
                                            <span>{m.metodo_pago?.nombre ?? 'Método'}</span>
                                            <span className="font-medium">{money(m.monto_declarado)}</span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        <Input label="Efectivo contado por ti (billetes + monedas, incluye caja chica si aplica)"
                            required type="number" min="0" step="0.01"
                            value={form.efectivo_contado}
                            onChange={e => setForm(f => ({ ...f, efectivo_contado: e.target.value }))}
                            error={errors.efectivo_contado}
                        />

                        {difReal !== null && (
                            <div className="rounded-xl px-3 py-2 text-sm"
                                style={{
                                    backgroundColor: Math.abs(difReal) < 0.01
                                        ? 'color-mix(in srgb, var(--color-success, #16a34a) 10%, var(--color-bg))'
                                        : hayFaltante
                                            ? 'color-mix(in srgb, var(--color-danger) 10%, var(--color-bg))'
                                            : 'color-mix(in srgb, var(--color-warning, #f59e0b) 10%, var(--color-bg))',
                                }}>
                                {Math.abs(difReal) < 0.01
                                    ? <span style={{ color: 'var(--color-success)' }}>✓ Cuadra exacto con lo esperado</span>
                                    : (
                                        <span style={{ color: hayFaltante ? 'var(--color-danger)' : 'var(--color-warning)' }}>
                                            {hayFaltante ? 'FALTANTE' : 'SOBRANTE'} de <strong>{money(Math.abs(difReal))}</strong> vs lo esperado
                                            {consolidando.monto_cierre_declarado !== null && (
                                                <span className="block text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                                                    vs lo declarado por la cajera: {(contadoNum! - Number(consolidando.monto_cierre_declarado)) >= 0 ? '+' : ''}
                                                    {(contadoNum! - Number(consolidando.monto_cierre_declarado)).toFixed(2)}
                                                </span>
                                            )}
                                        </span>
                                    )}
                            </div>
                        )}

                        {hayFaltante && (
                            <label className="flex items-start gap-2 text-sm cursor-pointer select-none px-1">
                                <input type="checkbox" checked={form.generar_descuento}
                                    onChange={e => setForm(f => ({ ...f, generar_descuento: e.target.checked }))}
                                    className="h-4 w-4 mt-0.5 accent-[var(--color-danger)]"
                                />
                                <span style={{ color: 'var(--color-text)' }}>
                                    Generar <strong>descuento de planilla</strong> a {consolidando.user?.name} por el faltante de {money(Math.abs(difReal!))}
                                </span>
                            </label>
                        )}

                        <Input label="Observación"
                            value={form.observacion}
                            onChange={e => setForm(f => ({ ...f, observacion: e.target.value }))}
                        />
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}
