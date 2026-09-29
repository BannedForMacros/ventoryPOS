import { useEffect, useMemo, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import * as XLSX from 'xlsx';
import toast from 'react-hot-toast';
import {
    ArrowLeft, ArrowRight, Check, CheckCircle2, FileSpreadsheet, Search, TriangleAlert, Upload, X,
} from 'lucide-react';
import Button from '@/Components/UI/Button';
import Select from '@/Components/UI/Select';
import { fmtCant, fmtInt, fmtS, plural } from '@/Components/Reportes/ReportUI';

/* ── Tipos ─────────────────────────────────────────────────────────────── */

type Campo = 'producto' | 'codigo' | 'cantidad' | 'unidad' | 'costo';
type Celda = string | number | boolean | null;

interface UnidadProd { id: number; nombre: string; abrev: string | null; factor: number; base: boolean; }
interface ProductoRes { id: number; nombre: string; codigo: string | null; unidades: UnidadProd[]; }

interface FilaRevision {
    fila: number;
    texto: string;
    codigo: string;
    unidad: string;
    cantidad: number | null;
    costo: number | null;
    estado: 'ok' | 'sugerido' | 'unidad' | 'error';
    mensaje: string | null;
    producto: ProductoRes | null;
    sugerencias: ProductoRes[];
    producto_unidad_id?: number | null;
    repetido_de?: number;
    ya_tenia?: { cantidad: number; fecha: string };
}

interface Decision { producto?: ProductoRes; unidadId?: number; omitida?: boolean; }
type EstadoFinal = 'listo' | 'revisar' | 'error' | 'omitida';

const CAMPOS: { clave: Campo; label: string; ayuda: string }[] = [
    { clave: 'producto', label: 'Producto',        ayuda: 'El nombre del producto' },
    { clave: 'codigo',   label: 'Código',          ayuda: 'Si tu Excel lo tiene, busca primero por código' },
    { clave: 'cantidad', label: 'Cantidad',        ayuda: 'Lo que contaste' },
    { clave: 'unidad',   label: 'Unidad',          ayuda: 'Si está vacía, se toma la unidad base del producto' },
    { clave: 'costo',    label: 'Costo unitario',  ayuda: 'Por la unidad en que está contado. Si falta, se usa el costo actual' },
];

/** Palabras con que suele venir cada columna. La primera coincidencia exacta gana. */
const SINONIMOS: Record<Campo, string[]> = {
    producto: ['producto', 'nombre', 'nombre producto', 'descripcion', 'articulo', 'item', 'detalle', 'material', 'nombre del producto'],
    codigo:   ['codigo', 'cod', 'sku', 'codigo barras', 'barras', 'ean', 'referencia', 'ref'],
    cantidad: ['cantidad', 'cant', 'stock', 'existencia', 'existencias', 'saldo', 'conteo', 'contado', 'qty', 'stock fisico', 'total'],
    unidad:   ['unidad', 'um', 'u m', 'unidad medida', 'unidad de medida', 'medida', 'presentacion', 'und'],
    costo:    ['costo', 'costo unitario', 'precio costo', 'p costo', 'costo unit', 'precio compra', 'p compra', 'costo promedio', 'pc'],
};

const norm = (s: unknown) => String(s ?? '')
    .normalize('NFD').replace(/[̀-ͯ]/g, '')
    .toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
const singular = (s: string) => s.replace(/(es|s)$/, '') || s;
const vacia = (c: Celda) => c === null || c === undefined || String(c).trim() === '';

function puntaje(header: string, campo: Campo): number {
    const h = norm(header);
    if (!h) return 0;
    let mejor = 0;
    SINONIMOS[campo].forEach((s, i) => {
        const peso = 1 - i / 100;   // los primeros sinónimos pesan un poco más
        if (h === s) mejor = Math.max(mejor, 3 * peso);
        else if (h.startsWith(s + ' ') || h.endsWith(' ' + s)) mejor = Math.max(mejor, 2 * peso);
        else if (s.length > 3 && h.includes(s)) mejor = Math.max(mejor, 1 * peso);
    });
    return mejor;
}

/** Fila de títulos: la que más se parece a encabezados conocidos entre las 20 primeras. */
function detectarFilaTitulos(filas: Celda[][]): number {
    let mejor = -1, mejorPuntos = 0;
    filas.slice(0, 20).forEach((f, i) => {
        const puntos = f.reduce<number>((acc, c) =>
            acc + (typeof c === 'string' && (Object.keys(SINONIMOS) as Campo[]).some(k => puntaje(c, k) >= 2) ? 1 : 0), 0);
        if (puntos > mejorPuntos) { mejor = i; mejorPuntos = puntos; }
    });
    if (mejor >= 0) return mejor;
    const primera = filas.findIndex(f => f.filter(c => !vacia(c)).length >= 2);
    return Math.max(0, primera);
}

/** Relaciona columnas con campos: gana el mejor puntaje y cada columna se usa una vez. */
function adivinarMapeo(headers: string[]): Record<Campo, number | null> {
    const pares: { campo: Campo; col: number; p: number }[] = [];
    headers.forEach((h, col) => (Object.keys(SINONIMOS) as Campo[]).forEach(campo => {
        const p = puntaje(h, campo);
        if (p > 0) pares.push({ campo, col, p });
    }));
    pares.sort((a, b) => b.p - a.p);
    const res: Record<Campo, number | null> = { producto: null, codigo: null, cantidad: null, unidad: null, costo: null };
    const usadas = new Set<number>();
    for (const { campo, col } of pares) {
        if (res[campo] !== null || usadas.has(col)) continue;
        res[campo] = col;
        usadas.add(col);
    }
    return res;
}

function unidadDe(p: ProductoRes, texto: string): number | null {
    const base = p.unidades.find(u => u.base) ?? p.unidades[0];
    if (!texto.trim()) return base?.id ?? null;
    const buscada = singular(norm(texto));
    const u = p.unidades.find(u => [u.nombre, u.abrev].some(a => a && singular(norm(a)) === buscada));
    return u?.id ?? null;
}

/* ── Componente ────────────────────────────────────────────────────────── */

export default function ImportarInventarioExcel({ almacenId, almacenNombre, fecha, fechaTexto, onCerrar }: {
    almacenId: number;
    almacenNombre: string;
    fecha: string;
    fechaTexto: string;
    onCerrar: () => void;
}) {
    const [paso, setPaso] = useState<1 | 2 | 3>(1);

    // Paso 1: archivo
    const [archivo, setArchivo] = useState<string | null>(null);
    const [libro, setLibro] = useState<XLSX.WorkBook | null>(null);
    const [hoja, setHoja] = useState('');
    const [arrastrando, setArrastrando] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    // Paso 2: columnas
    const filas = useMemo<Celda[][]>(() => {
        if (!libro || !hoja) return [];
        return XLSX.utils.sheet_to_json<Celda[]>(libro.Sheets[hoja], { header: 1, raw: true, defval: null, blankrows: true });
    }, [libro, hoja]);
    const [filaTitulos, setFilaTitulos] = useState(0);
    const headers = useMemo(() => (filas[filaTitulos] ?? []).map((c, i) => vacia(c) ? `Columna ${XLSX.utils.encode_col(i)}` : String(c).trim()), [filas, filaTitulos]);
    const [mapeo, setMapeo] = useState<Record<Campo, number | null>>({ producto: null, codigo: null, cantidad: null, unidad: null, costo: null });
    const datos = useMemo(() => filas
        .map((f, i) => ({ f, fila: i + 1 }))
        .slice(filaTitulos + 1)
        .filter(({ f }) => f.some(c => !vacia(c))), [filas, filaTitulos]);

    useEffect(() => {
        if (!filas.length) return;
        const t = detectarFilaTitulos(filas);
        setFilaTitulos(t);
    }, [filas]);
    useEffect(() => { setMapeo(adivinarMapeo(headers)); }, [headers]);

    // Paso 3: revisión
    const [revision, setRevision] = useState<FilaRevision[]>([]);
    const [decisiones, setDecisiones] = useState<Record<number, Decision>>({});
    const [cargando, setCargando] = useState(false);
    const [guardando, setGuardando] = useState(false);
    const [filtro, setFiltro] = useState<'todas' | EstadoFinal>('todas');
    const [limite, setLimite] = useState(100);

    function leer(file: File) {
        if (!/\.(xlsx|xls|csv|ods)$/i.test(file.name)) {
            toast.error('Sube un archivo de Excel (.xlsx, .xls) o .csv');
            return;
        }
        const reader = new FileReader();
        reader.onload = e => {
            try {
                const wb = XLSX.read(new Uint8Array(e.target!.result as ArrayBuffer), { type: 'array' });
                // La hoja con más filas suele ser la del inventario.
                const mayor = wb.SheetNames.reduce((a, b) =>
                    (XLSX.utils.decode_range(wb.Sheets[b]['!ref'] ?? 'A1').e.r > XLSX.utils.decode_range(wb.Sheets[a]['!ref'] ?? 'A1').e.r ? b : a), wb.SheetNames[0]);
                setLibro(wb);
                setHoja(mayor);
                setArchivo(file.name);
                setPaso(2);
            } catch {
                toast.error('No se pudo leer el archivo. ¿Está dañado o protegido con clave?');
            }
        };
        reader.readAsArrayBuffer(file);
    }

    const faltaProducto = mapeo.producto === null && mapeo.codigo === null;
    const faltaCantidad = mapeo.cantidad === null;

    async function emparejar() {
        const valor = (f: Celda[], campo: Campo) => {
            const col = mapeo[campo];
            if (col === null) return null;
            const c = f[col];
            return vacia(c) ? null : (typeof c === 'number' ? c : String(c).trim());
        };
        const payload = datos.map(({ f, fila }) => ({
            fila,
            producto: valor(f, 'producto'),
            codigo:   valor(f, 'codigo'),
            cantidad: valor(f, 'cantidad'),
            unidad:   valor(f, 'unidad'),
            costo:    valor(f, 'costo'),
        })).filter(r => r.producto !== null || r.codigo !== null);

        if (!payload.length) {
            toast.error('No hay filas con producto en esas columnas.');
            return;
        }
        setCargando(true);
        try {
            const { data } = await axios.post(route('inventario.inicial.emparejar'), { almacen_id: almacenId, filas: payload });
            const res = data.filas as FilaRevision[];
            setRevision(res);
            setDecisiones({});
            setLimite(100);
            setFiltro(res.some(r => r.estado !== 'ok') ? 'revisar' : 'todas');
            setPaso(3);
        } catch {
            toast.error('No se pudo revisar el archivo. Intenta de nuevo.');
        } finally {
            setCargando(false);
        }
    }

    // Estado final de cada fila, con lo que decidió el usuario.
    const final = useMemo(() => revision.map(r => {
        const d = decisiones[r.fila] ?? {};
        const producto = d.producto ?? r.producto;
        let unidadId: number | null = d.unidadId ?? null;
        if (!unidadId && producto) {
            unidadId = d.producto ? unidadDe(producto, r.unidad) : (r.estado === 'unidad' ? null : (r.producto_unidad_id ?? unidadDe(producto, r.unidad)));
        }
        let estado: EstadoFinal = 'listo';
        if (d.omitida) estado = 'omitida';
        else if (r.estado === 'error') estado = 'error';
        else if (!producto || !unidadId) estado = 'revisar';
        return { r, producto, unidadId, estado };
    }), [revision, decisiones]);

    const conteo = useMemo(() => {
        const c = { listo: 0, revisar: 0, error: 0, omitida: 0 } as Record<EstadoFinal, number>;
        final.forEach(f => c[f.estado]++);
        return c;
    }, [final]);
    const productosListos = useMemo(() => new Set(final.filter(f => f.estado === 'listo').map(f => f.producto!.id)).size, [final]);
    const reemplaza = useMemo(() => new Set(final.filter(f => f.estado === 'listo' && f.r.ya_tenia).map(f => f.producto!.id)).size, [final]);
    const conSugerencia = final.filter(f => f.estado === 'revisar' && !f.producto && f.r.sugerencias.length > 0);

    const visibles = final.filter(f => filtro === 'todas' || f.estado === filtro);

    const decidir = (fila: number, d: Decision) => setDecisiones(prev => ({ ...prev, [fila]: { ...prev[fila], ...d } }));

    function guardar() {
        const items = final.filter(f => f.estado === 'listo').map(f => ({
            producto_id: f.producto!.id,
            cantidad: f.r.cantidad ?? 0,
            producto_unidad_id: f.unidadId,
            costo: f.r.costo,
        }));
        if (!items.length) return;
        setGuardando(true);
        router.post(route('inventario.inicial.guardar'), { almacen_id: almacenId, fecha, origen: 'excel', items }, {
            preserveScroll: true,
            onSuccess: () => onCerrar(),
            onError: (e) => toast.error(Object.values(e)[0] as string ?? 'No se pudo guardar.'),
            onFinish: () => setGuardando(false),
        });
    }

    /* ── Render ── */
    return (
        <section className="rounded-2xl overflow-hidden"
            style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)', boxShadow: '0 6px 16px -10px rgb(15 76 129 / 0.14)' }}>
            {/* Pasos */}
            <header className="flex flex-wrap items-center justify-between gap-3 px-5 py-4"
                style={{ backgroundColor: 'color-mix(in srgb, var(--vp-navy) 6%, var(--color-surface))', borderBottom: '1px solid var(--color-border)' }}>
                <div className="flex items-center gap-3 min-w-0">
                    <span className="flex h-10 w-10 items-center justify-center rounded-xl text-white flex-shrink-0" style={{ backgroundColor: 'var(--vp-mint-ink)' }}>
                        <FileSpreadsheet size={20} />
                    </span>
                    <div className="min-w-0">
                        <h2 className="font-display text-lg font-bold leading-tight" style={{ color: 'var(--vp-navy)' }}>Subir inventario desde Excel</h2>
                        <p className="text-[13px] truncate" style={{ color: 'var(--color-text-muted)' }}>
                            {archivo ? `${archivo}${hoja && libro && libro.SheetNames.length > 1 ? `, hoja "${hoja}"` : ''}` : 'Cualquier Excel sirve: tú dices qué columna es qué'}
                        </p>
                    </div>
                </div>
                <ol className="flex items-center gap-1.5 text-[13px] font-semibold">
                    {['Archivo', 'Columnas', 'Revisión'].map((t, i) => {
                        const n = i + 1, activo = paso === n, hecho = paso > n;
                        return (
                            <li key={t} className="flex items-center gap-1.5">
                                {i > 0 && <span className="w-5 h-px" style={{ backgroundColor: 'var(--color-border)' }} />}
                                <span className="flex items-center gap-1.5 px-2.5 py-1 rounded-full"
                                    style={{
                                        backgroundColor: activo ? 'var(--vp-navy)' : hecho ? 'color-mix(in srgb, var(--vp-mint) 16%, transparent)' : 'transparent',
                                        color: activo ? '#fff' : hecho ? 'var(--vp-mint-ink)' : 'var(--color-text-muted)',
                                    }}>
                                    {hecho ? <Check size={13} /> : <span className="tabular-nums">{n}</span>} {t}
                                </span>
                            </li>
                        );
                    })}
                </ol>
                <button onClick={onCerrar} aria-label="Cerrar la carga desde Excel" className="p-2 rounded-lg hover:opacity-70" style={{ color: 'var(--color-text-muted)' }}>
                    <X size={18} />
                </button>
            </header>

            {/* ── Paso 1: archivo ── */}
            {paso === 1 && (
                <div className="p-5">
                    <label
                        onDragOver={e => { e.preventDefault(); setArrastrando(true); }}
                        onDragLeave={() => setArrastrando(false)}
                        onDrop={e => { e.preventDefault(); setArrastrando(false); const f = e.dataTransfer.files?.[0]; if (f) leer(f); }}
                        className="flex flex-col items-center justify-center gap-3 rounded-2xl px-6 py-14 text-center cursor-pointer transition-colors"
                        style={{
                            border: `2px dashed ${arrastrando ? 'var(--vp-mint-ink)' : 'var(--color-border)'}`,
                            backgroundColor: arrastrando ? 'color-mix(in srgb, var(--vp-mint) 8%, transparent)' : 'color-mix(in srgb, var(--vp-navy) 2%, transparent)',
                        }}>
                        <span className="flex h-14 w-14 items-center justify-center rounded-2xl" style={{ backgroundColor: 'color-mix(in srgb, var(--vp-mint) 14%, transparent)', color: 'var(--vp-mint-ink)' }}>
                            <Upload size={26} />
                        </span>
                        <span className="font-display text-lg font-bold" style={{ color: 'var(--color-text)' }}>Arrastra tu Excel aquí o haz clic para elegirlo</span>
                        <span className="text-sm max-w-md" style={{ color: 'var(--color-text-muted)' }}>
                            .xlsx, .xls o .csv. No importa cómo se llamen las columnas ni en qué orden estén: en el siguiente paso le dices al sistema cuál es el producto, la cantidad y el costo.
                        </span>
                        <input ref={inputRef} type="file" accept=".xlsx,.xls,.csv,.ods" className="sr-only"
                            onChange={e => { const f = e.target.files?.[0]; if (f) leer(f); e.target.value = ''; }} />
                    </label>
                </div>
            )}

            {/* ── Paso 2: columnas ── */}
            {paso === 2 && (
                <div className="p-5 space-y-5">
                    <div className="flex flex-wrap items-end gap-4">
                        {libro && libro.SheetNames.length > 1 && (
                            <div className="w-56">
                                <Select label="Hoja" value={hoja} onChange={v => setHoja(String(v))}
                                    options={libro.SheetNames.map(n => ({ value: n, label: n }))} />
                            </div>
                        )}
                        <div className="w-56">
                            <Select label="Los títulos están en la fila" value={filaTitulos}
                                onChange={v => setFilaTitulos(Number(v))}
                                options={filas.slice(0, 20).map((f, i) => ({
                                    value: i,
                                    label: `Fila ${i + 1}: ${f.filter(c => !vacia(c)).slice(0, 3).map(String).join(', ').slice(0, 40) || '(vacía)'}`,
                                }))} />
                        </div>
                        <p className="text-sm pb-2" style={{ color: 'var(--color-text-muted)' }}>
                            {plural(datos.length, 'fila con datos', 'filas con datos')} debajo de los títulos
                        </p>
                    </div>

                    <div>
                        <h3 className="font-display text-base font-bold mb-1" style={{ color: 'var(--color-text)' }}>¿Qué columna de tu Excel es cada dato?</h3>
                        <p className="text-sm mb-3" style={{ color: 'var(--color-text-muted)' }}>
                            Ya relacionamos las que reconocimos por su título. Revisa y corrige si hace falta.
                        </p>
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-3">
                            {CAMPOS.map(c => {
                                const col = mapeo[c.clave];
                                const ejemplos = col === null ? [] : datos.map(d => d.f[col]).filter(v => !vacia(v)).slice(0, 3);
                                const obligatorio = c.clave === 'cantidad' || ((c.clave === 'producto' || c.clave === 'codigo') && faltaProducto);
                                return (
                                    <div key={c.clave} className="rounded-xl p-3.5"
                                        style={{
                                            border: `1px solid ${col !== null ? 'color-mix(in srgb, var(--vp-mint) 45%, var(--color-border))' : obligatorio ? 'color-mix(in srgb, var(--vp-coral) 50%, var(--color-border))' : 'var(--color-border)'}`,
                                            backgroundColor: col !== null ? 'color-mix(in srgb, var(--vp-mint) 5%, var(--color-surface))' : 'var(--color-surface)',
                                        }}>
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <p className="text-[15px] font-bold" style={{ color: 'var(--color-text)' }}>
                                                    {c.label}
                                                    {(c.clave === 'cantidad') && <span style={{ color: 'var(--vp-coral-ink)' }}> *</span>}
                                                </p>
                                                <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>{c.ayuda}</p>
                                            </div>
                                            <div className="w-52 flex-shrink-0">
                                                <Select size="sm" ariaLabel={`Columna para ${c.label}`}
                                                    value={col === null ? '' : col}
                                                    onChange={v => setMapeo(m => ({ ...m, [c.clave]: v === '' ? null : Number(v) }))}
                                                    options={[{ value: '', label: 'No está en mi Excel' }, ...headers.map((h, i) => ({ value: i, label: h }))]} />
                                            </div>
                                        </div>
                                        {ejemplos.length > 0 && (
                                            <p className="mt-2 text-[13px] truncate" style={{ color: 'var(--color-text-muted)' }}>
                                                Ej.: {ejemplos.map(v => <span key={String(v)} className="font-semibold mr-2" style={{ color: 'var(--color-text)' }}>{String(v)}</span>)}
                                            </p>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                        {(faltaProducto || faltaCantidad) && (
                            <p className="mt-3 text-sm font-semibold" style={{ color: 'var(--vp-coral-ink)' }}>
                                {faltaProducto ? 'Elige la columna del producto (o la del código). ' : ''}
                                {faltaCantidad ? 'Elige la columna de la cantidad.' : ''}
                            </p>
                        )}
                    </div>

                    <div className="flex flex-wrap justify-between gap-2 pt-1">
                        <Button variant="ghost" onClick={() => { setPaso(1); setLibro(null); setArchivo(null); }} startContent={<ArrowLeft size={15} />}>Otro archivo</Button>
                        <Button onClick={emparejar} disabled={faltaProducto || faltaCantidad} loading={cargando} endContent={<ArrowRight size={15} />}>
                            Buscar los productos
                        </Button>
                    </div>
                </div>
            )}

            {/* ── Paso 3: revisión ── */}
            {paso === 3 && (
                <div>
                    {/* Resumen */}
                    <div className="grid grid-cols-2 lg:grid-cols-4" style={{ borderBottom: '1px solid var(--color-border)' }}>
                        {([
                            ['listo',   'Listas para cargar', 'var(--vp-mint-ink)'],
                            ['revisar', 'Por revisar',        'var(--vp-amber-ink)'],
                            ['error',   'Con error',          'var(--vp-coral-ink)'],
                            ['omitida', 'Omitidas',           'var(--color-text-muted)'],
                        ] as [EstadoFinal, string, string][]).map(([k, t, color]) => (
                            <button key={k} onClick={() => { setFiltro(filtro === k ? 'todas' : k); setLimite(100); }}
                                className="text-left px-5 py-3.5 transition-colors lg:[&:not(:first-child)]:border-l"
                                style={{
                                    borderColor: 'var(--color-border)',
                                    backgroundColor: filtro === k ? `color-mix(in srgb, ${color} 8%, var(--color-surface))` : undefined,
                                    boxShadow: filtro === k ? `inset 0 -3px 0 ${color}` : undefined,
                                }}>
                                <p className="text-[13px] font-semibold" style={{ color: 'var(--color-text-muted)' }}>{t}</p>
                                <p className="font-display text-2xl font-extrabold tabular-nums" style={{ color: conteo[k] ? color : 'var(--color-text-muted)' }}>{fmtInt(conteo[k])}</p>
                            </button>
                        ))}
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-2 px-5 py-3" style={{ borderBottom: '1px solid var(--color-border)' }}>
                        <p className="text-sm" style={{ color: 'var(--color-text-muted)' }}>
                            {filtro === 'todas' ? `Todas las filas (${fmtInt(final.length)})` : `Mostrando: ${({ listo: 'listas', revisar: 'por revisar', error: 'con error', omitida: 'omitidas' })[filtro]}`}
                            {filtro !== 'todas' && <button onClick={() => setFiltro('todas')} className="ml-2 font-semibold hover:underline" style={{ color: 'var(--color-primary)' }}>ver todas</button>}
                        </p>
                        {conSugerencia.length > 0 && (
                            <Button size="sm" variant="secondary" onClick={() => setDecisiones(prev => {
                                const n = { ...prev };
                                conSugerencia.forEach(f => { n[f.r.fila] = { ...n[f.r.fila], producto: f.r.sugerencias[0] }; });
                                return n;
                            })}>
                                Usar el primer parecido en {plural(conSugerencia.length, 'fila', 'filas')}
                            </Button>
                        )}
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm min-w-[860px]">
                            <thead>
                                <tr className="text-left text-[13px]" style={{ color: 'var(--color-text-muted)', backgroundColor: 'color-mix(in srgb, var(--vp-navy) 3%, var(--color-surface))' }}>
                                    <th className="px-4 py-2.5 font-semibold w-16">Fila</th>
                                    <th className="px-3 py-2.5 font-semibold">En tu Excel</th>
                                    <th className="px-3 py-2.5 font-semibold">Producto en el sistema</th>
                                    <th className="px-3 py-2.5 font-semibold text-right w-44">Cantidad</th>
                                    <th className="px-3 py-2.5 font-semibold text-right w-28">Costo</th>
                                    <th className="px-4 py-2.5 w-24" />
                                </tr>
                            </thead>
                            <tbody>
                                {visibles.slice(0, limite).map(({ r, producto, unidadId, estado }) => (
                                    <FilaExcel key={r.fila} r={r} producto={producto} unidadId={unidadId} estado={estado}
                                        onProducto={p => decidir(r.fila, { producto: p, unidadId: undefined })}
                                        onUnidad={id => decidir(r.fila, { unidadId: id })}
                                        onOmitir={o => decidir(r.fila, { omitida: o })} />
                                ))}
                                {visibles.length === 0 && (
                                    <tr><td colSpan={6} className="px-4 py-10 text-center" style={{ color: 'var(--color-text-muted)' }}>No hay filas en este grupo.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    {visibles.length > limite && (
                        <div className="px-5 py-3 text-center" style={{ borderTop: '1px solid var(--color-border)' }}>
                            <button onClick={() => setLimite(l => l + 200)} className="text-sm font-semibold hover:underline" style={{ color: 'var(--color-primary)' }}>
                                Mostrar {fmtInt(Math.min(200, visibles.length - limite))} filas más ({fmtInt(visibles.length - limite)} restantes)
                            </button>
                        </div>
                    )}

                    {/* Confirmación */}
                    <footer className="sticky bottom-0 flex flex-wrap items-center justify-between gap-3 px-5 py-4"
                        style={{ borderTop: '1px solid var(--color-border)', backgroundColor: 'color-mix(in srgb, var(--vp-navy) 4%, var(--color-surface))' }}>
                        <div className="text-sm min-w-0" style={{ color: 'var(--color-text-muted)' }}>
                            <p>
                                Se cargarán <strong style={{ color: 'var(--color-text)' }}>{plural(productosListos, 'producto', 'productos')}</strong> en {almacenNombre},
                                contados <strong style={{ color: 'var(--color-text)' }}>{fechaTexto}</strong>.
                            </p>
                            <p className="text-[13px]">
                                {reemplaza > 0 && <>{reemplaza === 1 ? '1 ya tenía inventario inicial: se reemplaza. ' : `${fmtInt(reemplaza)} ya tenían inventario inicial: se reemplazan. `}</>}
                                {conteo.revisar > 0 && <>Las {fmtInt(conteo.revisar)} por revisar no se cargan hasta que elijas su producto.</>}
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <Button variant="ghost" onClick={() => setPaso(2)} startContent={<ArrowLeft size={15} />}>Columnas</Button>
                            <Button variant="success" onClick={guardar} disabled={productosListos === 0} loading={guardando} startContent={<CheckCircle2 size={16} />}>
                                Guardar inventario inicial
                            </Button>
                        </div>
                    </footer>
                </div>
            )}
        </section>
    );
}

/* ── Fila de la revisión ───────────────────────────────────────────────── */

function FilaExcel({ r, producto, unidadId, estado, onProducto, onUnidad, onOmitir }: {
    r: FilaRevision; producto: ProductoRes | null; unidadId: number | null; estado: EstadoFinal;
    onProducto: (p: ProductoRes) => void; onUnidad: (id: number) => void; onOmitir: (o: boolean) => void;
}) {
    const [buscando, setBuscando] = useState(false);
    const unidad = producto?.unidades.find(u => u.id === unidadId);
    const color = { listo: 'var(--vp-mint)', revisar: 'var(--vp-amber)', error: 'var(--vp-coral)', omitida: 'var(--color-border)' }[estado];

    return (
        <tr className="align-top" style={{
            borderTop: '1px solid color-mix(in srgb, var(--color-border) 70%, transparent)',
            boxShadow: `inset 3px 0 0 ${color}`,
            opacity: estado === 'omitida' ? 0.55 : 1,
        }}>
            <td className="px-4 py-3 tabular-nums" style={{ color: 'var(--color-text-muted)' }}>{r.fila}</td>
            <td className="px-3 py-3 max-w-[260px]">
                <p className="font-medium break-words" style={{ color: 'var(--color-text)' }}>{r.texto}</p>
                <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>
                    {[r.codigo && r.codigo !== r.texto ? `cód. ${r.codigo}` : null, r.unidad ? `en ${r.unidad}` : null].filter(Boolean).join(', ')}
                </p>
            </td>
            <td className="px-3 py-3 min-w-[260px]">
                {producto && !buscando ? (
                    <div>
                        <p className="font-semibold flex items-start gap-1.5" style={{ color: 'var(--color-text)' }}>
                            <Check size={15} className="mt-0.5 flex-shrink-0" style={{ color: 'var(--vp-mint-ink)' }} />
                            <span>{producto.nombre}</span>
                        </p>
                        <p className="text-[13px] pl-5" style={{ color: 'var(--color-text-muted)' }}>
                            {producto.codigo ? `cód. ${producto.codigo}` : ''}
                            {estado !== 'omitida' && <button onClick={() => setBuscando(true)} className="ml-2 font-semibold hover:underline" style={{ color: 'var(--color-primary)' }}>cambiar</button>}
                        </p>
                    </div>
                ) : buscando || r.sugerencias.length === 0 ? (
                    <ProductoPicker autoFocus={buscando} inicial={r.texto} onElegir={p => { onProducto(p); setBuscando(false); }} onCancelar={buscando ? () => setBuscando(false) : undefined} />
                ) : (
                    <div className="space-y-1.5">
                        {r.sugerencias.map(s => (
                            <button key={s.id} onClick={() => onProducto(s)}
                                className="flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-left transition-colors hover:brightness-95"
                                style={{ backgroundColor: 'color-mix(in srgb, var(--vp-amber) 10%, var(--color-surface))', border: '1px solid color-mix(in srgb, var(--vp-amber) 30%, transparent)' }}>
                                <span className="font-medium truncate" style={{ color: 'var(--color-text)' }}>{s.nombre}</span>
                                <span className="text-xs font-semibold flex-shrink-0" style={{ color: 'var(--vp-amber-ink)' }}>Es este</span>
                            </button>
                        ))}
                        <button onClick={() => setBuscando(true)} className="text-[13px] font-semibold hover:underline" style={{ color: 'var(--color-primary)' }}>Buscar otro</button>
                    </div>
                )}
                {r.mensaje && estado !== 'listo' && estado !== 'omitida' && (
                    <p className="text-[13px] mt-1.5 flex items-start gap-1" style={{ color: estado === 'error' ? 'var(--vp-coral-ink)' : 'var(--vp-amber-ink)' }}>
                        <TriangleAlert size={13} className="mt-0.5 flex-shrink-0" /> {estado === 'revisar' && producto && !unidadId ? 'Elige en qué unidad está contado.' : r.mensaje}
                    </p>
                )}
                {r.repetido_de && estado === 'listo' && (
                    <p className="text-[13px] mt-1" style={{ color: 'var(--color-text-muted)' }}>También está en la fila {r.repetido_de}: se suman.</p>
                )}
                {r.ya_tenia && estado === 'listo' && (
                    <p className="text-[13px] mt-1" style={{ color: 'var(--color-text-muted)' }}>
                        Reemplaza el inicial de {fmtCant(r.ya_tenia.cantidad)} que tenía.
                    </p>
                )}
            </td>
            <td className="px-3 py-3 text-right">
                <p className="font-bold tabular-nums" style={{ color: r.cantidad === null ? 'var(--vp-coral-ink)' : 'var(--color-text)' }}>
                    {r.cantidad === null ? 'vacía' : fmtCant(r.cantidad)}
                </p>
                {producto && (producto.unidades.length > 1 || !unidadId) ? (
                    <div className="mt-1 flex justify-end">
                        <div className="w-36">
                            <Select size="sm" ariaLabel="Unidad en que está contado" value={unidadId ?? ''} placeholder="Unidad…"
                                onChange={v => onUnidad(Number(v))}
                                options={producto.unidades.map(u => ({ value: u.id, label: u.factor !== 1 ? `${u.nombre} (x${fmtCant(u.factor)})` : u.nombre }))} />
                        </div>
                    </div>
                ) : unidad ? (
                    <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>{unidad.abrev ?? unidad.nombre}</p>
                ) : null}
                {unidad && unidad.factor !== 1 && r.cantidad !== null && (
                    <p className="text-[13px]" style={{ color: 'var(--color-text-muted)' }}>= {fmtCant(r.cantidad * unidad.factor)} en unidad base</p>
                )}
            </td>
            <td className="px-3 py-3 text-right tabular-nums" style={{ color: r.costo === null ? 'var(--color-text-muted)' : 'var(--color-text)' }}>
                {r.costo === null ? 'el actual' : fmtS(r.costo)}
            </td>
            <td className="px-4 py-3 text-right">
                <button onClick={() => onOmitir(estado !== 'omitida')} className="text-[13px] font-semibold hover:underline"
                    style={{ color: estado === 'omitida' ? 'var(--color-primary)' : 'var(--color-text-muted)' }}>
                    {estado === 'omitida' ? 'Incluir' : 'Omitir'}
                </button>
            </td>
        </tr>
    );
}

/** Buscador de productos del catálogo (para las filas que no calzaron). */
function ProductoPicker({ inicial, autoFocus, onElegir, onCancelar }: {
    inicial: string; autoFocus?: boolean; onElegir: (p: ProductoRes) => void; onCancelar?: () => void;
}) {
    const [q, setQ] = useState('');
    const [res, setRes] = useState<ProductoRes[]>([]);
    const [abierto, setAbierto] = useState(false);

    useEffect(() => {
        if (q.trim().length < 2) { setRes([]); return; }
        const t = setTimeout(async () => {
            try {
                const { data } = await axios.get(route('inventario.inicial.buscar'), { params: { q } });
                setRes(data.productos);
                setAbierto(true);
            } catch { /* sin conexión: el usuario reintenta escribiendo */ }
        }, 250);
        return () => clearTimeout(t);
    }, [q]);

    return (
        <div className="relative">
            <div className="flex items-center gap-1.5">
                <div className="relative flex-1">
                    <Search size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }} />
                    <input autoFocus={autoFocus} value={q} onChange={e => setQ(e.target.value)} onFocus={() => res.length && setAbierto(true)}
                        onBlur={() => setTimeout(() => setAbierto(false), 150)}
                        placeholder={`Buscar "${inicial.slice(0, 24)}"…`} aria-label="Buscar producto del catálogo"
                        className="w-full text-sm rounded-lg pl-8 pr-2 py-1.5 border outline-none focus:ring-2"
                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
                </div>
                {onCancelar && (
                    <button onClick={onCancelar} aria-label="Cancelar búsqueda" className="p-1" style={{ color: 'var(--color-text-muted)' }}><X size={15} /></button>
                )}
            </div>
            {abierto && res.length > 0 && (
                <ul className="absolute z-20 mt-1 w-full max-h-60 overflow-y-auto rounded-xl shadow-lg"
                    style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
                    {res.map(p => (
                        <li key={p.id}>
                            <button onMouseDown={e => e.preventDefault()} onClick={() => { onElegir(p); setAbierto(false); }}
                                className="w-full text-left px-3 py-2 text-sm hover:brightness-95"
                                style={{ backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }}>
                                <span className="font-medium">{p.nombre}</span>
                                {p.codigo && <span className="ml-2 text-[13px]" style={{ color: 'var(--color-text-muted)' }}>{p.codigo}</span>}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
            {abierto && q.trim().length >= 2 && res.length === 0 && (
                <p className="text-[13px] mt-1" style={{ color: 'var(--color-text-muted)' }}>Sin resultados. Si no existe, créalo en Productos y vuelve a subir el Excel.</p>
            )}
        </div>
    );
}
