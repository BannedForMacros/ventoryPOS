import { useEffect, useMemo, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { CalendarCheck, FileSpreadsheet, PackageCheck, Search, Trash2, TriangleAlert, X } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/UI/Button';
import Select from '@/Components/UI/Select';
import Modal from '@/Components/UI/Modal';
import { Empty, Paginacion, fmtCant, fmtInt, fmtS, pct, plural, useRecargaTabla, type Paginado } from '@/Components/Reportes/ReportUI';
import { fechaLocal, hoyLocal } from '@/lib/fechas';
import type { PageProps } from '@/types';
import ImportarInventarioExcel from './Partials/ImportarInventarioExcel';
import { avisoError } from '@/lib/avisoError';

interface Fila {
    id: number;
    codigo: string | null;
    nombre: string;
    categoria: string | null;
    unidad: string;
    stock: number;
    costo: number;
    inicial: { cantidad: number; costo: number; fecha: string } | null;
}

interface Props extends PageProps {
    almacenes: { id: number; nombre: string }[];
    almacenId: number;
    categorias: { id: number; nombre: string }[];
    resumen: { productos: number; cargados: number; valor: number; sin_costo: number; ultima_fecha: string | null };
    productos: Paginado<Fila>;
    filters: { q?: string; estado?: string; categoria_id?: string; almacen_id?: string };
    puede: { editar: boolean };
}

type Cambio = { cantidad: string; costo: string };
type ModoFecha = 'ayer' | 'hoy' | 'otra';

const ayerLocal = () => { const d = new Date(); d.setDate(d.getDate() - 1); return fechaLocal(d); };
const fechaBonita = (iso: string) => new Date(`${iso}T12:00:00`).toLocaleDateString('es-PE', { weekday: 'long', day: 'numeric', month: 'long' });
const fechaCorta = (iso: string) => {
    const d = new Date(`${iso}T12:00:00`);
    return d.toLocaleDateString('es-PE', { day: 'numeric', month: 'short', ...(d.getFullYear() !== new Date().getFullYear() ? { year: 'numeric' } : {}) });
};
const aNumero = (s: string) => { const n = parseFloat(s.replace(',', '.')); return Number.isFinite(n) ? n : null; };

export default function InventarioInicial({ almacenes, almacenId, categorias, resumen, productos, filters, puede }: Props) {
    const { flash } = usePage<Props>().props;
    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    const almacen = almacenes.find(a => a.id === almacenId);
    const { cargando, recargar } = useRecargaTabla('inventario.inicial.index', ['productos', 'resumen', 'filters', 'almacenId']);
    const params = (extra: Record<string, unknown> = {}) => ({ ...filters, almacen_id: almacenId, ...extra });

    // ── Fecha del conteo ──
    // Por defecto "antes de abrir hoy": quien carga ahora suele estar contando
    // ahora. La fecha de cargas anteriores se ofrece como atajo, no se impone.
    const [modo, setModo] = useState<ModoFecha>('ayer');
    const [fechaOtra, setFechaOtra] = useState(resumen.ultima_fecha ?? ayerLocal());
    const fecha = modo === 'ayer' ? ayerLocal() : modo === 'hoy' ? hoyLocal() : fechaOtra;
    const fechaTexto = modo === 'ayer' ? 'antes de abrir hoy' : modo === 'hoy' ? 'al cerrar hoy' : `al cierre del ${fechaCorta(fechaOtra)}`;

    // ── Edición a mano ──
    const [cambios, setCambios] = useState<Record<number, Cambio>>({});
    const [guardando, setGuardando] = useState(false);
    const [quitando, setQuitando] = useState<Fila | null>(null);
    const [importando, setImportando] = useState(false);
    const [q, setQ] = useState(filters.q ?? '');

    const pendientes = useMemo(() => Object.entries(cambios)
        .filter(([, c]) => c.cantidad.trim() !== '' && aNumero(c.cantidad) !== null), [cambios]);
    const invalidos = Object.values(cambios).filter(c => (c.cantidad.trim() !== '' && (aNumero(c.cantidad) ?? -1) < 0) || (c.costo.trim() !== '' && (aNumero(c.costo) ?? -1) < 0)).length;

    // Búsqueda con pausa: no recarga en cada tecla.
    useEffect(() => {
        if ((filters.q ?? '') === q) return;
        const t = setTimeout(() => recargar(params({ q: q || undefined, page: undefined })), 350);
        return () => clearTimeout(t);
    }, [q]); // eslint-disable-line react-hooks/exhaustive-deps

    function editar(f: Fila, campo: keyof Cambio, valor: string) {
        setCambios(prev => {
            const base = prev[f.id] ?? {
                cantidad: f.inicial ? String(f.inicial.cantidad) : '',
                costo: f.inicial && f.inicial.costo > 0 ? String(f.inicial.costo) : '',
            };
            const nuevo = { ...base, [campo]: valor };
            const igual = f.inicial
                ? aNumero(nuevo.cantidad) === f.inicial.cantidad && (nuevo.costo === '' ? 0 : aNumero(nuevo.costo)) === (f.inicial.costo > 0 ? f.inicial.costo : 0)
                : nuevo.cantidad.trim() === '' && nuevo.costo.trim() === '';
            const copia = { ...prev };
            if (igual) delete copia[f.id]; else copia[f.id] = nuevo;
            return copia;
        });
    }

    function guardar() {
        if (!pendientes.length || invalidos) return;
        setGuardando(true);
        router.post(route('inventario.inicial.guardar'), {
            almacen_id: almacenId,
            fecha,
            origen: 'manual',
            items: pendientes.map(([id, c]) => ({
                producto_id: Number(id),
                cantidad: aNumero(c.cantidad),
                costo: c.costo.trim() === '' ? null : aNumero(c.costo),
            })),
        }, {
            preserveScroll: true,
            onSuccess: () => setCambios({}),
            onError: (e) => avisoError(Object.values(e)[0], 'No se pudo guardar.'),
            onFinish: () => setGuardando(false),
        });
    }

    function quitar() {
        if (!quitando) return;
        router.delete(route('inventario.inicial.quitar', quitando.id), {
            data: { almacen_id: almacenId },
            preserveScroll: true,
            onSuccess: () => { setCambios(c => { const n = { ...c }; delete n[quitando.id]; return n; }); setQuitando(null); },
        });
    }

    // Enter baja a la siguiente fila (como en una hoja de cálculo).
    const tablaRef = useRef<HTMLTableSectionElement>(null);
    function alPresionar(e: React.KeyboardEvent<HTMLInputElement>, idx: number, campo: keyof Cambio) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const sig = tablaRef.current?.querySelector<HTMLInputElement>(`input[data-fila="${idx + (e.shiftKey ? -1 : 1)}"][data-campo="${campo}"]`);
        sig?.focus();
        sig?.select();
    }

    const avance = pct(resumen.cargados, resumen.productos);
    const estado = filters.estado ?? '';

    return (
        <AppLayout title="Inventario inicial">
            {/* ── Encabezado ── */}
            <div className="flex flex-wrap items-end justify-between gap-x-6 gap-y-3 mb-4">
                <div className="min-w-0 max-w-2xl">
                    <h1 className="font-display text-[28px] font-extrabold tracking-tight leading-none" style={{ color: 'var(--vp-navy)' }}>Inventario inicial</h1>
                    <p className="text-[15px] mt-2" style={{ color: 'var(--color-text-muted)' }}>
                        El stock contado con el que arranca cada producto. Carga solo los que ya contaste: los demás quedan sin cargar hasta que lo hagas.
                    </p>
                </div>
                <div className="flex flex-wrap items-end gap-2">
                    {almacenes.length > 1 && (
                        <div className="w-56">
                            <Select label="Almacén" value={almacenId} disabled={pendientes.length > 0 || importando}
                                onChange={v => recargar({ almacen_id: v })}
                                options={almacenes.map(a => ({ value: a.id, label: a.nombre }))} />
                        </div>
                    )}
                    {puede.editar && !importando && (
                        <Button onClick={() => setImportando(true)} startContent={<FileSpreadsheet size={16} />}>Subir Excel</Button>
                    )}
                </div>
            </div>

            {/* ── Avance + fecha del conteo ── */}
            <section className="grid grid-cols-1 lg:grid-cols-12 gap-4 mb-4">
                <div className="lg:col-span-5 rounded-2xl p-5 text-white relative overflow-hidden" style={{ backgroundColor: 'var(--vp-navy)' }}>
                    <div className="flex items-center gap-2 text-[13px] font-semibold opacity-80"><PackageCheck size={16} /> Avance{almacen && almacenes.length > 1 ? `, ${almacen.nombre}` : ''}</div>
                    <p className="font-display text-[34px] font-extrabold leading-tight mt-1 tabular-nums">
                        {fmtInt(resumen.cargados)} <span className="text-lg font-bold opacity-70">de {fmtInt(resumen.productos)} productos</span>
                    </p>
                    <div className="h-2.5 rounded-full mt-3 overflow-hidden" style={{ backgroundColor: 'rgb(255 255 255 / 0.15)' }}>
                        <div className="h-full rounded-full transition-all" style={{ width: `${avance}%`, backgroundColor: 'var(--vp-mint)' }} />
                    </div>
                    <div className="flex flex-wrap justify-between gap-2 mt-3 text-sm">
                        <span className="opacity-85">Valor contado <strong className="tabular-nums">{fmtS(resumen.valor)}</strong></span>
                        <span className="opacity-85">{fmtInt(resumen.productos - resumen.cargados)} sin cargar</span>
                    </div>
                    {resumen.sin_costo > 0 && (
                        <p className="mt-3 text-[13px] flex items-start gap-1.5 rounded-lg px-2.5 py-1.5" style={{ backgroundColor: 'rgb(255 255 255 / 0.1)' }}>
                            <TriangleAlert size={14} className="mt-0.5 flex-shrink-0" style={{ color: 'var(--vp-amber)' }} />
                            {plural(resumen.sin_costo, 'producto cargado no tiene', 'productos cargados no tienen')} costo: su valor cuenta como S/ 0.
                        </p>
                    )}
                </div>

                <div className="lg:col-span-7 rounded-2xl p-5" style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
                    <div className="flex items-center gap-2 mb-3">
                        <span className="flex h-8 w-8 items-center justify-center rounded-lg" style={{ backgroundColor: 'color-mix(in srgb, var(--vp-sky) 14%, transparent)', color: 'var(--vp-navy)' }}>
                            <CalendarCheck size={17} />
                        </span>
                        <h2 className="font-display text-base font-bold" style={{ color: 'var(--color-text)' }}>¿Cuándo contaste?</h2>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {([['ayer', 'Antes de abrir hoy'], ['hoy', 'Al cerrar hoy'], ['otra', 'Otra fecha']] as [ModoFecha, string][]).map(([m, t]) => (
                            <button key={m} onClick={() => setModo(m)}
                                className="px-3.5 py-2 rounded-xl text-sm font-semibold transition-colors"
                                style={{
                                    backgroundColor: modo === m ? 'var(--vp-navy)' : 'color-mix(in srgb, var(--vp-navy) 5%, transparent)',
                                    color: modo === m ? '#fff' : 'var(--color-text)',
                                }}>
                                {t}
                            </button>
                        ))}
                        {modo === 'otra' && (
                            <input type="date" value={fechaOtra} max={hoyLocal()} onChange={e => setFechaOtra(e.target.value)} aria-label="Fecha del conteo"
                                className="px-3 py-2 rounded-xl text-sm border" style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
                        )}
                    </div>
                    <p className="text-sm mt-3" style={{ color: 'var(--color-text-muted)' }}>
                        {modo === 'ayer' && <>Lo que se venda <strong style={{ color: 'var(--color-text)' }}>desde hoy</strong> se descuenta de lo contado.</>}
                        {modo === 'hoy' && <>Las ventas de hoy <strong style={{ color: 'var(--color-text)' }}>ya están dentro</strong> de lo contado; se descuenta lo que se venda desde mañana.</>}
                        {modo === 'otra' && fechaOtra && <>Vale al cierre del <strong style={{ color: 'var(--color-text)' }}>{fechaBonita(fechaOtra)}</strong>: lo que se vendió o compró después se descuenta o se suma.</>}
                    </p>
                    {resumen.ultima_fecha && !(modo === 'otra' && fechaOtra === resumen.ultima_fecha) && (
                        <button onClick={() => { setModo('otra'); setFechaOtra(resumen.ultima_fecha!); }}
                            className="text-[13px] font-semibold mt-1.5 hover:underline" style={{ color: 'var(--color-primary)' }}>
                            Usar la fecha del último conteo ({fechaCorta(resumen.ultima_fecha)})
                        </button>
                    )}
                </div>
            </section>

            {importando && almacen ? (
                <ImportarInventarioExcel almacenId={almacenId} almacenNombre={almacen.nombre} fecha={fecha} fechaTexto={fechaTexto}
                    onCerrar={() => setImportando(false)} />
            ) : (
                /* ── Carga a mano ── */
                <section className="rounded-2xl overflow-hidden mb-20"
                    style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)', boxShadow: '0 6px 16px -10px rgb(15 76 129 / 0.12)' }}>
                    <div className="flex flex-wrap items-center gap-3 px-4 py-3" style={{ borderBottom: '1px solid var(--color-border)' }}>
                        <div className="relative flex-1 min-w-[220px] max-w-sm">
                            <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }} />
                            <input type="search" value={q} onChange={e => setQ(e.target.value)} placeholder="Buscar por nombre o código" aria-label="Buscar producto"
                                className="w-full text-sm rounded-xl pl-9 pr-8 py-2 border outline-none focus:ring-2"
                                style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
                            {q && <button onClick={() => setQ('')} aria-label="Quitar búsqueda" className="absolute right-2.5 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }}><X size={14} /></button>}
                        </div>
                        <div className="flex rounded-xl p-0.5" style={{ backgroundColor: 'color-mix(in srgb, var(--vp-navy) 5%, transparent)' }}>
                            {([['', 'Todos', resumen.productos], ['pendientes', 'Sin cargar', resumen.productos - resumen.cargados], ['cargados', 'Cargados', resumen.cargados]] as [string, string, number][]).map(([v, t, n]) => (
                                <button key={v} onClick={() => recargar(params({ estado: v || undefined, page: undefined }))}
                                    className="px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors"
                                    style={{ backgroundColor: estado === v ? 'var(--color-surface)' : 'transparent', color: estado === v ? 'var(--vp-navy)' : 'var(--color-text-muted)', boxShadow: estado === v ? '0 1px 3px rgb(0 0 0 / 0.1)' : undefined }}>
                                    {t} <span className="tabular-nums opacity-70">{fmtInt(n)}</span>
                                </button>
                            ))}
                        </div>
                        {categorias.length > 1 && (
                            <div className="w-52">
                                <Select size="sm" ariaLabel="Categoría" value={filters.categoria_id ?? ''}
                                    onChange={v => recargar(params({ categoria_id: v || undefined, page: undefined }))}
                                    options={[{ value: '', label: 'Todas las categorías' }, ...categorias.map(c => ({ value: c.id, label: c.nombre }))]} />
                            </div>
                        )}
                    </div>

                    <div className="overflow-x-auto" style={{ opacity: cargando ? 0.55 : 1, transition: 'opacity .15s' }}>
                        <table className="w-full text-sm min-w-[760px]">
                            <thead>
                                <tr className="text-left text-[13px]" style={{ color: 'var(--color-text-muted)', backgroundColor: 'color-mix(in srgb, var(--vp-navy) 3%, var(--color-surface))' }}>
                                    <th className="px-4 py-2.5 font-semibold">Producto</th>
                                    <th className="px-3 py-2.5 font-semibold text-right w-32">Stock hoy</th>
                                    <th className="px-3 py-2.5 font-semibold w-44">Cantidad contada</th>
                                    <th className="px-3 py-2.5 font-semibold w-40">Costo unitario</th>
                                    <th className="px-4 py-2.5 font-semibold w-44">Estado</th>
                                </tr>
                            </thead>
                            <tbody ref={tablaRef}>
                                {productos.data.map((f, idx) => {
                                    const c = cambios[f.id];
                                    const cantidad = c?.cantidad ?? (f.inicial ? String(f.inicial.cantidad) : '');
                                    const costo = c?.costo ?? (f.inicial && f.inicial.costo > 0 ? String(f.inicial.costo) : '');
                                    const sugerido = f.inicial?.costo || f.costo;
                                    return (
                                        <tr key={f.id} style={{
                                            borderTop: '1px solid color-mix(in srgb, var(--color-border) 70%, transparent)',
                                            backgroundColor: c ? 'color-mix(in srgb, var(--vp-amber) 7%, var(--color-surface))' : undefined,
                                            boxShadow: c ? 'inset 3px 0 0 var(--vp-amber)' : f.inicial ? 'inset 3px 0 0 var(--vp-mint)' : undefined,
                                        }}>
                                            <td className="px-4 py-2.5 min-w-0">
                                                <p className="font-medium" style={{ color: 'var(--color-text)' }}>{f.nombre}</p>
                                                <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>{[f.codigo, f.categoria].filter(Boolean).join(', ')}</p>
                                            </td>
                                            <td className="px-3 py-2.5 text-right tabular-nums" style={{ color: f.stock < 0 ? 'var(--vp-coral-ink)' : 'var(--color-text-muted)' }}>
                                                {fmtCant(f.stock)} <span className="text-[13px]">{f.unidad}</span>
                                            </td>
                                            <td className="px-3 py-2">
                                                <div className="flex items-center gap-1.5">
                                                    <input inputMode="decimal" value={cantidad} disabled={!puede.editar}
                                                        data-fila={idx} data-campo="cantidad"
                                                        onChange={e => editar(f, 'cantidad', e.target.value)}
                                                        onKeyDown={e => alPresionar(e, idx, 'cantidad')}
                                                        placeholder="—" aria-label={`Cantidad contada de ${f.nombre}`}
                                                        className="w-24 text-right tabular-nums text-sm font-semibold rounded-lg px-2.5 py-1.5 border outline-none focus:ring-2"
                                                        style={{ borderColor: c ? 'var(--vp-amber)' : 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
                                                    <span className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>{f.unidad}</span>
                                                </div>
                                            </td>
                                            <td className="px-3 py-2">
                                                <div className="flex items-center gap-1.5">
                                                    <span className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>S/</span>
                                                    <input inputMode="decimal" value={costo} disabled={!puede.editar}
                                                        data-fila={idx} data-campo="costo"
                                                        onChange={e => editar(f, 'costo', e.target.value)}
                                                        onKeyDown={e => alPresionar(e, idx, 'costo')}
                                                        placeholder={sugerido > 0 ? fmtCant(sugerido) : '0.00'} aria-label={`Costo unitario de ${f.nombre}`}
                                                        title={sugerido > 0 ? 'Si lo dejas vacío se usa el costo actual' : undefined}
                                                        className="w-24 text-right tabular-nums text-sm rounded-lg px-2.5 py-1.5 border outline-none focus:ring-2"
                                                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
                                                </div>
                                            </td>
                                            <td className="px-4 py-2.5">
                                                <div className="flex items-center justify-between gap-2">
                                                    {c ? (
                                                        <span className="text-[13px] font-semibold" style={{ color: 'var(--vp-amber-ink)' }}>Sin guardar</span>
                                                    ) : f.inicial ? (
                                                        <span className="text-[13px] font-semibold" style={{ color: 'var(--vp-mint-ink)' }}>Cargado, {fechaCorta(f.inicial.fecha)}</span>
                                                    ) : (
                                                        <span className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>Sin cargar</span>
                                                    )}
                                                    {f.inicial && puede.editar && !c && (
                                                        <button onClick={() => setQuitando(f)} aria-label={`Quitar inventario inicial de ${f.nombre}`} title="Quitar inventario inicial"
                                                            className="p-1.5 rounded-lg hover:opacity-70" style={{ color: 'var(--color-text-muted)' }}>
                                                            <Trash2 size={15} />
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                        {productos.data.length === 0 && (
                            <Empty text={filters.q ? 'Ningún producto coincide con la búsqueda.' : estado === 'cargados' ? 'Aún no cargaste ningún producto.' : estado === 'pendientes' ? 'Todos los productos ya tienen inventario inicial.' : 'No hay productos que controlen stock.'} />
                        )}
                    </div>
                    <Paginacion paginado={productos} ruta="inventario.inicial.index" filters={params()} onIr={page => recargar(params({ page }))} />
                </section>
            )}

            {/* ── Barra de guardado ── */}
            {!importando && (pendientes.length > 0 || invalidos > 0) && (
                <div className="fixed bottom-4 left-1/2 -translate-x-1/2 z-40 w-[calc(100%-2rem)] max-w-3xl rounded-2xl px-5 py-3.5 flex flex-wrap items-center justify-between gap-3 text-white"
                    style={{ backgroundColor: 'var(--vp-navy)', boxShadow: '0 16px 40px -12px rgb(15 76 129 / 0.55)' }}>
                    <div className="text-sm min-w-0">
                        <p className="font-bold">{plural(pendientes.length, 'producto por guardar', 'productos por guardar')}</p>
                        <p className="opacity-80 text-[13px]">
                            {invalidos > 0 ? 'Hay números negativos: corrígelos para guardar.' : `Contados ${fechaTexto}. Puedes cambiar de página: no se pierden.`}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <button onClick={() => setCambios({})} className="px-3.5 py-2 rounded-xl text-sm font-semibold hover:bg-white/10">Descartar</button>
                        <Button variant="success" onClick={guardar} loading={guardando} disabled={invalidos > 0 || !pendientes.length}>Guardar</Button>
                    </div>
                </div>
            )}

            <Modal isOpen={!!quitando} onClose={() => setQuitando(null)} title="Quitar inventario inicial" size="sm"
                footer={<>
                    <Button variant="ghost" onClick={() => setQuitando(null)}>Cancelar</Button>
                    <Button variant="danger" onClick={quitar}>Quitar</Button>
                </>}>
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    <strong>{quitando?.nombre}</strong> dejará de arrancar desde {quitando?.inicial ? fmtCant(quitando.inicial.cantidad) : 0} {quitando?.unidad}.
                    Su stock volverá a calcularse con todas sus compras y ventas.
                </p>
            </Modal>
        </AppLayout>
    );
}
