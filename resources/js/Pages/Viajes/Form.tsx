import { useEffect, useMemo, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import toast from 'react-hot-toast';
import { AlertTriangle, FileText, MapPin, PackagePlus, Plus, Receipt, Search, Trash2, Truck, UserPlus, Users, Wrench } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import SearchableSelect from '@/Components/UI/SearchableSelect';
import Callout from '@/Components/UI/Callout';
import {
    BarraCarga, Pastilla, TIPOS_VIAJE, fmtCantidad, fmtKg,
    type Carga, type EstadoLicencia, type TipoViaje,
} from '@/Components/Flota/FlotaUI';
import ModalVehiculo from '@/Components/Flota/ModalVehiculo';
import ModalConductor, { type TipoConductor } from '@/Components/Flota/ModalConductor';
import { hoyLocal } from '@/lib/fechas';
import { soles } from '@/lib/dinero';
import { avisoError } from '@/lib/avisoError';
import type { PageProps } from '@/types';

interface Opcion { value: number; label: string }

interface VehiculoOpt {
    id: number; placa: string; descripcion: string; capacidad_kg: number | null;
    conductor_id: number | null; conductor: string | null;
    estado: 'disponible' | 'taller' | 'fuera_servicio'; en_ruta: string | null;
    /** Límite por volumen: producto_id => unidades máximas. */
    capacidades: Record<number, number>;
}

interface ConductorOpt {
    id: number; nombre: string; tipo: 'chofer' | 'ayudante'; licencia: string | null;
    estado_licencia: EstadoLicencia; licencia_vence: string | null;
}

interface ItemDisp {
    id: number; producto_id: number; producto_nombre: string; unidad_nombre: string;
    factor_conversion: number; pendiente: number; en_viaje: number; disponible: number;
    peso_unitario: number | null;
}

interface PedidoDisp {
    id: number; venta_id: number; venta: string; cliente: string; direccion: string | null;
    telefono: string | null; ruta: string | null; programada: string | null; saldo_venta: number;
    items: ItemDisp[];
}

interface VentaDisp {
    id: number; numero: string; cliente: string; direccion: string | null; total?: number;
    /** `id` = línea de la venta; `peso_kg` = peso del catálogo; `peso_manual` = kg ya escritos (al editar). */
    items: { id: number; producto_id: number; factor: number; producto_nombre: string; unidad_nombre: string; cantidad: number; peso_kg: number | null; peso_manual?: number | null }[];
}

/** `pesos`: kg escritos a mano por línea; vacío = se calcula del catálogo. */
type Parada =
    | { modo: 'pendiente'; pedido: PedidoDisp; cantidades: Record<number, string>; pesos: Record<number, string> }
    | { modo: 'directa'; venta: VentaDisp; pesos: Record<number, string> };

interface ViajeEdit {
    id: number; numero: string; estado: string; tipo: TipoViaje; fecha: string; hora_salida: string;
    vehiculo_id: number; conductor_id: number | null; ayudantes: number[]; almacen_id: number | null;
    ruta_entrega_id: number | null; origen: string; destino: string; proveedor_id: number | null;
    entrada_id: number | null; observacion: string;
    resueltas: { id: number; venta: string | null; cliente: string; estado: string }[];
    paradas: (
        | { cliente_anticipo_id: number; items: { cliente_anticipo_item_id: number; cantidad: number; peso_kg: number | null }[] }
        | { venta_id: number; venta: string; cliente: string; direccion: string | null; items: VentaDisp['items'] }
    )[];
}

interface Props extends PageProps {
    viaje: ViajeEdit | null;
    vehiculos: VehiculoOpt[];
    conductores: ConductorOpt[];
    almacenes: Opcion[];
    rutas: Opcion[];
    proveedores: Opcion[];
    entradas: (Opcion & { proveedor_id: number | null })[];
    pendientes: PedidoDisp[];
    preseleccion: number | null;
    puede: { vehiculo: boolean; conductor: boolean; guia: boolean };
}

const num = (v: string | number | undefined) => {
    const n = Number(v);
    return Number.isFinite(n) ? n : 0;
};

/** Kg escritos a mano, o null si el campo está vacío. */
const kgEscrito = (v: string | undefined): number | null => (v === undefined || v.trim() === '' ? null : num(v));

/** Peso de una línea de pedido: el escrito a mano o el del catálogo por la cantidad. */
const pesoLineaPedido = (it: ItemDisp, cantidad: number, escrito: string | undefined) =>
    kgEscrito(escrito) ?? (it.peso_unitario == null ? null : cantidad * it.peso_unitario);

export default function ViajeForm({ viaje, vehiculos, conductores, almacenes, rutas, proveedores, entradas, pendientes, preseleccion, puede }: Props) {
    const editando = !!viaje;

    const [tipo, setTipo] = useState<TipoViaje>(viaje?.tipo ?? 'reparto');
    const [fecha, setFecha] = useState(viaje?.fecha ?? hoyLocal());
    const [hora, setHora] = useState(viaje?.hora_salida ?? '');
    const [vehiculoId, setVehiculoId] = useState<number | null>(viaje?.vehiculo_id ?? (vehiculos.filter(v => v.estado === 'disponible' && !v.en_ruta).length === 1 ? vehiculos.find(v => v.estado === 'disponible' && !v.en_ruta)!.id : null));
    const [choferId, setChoferId] = useState<number | null>(viaje?.conductor_id ?? null);
    const [ayudantes, setAyudantes] = useState<number[]>(viaje?.ayudantes ?? []);
    const [almacenId, setAlmacenId] = useState<number | null>(viaje?.almacen_id ?? (almacenes.length === 1 ? almacenes[0].value : null));
    const [rutaId, setRutaId] = useState<number | null>(viaje?.ruta_entrega_id ?? null);
    const [origen, setOrigen] = useState(viaje?.origen ?? '');
    const [destino, setDestino] = useState(viaje?.destino ?? '');
    const [proveedorId, setProveedorId] = useState<number | null>(viaje?.proveedor_id ?? null);
    const [entradaId, setEntradaId] = useState<number | null>(viaje?.entrada_id ?? null);
    const [observacion, setObservacion] = useState(viaje?.observacion ?? '');
    const [paradas, setParadas] = useState<Parada[]>(() => paradasIniciales(viaje, pendientes, preseleccion));
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [guardando, setGuardando] = useState(false);
    // Altas sin salir del viaje: el camión o la persona nuevos quedan elegidos.
    const [nuevoVehiculo, setNuevoVehiculo] = useState(false);
    const [nuevoConductor, setNuevoConductor] = useState<TipoConductor | null>(null);

    const vehiculo = vehiculos.find(v => v.id === vehiculoId) ?? null;
    const choferes = conductores.filter(c => c.tipo === 'chofer');
    const chofer = conductores.find(c => c.id === choferId) ?? null;
    const llevaEntregas = tipo !== 'abastecimiento';

    // Elegir el vehículo propone a su chofer asignado (si no se eligió otro a mano).
    const choferManual = useRef(!!viaje?.conductor_id);
    function elegirVehiculo(id: number) {
        setVehiculoId(id);
        const v = vehiculos.find(x => x.id === id);
        if (v?.conductor_id && !choferManual.current) setChoferId(v.conductor_id);
    }
    useEffect(() => {
        if (!viaje && vehiculo?.conductor_id && !choferId) setChoferId(vehiculo.conductor_id);
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    // Lo cargado, en unidades base con su peso: sirve para el camión elegido y
    // para sugerir otro (el % que ocuparía en cada uno).
    const lineas: LineaCarga[] = useMemo(() => {
        const out: LineaCarga[] = [];
        for (const p of paradas) {
            if (p.modo === 'pendiente') {
                for (const it of p.pedido.items) {
                    const c = num(p.cantidades[it.id]);
                    if (c <= 0) continue;
                    out.push({ producto_id: it.producto_id, unidades: c * (it.factor_conversion || 1), peso_kg: pesoLineaPedido(it, c, p.pesos[it.id]) });
                }
            } else {
                for (const it of p.venta.items) {
                    out.push({ producto_id: it.producto_id, unidades: it.cantidad * (it.factor || 1), peso_kg: kgEscrito(p.pesos[it.id]) ?? it.peso_kg });
                }
            }
        }
        return out;
    }, [paradas]);

    const carga: Carga = useMemo(() => usoDeCarga(lineas, vehiculo?.capacidad_kg ?? 0, vehiculo?.capacidades ?? {}), [lineas, vehiculo]);

    // Camión sugerido: el que queda más lleno sin pasarse, entre los que pueden salir.
    const usoPorVehiculo = useMemo(() => Object.fromEntries(vehiculos.map(v => [v.id, usoDeCarga(lineas, v.capacidad_kg ?? 0, v.capacidades ?? {}).porcentaje])), [lineas, vehiculos]);
    const sugerido = useMemo(() => {
        if (lineas.length === 0) return null;
        const libres = vehiculos.filter(v => v.estado === 'disponible' && !v.en_ruta && usoPorVehiculo[v.id] != null && usoPorVehiculo[v.id]! <= 100);
        return libres.sort((a, b) => usoPorVehiculo[b.id]! - usoPorVehiculo[a.id]!)[0]?.id ?? null;
    }, [lineas, vehiculos, usoPorVehiculo]);

    const enUso = useMemo(() => new Set(paradas.filter(p => p.modo === 'pendiente').map(p => (p as Extract<Parada, { modo: 'pendiente' }>).pedido.id)), [paradas]);
    const ventasEnUso = useMemo(() => new Set(paradas.filter(p => p.modo === 'directa').map(p => (p as Extract<Parada, { modo: 'directa' }>).venta.id)), [paradas]);

    function agregarPedido(p: PedidoDisp) {
        if (enUso.has(p.id)) return;
        setParadas(prev => [...prev, { modo: 'pendiente', pedido: p, cantidades: Object.fromEntries(p.items.map(i => [i.id, String(i.disponible)])), pesos: {} }]);
    }
    function agregarVenta(v: VentaDisp) {
        if (ventasEnUso.has(v.id)) return;
        setParadas(prev => [...prev, { modo: 'directa', venta: v, pesos: {} }]);
    }
    const quitar = (idx: number) => setParadas(prev => prev.filter((_, i) => i !== idx));
    const mover = (idx: number, dir: -1 | 1) => setParadas(prev => {
        const j = idx + dir;
        if (j < 0 || j >= prev.length) return prev;
        const copia = [...prev];
        [copia[idx], copia[j]] = [copia[j], copia[idx]];
        return copia;
    });
    function cambiarCantidad(idx: number, itemId: number, valor: string) {
        setParadas(prev => prev.map((p, i) => i === idx && p.modo === 'pendiente' ? { ...p, cantidades: { ...p.cantidades, [itemId]: valor } } : p));
    }
    function cambiarPeso(idx: number, itemId: number, valor: string) {
        setParadas(prev => prev.map((p, i) => i === idx ? { ...p, pesos: { ...p.pesos, [itemId]: valor } } : p));
    }

    /** Lo recién creado es el id que no estaba antes de guardar. */
    function vehiculoCreado(page: { props: Record<string, unknown> }) {
        const antes = new Set(vehiculos.map(v => v.id));
        const nuevo = (page.props.vehiculos as VehiculoOpt[]).find(v => !antes.has(v.id));
        if (!nuevo) return;
        setVehiculoId(nuevo.id);
        if (nuevo.conductor_id && !choferManual.current) setChoferId(nuevo.conductor_id);
        toast.success(`Camión ${nuevo.placa} agregado y elegido.`);
    }

    function conductorCreado(page: { props: Record<string, unknown> }) {
        const antes = new Set(conductores.map(c => c.id));
        const nuevo = (page.props.conductores as ConductorOpt[]).find(c => !antes.has(c.id));
        if (!nuevo) return;
        // Un chofer nuevo va al volante solo si todavía no hay chofer; si no, de ayudante.
        if (nuevo.tipo === 'chofer' && (nuevoConductor === 'chofer' || !choferId)) {
            choferManual.current = true;
            setChoferId(nuevo.id);
            setAyudantes(a => a.filter(x => x !== nuevo.id));
            toast.success(`${nuevo.nombre} agregado como chofer del viaje.`);
        } else {
            setAyudantes(a => [...a, nuevo.id]);
            toast.success(`${nuevo.nombre} agregado como ayudante del viaje.`);
        }
    }

    function guardar(hacerGuias = false) {
        setGuardando(true);
        setErrores({});
        const payload = {
            tipo, fecha, hora_salida: hora || null, vehiculo_id: vehiculoId, conductor_id: choferId,
            ayudantes, almacen_id: almacenId, ruta_entrega_id: llevaEntregas ? rutaId : null,
            origen, destino, proveedor_id: tipo === 'abastecimiento' ? proveedorId : null,
            entrada_id: tipo === 'abastecimiento' ? entradaId : null, observacion,
            paradas: llevaEntregas ? paradas.map(p => p.modo === 'pendiente'
                ? { cliente_anticipo_id: p.pedido.id, items: p.pedido.items.map(i => ({ cliente_anticipo_item_id: i.id, cantidad: num(p.cantidades[i.id]), peso_kg: kgEscrito(p.pesos[i.id]) })) }
                : { venta_id: p.venta.id, pesos: p.venta.items.map(i => ({ venta_item_id: i.id, peso_kg: kgEscrito(p.pesos[i.id]) })) }) : [],
            hacer_guias: hacerGuias,
        };
        const opts = {
            preserveScroll: true,
            onError: (e: Record<string, string>) => { setErrores(e); avisoError(Object.values(e)[0]); },
            onFinish: () => setGuardando(false),
        };
        editando ? router.put(route('viajes.update', viaje!.id), payload, opts) : router.post(route('viajes.store'), payload, opts);
    }

    const errorParada = (idx: number) => Object.entries(errores).find(([k]) => k === `paradas.${idx}` || k.startsWith(`paradas.${idx}.`))?.[1];
    const sinVehiculos = vehiculos.length === 0;
    const pasado = carga.porcentaje !== null && carga.porcentaje > 100;
    const conGuias = puede.guia && llevaEntregas && paradas.length > 0;

    return (
        <AppLayout title={editando ? `Editar ${viaje!.numero}` : 'Nuevo viaje'}>
            <PageHeader
                title={editando ? `Editar viaje ${viaje!.numero}` : 'Nuevo viaje'}
                subtitle="Elige el camión y el chofer, y carga los pedidos que van. Nada sale del stock hasta marcar cada entrega."
                backHref={editando ? route('viajes.show', viaje!.id) : route('viajes.index')}
                icon={<Truck size={20} />}
            />

            {sinVehiculos && !puede.vehiculo && (
                <Callout variant="warning" className="mb-4" title="Todavía no hay vehículos">
                    Para armar un viaje hace falta al menos un vehículo. Pide a quien administra la flota que lo registre.
                </Callout>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] gap-5 items-start">
                {/* ── Datos del viaje ── */}
                <section className="space-y-4">
                    <Tarjeta titulo="Tipo de viaje">
                        <div role="radiogroup" aria-label="Tipo de viaje" className="grid grid-cols-3 gap-2">
                            {(Object.keys(TIPOS_VIAJE) as TipoViaje[]).map(t => {
                                const T = TIPOS_VIAJE[t];
                                const activo = tipo === t;
                                return (
                                    <button key={t} type="button" role="radio" aria-checked={activo} onClick={() => setTipo(t)} title={T.ayuda}
                                        className="rounded-xl px-2 py-2.5 border flex flex-col items-center gap-1 text-center transition-colors duration-150"
                                        style={{
                                            borderColor: activo ? 'var(--vp-navy)' : 'var(--color-border)',
                                            backgroundColor: activo ? 'color-mix(in srgb, var(--vp-navy) 7%, var(--color-surface))' : 'var(--color-surface)',
                                            color: activo ? 'var(--vp-navy)' : 'var(--color-text-muted)',
                                        }}>
                                        <T.Icono size={18} />
                                        <span className="text-[13px] font-semibold">{T.label}</span>
                                    </button>
                                );
                            })}
                        </div>
                        <p className="text-[13px] mt-2" style={{ color: 'var(--color-text-muted)' }}>{TIPOS_VIAJE[tipo].ayuda}.</p>
                    </Tarjeta>

                    <Tarjeta titulo="Cuándo sale">
                        <div className="grid grid-cols-2 gap-3">
                            <Input label="Fecha" type="date" required value={fecha} onChange={e => setFecha(e.target.value)} error={errores.fecha} />
                            <Input label="Hora de salida" type="time" value={hora} onChange={e => setHora(e.target.value)} error={errores.hora_salida} hint="Opcional" />
                        </div>
                    </Tarjeta>

                    <Tarjeta titulo="Vehículo" error={errores.vehiculo_id}>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2" role="radiogroup" aria-label="Vehículo">
                            {vehiculos.map(v => {
                                const noOperativo = v.estado !== 'disponible';
                                const activo = vehiculoId === v.id;
                                return (
                                    <button key={v.id} type="button" role="radio" aria-checked={activo} disabled={noOperativo && !activo}
                                        onClick={() => elegirVehiculo(v.id)}
                                        className="rounded-xl p-2.5 border text-left transition-[transform,border-color] duration-150 ease-out enabled:hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed"
                                        style={{
                                            borderColor: activo ? 'var(--vp-navy)' : 'var(--color-border)',
                                            backgroundColor: activo ? 'color-mix(in srgb, var(--vp-navy) 6%, var(--color-surface))' : 'var(--color-surface)',
                                            boxShadow: activo ? '0 0 0 1px var(--vp-navy)' : undefined,
                                        }}>
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="font-mono text-[13px] font-bold tracking-wider px-1.5 py-0.5 rounded border-2"
                                                style={{ borderColor: 'var(--vp-navy)', color: 'var(--vp-navy)', backgroundColor: 'color-mix(in srgb, var(--vp-amber) 18%, var(--color-surface))' }}>
                                                {v.placa}
                                            </span>
                                            {lineas.length > 0 && usoPorVehiculo[v.id] != null
                                                ? <span className="text-[13px] tabular-nums font-bold" style={{ color: usoPorVehiculo[v.id]! > 100 ? 'var(--color-danger)' : 'var(--color-text-muted)' }}>{usoPorVehiculo[v.id]}%</span>
                                                : v.capacidad_kg ? <span className="text-[13px] tabular-nums font-semibold" style={{ color: 'var(--color-text-muted)' }}>{fmtKg(v.capacidad_kg)}</span> : null}
                                        </div>
                                        <p className="text-[13px] mt-1 truncate" style={{ color: 'var(--color-text)' }}>
                                            {sugerido === v.id && <span className="font-bold mr-1" style={{ color: 'var(--vp-mint-ink)' }}>Sugerido ·</span>}
                                            {v.descripcion || '—'}
                                        </p>
                                        <p className="text-[12px] truncate" style={{ color: noOperativo || v.en_ruta ? 'var(--vp-amber-ink)' : 'var(--color-text-muted)' }}>
                                            {v.estado === 'taller' ? <><Wrench size={11} className="inline mr-1" />En taller</>
                                                : v.estado === 'fuera_servicio' ? 'Fuera de servicio'
                                                : v.en_ruta ? `En ruta con ${v.en_ruta}`
                                                : v.conductor ?? 'Sin chofer asignado'}
                                        </p>
                                    </button>
                                );
                            })}
                            {puede.vehiculo && (
                                <BotonNuevo onClick={() => setNuevoVehiculo(true)} destacado={sinVehiculos}
                                    titulo={sinVehiculos ? 'Registra tu primer camión' : 'Nuevo camión'}
                                    ayuda="Placa y carga máxima; queda elegido" />
                            )}
                        </div>
                    </Tarjeta>

                    <Tarjeta titulo="Quiénes van">
                        <div className="space-y-3">
                            <div className="flex items-end gap-2">
                                <div className="flex-1 min-w-0">
                                    <Select label="Chofer" value={choferId ?? ''} placeholder="Elegir chofer…" error={errores.conductor_id}
                                        onChange={v => { choferManual.current = true; setChoferId(v === '' ? null : Number(v)); setAyudantes(a => a.filter(x => x !== Number(v))); }}
                                        options={[{ value: '', label: choferes.length ? 'Sin chofer todavía' : 'No hay choferes: agrega uno →' }, ...choferes.map(c => ({ value: c.id, label: c.nombre }))]} />
                                </div>
                                {puede.conductor && (
                                    <Button variant="secondary" onClick={() => setNuevoConductor('chofer')} startContent={<UserPlus size={15} />}
                                        className={`flex-shrink-0 ${errores.conductor_id ? 'mb-6' : ''}`} aria-label="Agregar un chofer nuevo">Nuevo</Button>
                                )}
                            </div>
                            {!errores.conductor_id && <p className="-mt-2 text-xs" style={{ color: 'var(--color-text-muted)' }}>Hace falta para que el viaje salga</p>}
                            {chofer && (chofer.estado_licencia === 'vencida' || chofer.estado_licencia === 'por_vencer') && (
                                <p className="text-[13px] flex items-start gap-1.5" style={{ color: chofer.estado_licencia === 'vencida' ? 'var(--vp-coral-ink)' : 'var(--vp-amber-ink)' }}>
                                    <AlertTriangle size={14} className="flex-shrink-0 mt-0.5" />
                                    {chofer.estado_licencia === 'vencida' ? 'La licencia de este chofer está vencida.' : 'La licencia de este chofer vence pronto.'}
                                </p>
                            )}
                            {(conductores.filter(c => c.id !== choferId).length > 0 || puede.conductor) && (
                                <div>
                                    <p className="text-sm font-medium mb-1.5 flex items-center gap-1.5" style={{ color: 'var(--color-text)' }}><Users size={14} /> Ayudantes</p>
                                    <div className="flex flex-wrap gap-1.5">
                                        {conductores.filter(c => c.id !== choferId).map(c => {
                                            const on = ayudantes.includes(c.id);
                                            return (
                                                <button key={c.id} type="button" aria-pressed={on}
                                                    onClick={() => setAyudantes(a => on ? a.filter(x => x !== c.id) : [...a, c.id])}
                                                    className="px-3 py-1.5 rounded-full text-[13px] font-semibold border transition-colors duration-150"
                                                    style={{
                                                        borderColor: on ? 'var(--vp-navy)' : 'var(--color-border)',
                                                        backgroundColor: on ? 'var(--vp-navy)' : 'var(--color-surface)',
                                                        color: on ? '#fff' : 'var(--color-text-muted)',
                                                    }}>
                                                    {c.nombre}
                                                </button>
                                            );
                                        })}
                                        {puede.conductor && (
                                            <button type="button" onClick={() => setNuevoConductor('ayudante')}
                                                className="px-3 py-1.5 rounded-full text-[13px] font-semibold border border-dashed inline-flex items-center gap-1 transition-colors duration-150 hover:bg-black/[0.03]"
                                                style={{ borderColor: 'var(--color-border)', color: 'var(--color-primary)' }}>
                                                <Plus size={13} /> Ayudante
                                            </button>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>
                    </Tarjeta>

                    <Tarjeta titulo={tipo === 'abastecimiento' ? 'De dónde trae la carga' : 'Ruta'}>
                        <div className="space-y-3">
                            {tipo === 'abastecimiento' ? (
                                <>
                                    <SearchableSelect label="Proveedor o fábrica" value={proveedorId ?? ''} placeholder="Elegir proveedor…"
                                        onChange={(v: string | number) => { setProveedorId(v === '' ? null : Number(v)); setEntradaId(null); }}
                                        options={proveedores} />
                                    <Input label="Origen" value={origen} onChange={e => setOrigen(e.target.value)} placeholder="Fábrica, cantera, dirección…" error={errores.origen} />
                                    <Select label="Entrada (compra) que trae" value={entradaId ?? ''} placeholder="Opcional"
                                        onChange={v => setEntradaId(v === '' ? null : Number(v))}
                                        options={[{ value: '', label: 'Todavía no registrada' }, ...entradas.filter(e => !proveedorId || e.proveedor_id === proveedorId)]}
                                        hint="El stock lo suma la entrada, no el viaje" />
                                    {almacenes.length > 1 && (
                                        <Select label="Almacén de llegada" value={almacenId ?? ''} onChange={v => setAlmacenId(v === '' ? null : Number(v))}
                                            options={[{ value: '', label: 'Sin indicar' }, ...almacenes]} />
                                    )}
                                </>
                            ) : (
                                <>
                                    {rutas.length > 0 && (
                                        <Select label="Ruta o zona" value={rutaId ?? ''} onChange={v => setRutaId(v === '' ? null : Number(v))}
                                            options={[{ value: '', label: 'Sin ruta' }, ...rutas]} />
                                    )}
                                    {almacenes.length > 1 && (
                                        <Select label="Almacén de carga" value={almacenId ?? ''} onChange={v => setAlmacenId(v === '' ? null : Number(v))}
                                            options={[{ value: '', label: 'Sin indicar' }, ...almacenes]} />
                                    )}
                                    {tipo === 'flete' && (
                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <Input label="Origen" value={origen} onChange={e => setOrigen(e.target.value)} error={errores.origen} />
                                            <Input label="Destino" value={destino} onChange={e => setDestino(e.target.value)} placeholder="Cuyumalca, obra…" error={errores.destino} />
                                        </div>
                                    )}
                                </>
                            )}
                            <Input label="Observación" value={observacion} onChange={e => setObservacion(e.target.value)} placeholder="Opcional" error={errores.observacion} />
                        </div>
                    </Tarjeta>
                </section>

                {/* ── Carga ── */}
                <section className="space-y-4 lg:sticky lg:top-4">
                    {llevaEntregas ? (
                        <>
                            <div className="rounded-2xl p-4" style={{ backgroundColor: 'var(--color-surface)', border: `1px solid ${pasado ? 'var(--color-danger)' : 'var(--color-border)'}` }}>
                                <div className="flex items-center justify-between gap-3 mb-3">
                                    <h2 className="text-base font-bold" style={{ color: 'var(--color-text)' }}>
                                        Lo que lleva <span className="font-normal tabular-nums" style={{ color: 'var(--color-text-muted)' }}>· {paradas.length} {paradas.length === 1 ? 'parada' : 'paradas'}</span>
                                    </h2>
                                    {vehiculo && <span className="font-mono text-[13px] font-bold" style={{ color: 'var(--vp-navy)' }}>{vehiculo.placa}</span>}
                                </div>
                                <BarraCarga carga={carga} />
                                {pasado && (
                                    <p className="mt-2 text-[13px] font-semibold flex items-center gap-1.5" style={{ color: 'var(--color-danger)' }}>
                                        <AlertTriangle size={14} /> Pasa la carga máxima de {vehiculo?.placa}: baja cantidades o reparte en otro viaje.
                                    </p>
                                )}

                                {viaje && viaje.resueltas.length > 0 && (
                                    <p className="mt-3 text-[13px]" style={{ color: 'var(--color-text-muted)' }}>
                                        Ya resueltas (no se cambian): {viaje.resueltas.map(r => `${r.venta ?? '—'} ${r.estado === 'entregado' ? 'entregada' : 'no entregada'}`).join(', ')}.
                                    </p>
                                )}

                                {paradas.length === 0 ? (
                                    <div className="mt-4 rounded-xl border border-dashed py-8 text-center text-sm" style={{ borderColor: 'var(--color-border)', color: 'var(--color-text-muted)' }}>
                                        Agrega abajo los pedidos que van en este viaje.
                                    </div>
                                ) : (
                                    <ol className="mt-4 space-y-2.5">
                                        {paradas.map((p, idx) => (
                                            <ParadaFila key={p.modo === 'pendiente' ? `p${p.pedido.id}` : `v${p.venta.id}`}
                                                parada={p} idx={idx} total={paradas.length} error={errorParada(idx)}
                                                onCantidad={(itemId, v) => cambiarCantidad(idx, itemId, v)}
                                                onPeso={(itemId, v) => cambiarPeso(idx, itemId, v)}
                                                onQuitar={() => quitar(idx)} onMover={d => mover(idx, d)} />
                                        ))}
                                    </ol>
                                )}
                                {errores.paradas && <p className="mt-2 text-[13px]" style={{ color: 'var(--color-danger)' }}>{errores.paradas}</p>}
                            </div>

                            <PedidosDisponibles inicial={pendientes} viajeId={viaje?.id ?? null} enUso={enUso} onAgregar={agregarPedido} />
                            <VentasEntregadas viajeId={viaje?.id ?? null} enUso={ventasEnUso} onAgregar={agregarVenta} destacado={tipo === 'flete'} />
                        </>
                    ) : (
                        <Callout variant="info" title="Viaje de abastecimiento">
                            Trae carga al almacén: no lleva entregas a clientes. Lo que llega se suma al stock con la entrada (compra) de siempre; el viaje solo deja constancia del camión, el chofer y la vuelta.
                        </Callout>
                    )}

                    <div className="flex flex-wrap justify-end gap-2">
                        <Button variant="ghost" onClick={() => router.visit(editando ? route('viajes.show', viaje!.id) : route('viajes.index'))} disabled={guardando}>Cancelar</Button>
                        {conGuias ? (
                            <>
                                <Button variant="secondary" onClick={() => guardar(false)} loading={guardando} disabled={!vehiculoId}>
                                    {editando ? 'Guardar cambios' : 'Programar viaje'}
                                </Button>
                                <Button onClick={() => guardar(true)} loading={guardando} disabled={!vehiculoId} startContent={<FileText size={15} />}
                                    title="Guarda el viaje y abre la guía de la primera parada; al emitirla sigue con la siguiente">
                                    {editando ? 'Guardar y hacer guías' : 'Programar y hacer guías'}
                                </Button>
                            </>
                        ) : (
                            <Button onClick={() => guardar(false)} loading={guardando} disabled={!vehiculoId}>
                                {editando ? 'Guardar cambios' : 'Programar viaje'}
                            </Button>
                        )}
                    </div>
                </section>
            </div>

            <ModalVehiculo isOpen={nuevoVehiculo} onClose={() => setNuevoVehiculo(false)} rapido
                choferes={choferes.map(c => ({ value: c.id, label: c.nombre }))} conductorInicial={choferId}
                only={['vehiculos']} onGuardado={vehiculoCreado} />
            <ModalConductor isOpen={nuevoConductor !== null} onClose={() => setNuevoConductor(null)} rapido
                tipoInicial={nuevoConductor ?? 'chofer'} only={['conductores']} onGuardado={conductorCreado} />
        </AppLayout>
    );
}

/** Tarjeta punteada para dar de alta algo sin salir del viaje. */
function BotonNuevo({ onClick, titulo, ayuda, destacado = false }: { onClick: () => void; titulo: string; ayuda: string; destacado?: boolean }) {
    return (
        <button type="button" onClick={onClick}
            className="rounded-xl p-2.5 border border-dashed text-left flex items-center gap-2.5 transition-[transform,background-color] duration-150 ease-out hover:-translate-y-0.5 hover:bg-[color-mix(in_srgb,var(--color-primary)_5%,transparent)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)]"
            style={{ borderColor: destacado ? 'var(--color-primary)' : 'var(--color-border)', minHeight: 76 }}>
            <span className="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                style={{ backgroundColor: 'color-mix(in srgb, var(--color-primary) 12%, transparent)', color: 'var(--color-primary)' }}>
                <Plus size={16} />
            </span>
            <span className="min-w-0">
                <span className="block text-sm font-semibold" style={{ color: 'var(--color-primary)' }}>{titulo}</span>
                <span className="block text-[12px]" style={{ color: 'var(--color-text-muted)' }}>{ayuda}</span>
            </span>
        </button>
    );
}

function paradasIniciales(viaje: ViajeEdit | null, pendientes: PedidoDisp[], preseleccion: number | null): Parada[] {
    if (!viaje) {
        const p = preseleccion ? pendientes.find(x => x.id === preseleccion) : null;
        return p ? [{ modo: 'pendiente', pedido: p, cantidades: Object.fromEntries(p.items.map(i => [i.id, String(i.disponible)])), pesos: {} }] : [];
    }
    const out: Parada[] = [];
    for (const p of viaje.paradas) {
        if ('venta_id' in p) {
            out.push({ modo: 'directa', venta: { id: p.venta_id, numero: p.venta, cliente: p.cliente, direccion: p.direccion, items: p.items },
                pesos: Object.fromEntries(p.items.filter(i => i.peso_manual != null).map(i => [i.id, String(i.peso_manual)])) });
        } else {
            const pedido = pendientes.find(x => x.id === p.cliente_anticipo_id);
            if (!pedido) continue; // ya no tiene nada por entregar
            out.push({ modo: 'pendiente', pedido, cantidades: Object.fromEntries(p.items.map(i => [i.cliente_anticipo_item_id, String(i.cantidad)])),
                pesos: Object.fromEntries(p.items.filter(i => i.peso_kg != null).map(i => [i.cliente_anticipo_item_id, String(i.peso_kg)])) });
        }
    }
    return out;
}

function Tarjeta({ titulo, error, children }: { titulo: string; error?: string; children: React.ReactNode }) {
    return (
        <div className="rounded-2xl p-4" style={{ backgroundColor: 'var(--color-surface)', border: `1px solid ${error ? 'var(--color-danger)' : 'var(--color-border)'}` }}>
            <h2 className="text-sm font-bold mb-3" style={{ color: 'var(--color-text)' }}>{titulo}</h2>
            {children}
            {error && <p className="mt-2 text-[13px]" style={{ color: 'var(--color-danger)' }}>{error}</p>}
        </div>
    );
}

function ParadaFila({ parada, idx, total, error, onCantidad, onPeso, onQuitar, onMover }: {
    parada: Parada; idx: number; total: number; error?: string;
    onCantidad: (itemId: number, v: string) => void; onPeso: (itemId: number, v: string) => void;
    onQuitar: () => void; onMover: (d: -1 | 1) => void;
}) {
    const esPedido = parada.modo === 'pendiente';
    const cliente = esPedido ? parada.pedido.cliente : parada.venta.cliente;
    const numero = esPedido ? parada.pedido.venta : parada.venta.numero;
    const direccion = esPedido ? parada.pedido.direccion : parada.venta.direccion;

    return (
        <li className="vp-fade-up rounded-xl p-3" style={{ backgroundColor: 'var(--color-bg)', border: `1px solid ${error ? 'var(--color-danger)' : 'var(--color-border)'}` }}>
            <div className="flex items-start gap-3">
                <span className="flex-shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-[13px] font-bold tabular-nums"
                    style={{ backgroundColor: 'var(--vp-navy)', color: '#fff' }}>{idx + 1}</span>
                <div className="flex-1 min-w-0">
                    <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <p className="text-sm font-bold truncate" style={{ color: 'var(--color-text)' }}>{cliente}</p>
                        <span className="text-[13px] font-semibold" style={{ color: 'var(--color-primary)' }}>{numero}</span>
                        {!esPedido && <Pastilla color="var(--vp-mint-ink)">Ya entregada al vender</Pastilla>}
                    </div>
                    {direccion && <p className="text-[13px] flex items-center gap-1 truncate" style={{ color: 'var(--color-text-muted)' }}><MapPin size={12} className="flex-shrink-0" />{direccion}</p>}

                    <div className="mt-2 space-y-1.5">
                        {esPedido ? parada.pedido.items.map(it => {
                            const v = parada.cantidades[it.id] ?? '0';
                            const exceso = num(v) > it.disponible + 0.00009;
                            return (
                                <div key={it.id} className="flex items-center gap-3">
                                    <div className="flex-1 min-w-0">
                                        <p className="text-[13px] font-medium truncate" style={{ color: 'var(--color-text)' }}>{it.producto_nombre}</p>
                                        <p className="text-[12px]" style={{ color: exceso ? 'var(--color-danger)' : 'var(--color-text-muted)' }}>
                                            Por cargar {fmtCantidad(it.disponible)} {it.unidad_nombre}
                                            {it.en_viaje > 0 ? ` · ${fmtCantidad(it.en_viaje)} ya en otro viaje` : ''}
                                        </p>
                                    </div>
                                    <input type="number" min={0} max={it.disponible} step="any" inputMode="decimal"
                                        aria-label={`Cantidad que se carga de ${it.producto_nombre}`}
                                        value={v} onChange={e => onCantidad(it.id, e.target.value)}
                                        className="w-20 h-10 rounded-lg border px-2 text-right text-sm tabular-nums outline-none focus:ring-2"
                                        style={{ borderColor: exceso ? 'var(--color-danger)' : 'var(--color-border)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)' }} />
                                    <InputKg producto={it.producto_nombre} valor={parada.pesos[it.id] ?? ''} onChange={x => onPeso(it.id, x)}
                                        auto={it.peso_unitario != null ? num(v) * it.peso_unitario : null} />
                                </div>
                            );
                        }) : parada.venta.items.map(it => (
                            <div key={it.id} className="flex items-center gap-3">
                                <p className="flex-1 min-w-0 text-[13px] flex justify-between gap-3" style={{ color: 'var(--color-text)' }}>
                                    <span className="truncate">{it.producto_nombre}</span>
                                    <span className="tabular-nums flex-shrink-0" style={{ color: 'var(--color-text-muted)' }}>{fmtCantidad(it.cantidad)} {it.unidad_nombre}</span>
                                </p>
                                <InputKg producto={it.producto_nombre} valor={parada.pesos[it.id] ?? ''} onChange={x => onPeso(it.id, x)} auto={it.peso_kg} />
                            </div>
                        ))}
                    </div>
                    {error && <p className="mt-1.5 text-[13px]" style={{ color: 'var(--color-danger)' }}>{error}</p>}
                </div>
                <div className="flex flex-col gap-1 flex-shrink-0">
                    <button type="button" onClick={onQuitar} aria-label="Quitar del viaje" title="Quitar del viaje"
                        className="p-1.5 rounded-lg transition-colors hover:bg-black/5" style={{ color: 'var(--color-danger)' }}><Trash2 size={15} /></button>
                    {total > 1 && (
                        <>
                            <button type="button" onClick={() => onMover(-1)} disabled={idx === 0} aria-label="Subir parada"
                                className="p-1 rounded-lg text-xs disabled:opacity-30 hover:bg-black/5" style={{ color: 'var(--color-text-muted)' }}>▲</button>
                            <button type="button" onClick={() => onMover(1)} disabled={idx === total - 1} aria-label="Bajar parada"
                                className="p-1 rounded-lg text-xs disabled:opacity-30 hover:bg-black/5" style={{ color: 'var(--color-text-muted)' }}>▼</button>
                        </>
                    )}
                </div>
            </div>
        </li>
    );
}

/**
 * Kg de la línea. Vacío = el del catálogo (se ve de guía en gris); lo escrito
 * manda, y sirve también para los productos que no tienen peso cargado.
 */
function InputKg({ producto, valor, auto, onChange }: { producto: string; valor: string; auto: number | null; onChange: (v: string) => void }) {
    const manual = valor.trim() !== '';
    const sinPeso = auto == null && !manual;
    return (
        <label className="relative flex-shrink-0" title={manual ? 'Peso escrito a mano: borra para volver al del catálogo' : auto != null ? 'Peso del catálogo: escribe para cambiarlo' : 'Este producto no tiene peso: escríbelo aquí'}>
            <input type="number" min={0} step="any" inputMode="decimal" aria-label={`Kilos de ${producto}`}
                value={valor} onChange={e => onChange(e.target.value)}
                placeholder={auto != null ? String(Math.round(auto * 10) / 10) : '¿kg?'}
                className="w-24 h-10 rounded-lg border pl-2 pr-7 text-right text-sm tabular-nums outline-none focus:ring-2 placeholder:opacity-70"
                style={{
                    borderColor: sinPeso ? 'var(--vp-amber)' : manual ? 'var(--vp-navy)' : 'var(--color-border)',
                    borderStyle: sinPeso ? 'dashed' : 'solid',
                    backgroundColor: manual ? 'color-mix(in srgb, var(--vp-navy) 5%, var(--color-surface))' : 'var(--color-surface)',
                    color: 'var(--color-text)',
                }} />
            <span className="absolute right-2 top-1/2 -translate-y-1/2 text-[12px] pointer-events-none" style={{ color: 'var(--color-text-muted)' }}>kg</span>
        </label>
    );
}

function PedidosDisponibles({ inicial, viajeId, enUso, onAgregar }: {
    inicial: PedidoDisp[]; viajeId: number | null; enUso: Set<number>; onAgregar: (p: PedidoDisp) => void;
}) {
    const [q, setQ] = useState('');
    const [lista, setLista] = useState<PedidoDisp[]>(inicial);
    const [cargando, setCargando] = useState(false);

    useEffect(() => {
        if (q.trim() === '') { setLista(inicial); return; }
        const t = setTimeout(async () => {
            setCargando(true);
            try {
                const { data } = await axios.get<PedidoDisp[]>(route('viajes.pendientes'), { params: { buscar: q.trim(), viaje: viajeId ?? undefined } });
                setLista(data);
            } finally { setCargando(false); }
        }, 300);
        return () => clearTimeout(t);
    }, [q]); // eslint-disable-line react-hooks/exhaustive-deps

    const visibles = lista.filter(p => !enUso.has(p.id));

    return (
        <div className="rounded-2xl" style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
            <div className="p-4 pb-3 flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-sm font-bold flex items-center gap-1.5" style={{ color: 'var(--color-text)' }}><PackagePlus size={16} /> Pedidos por entregar</h2>
                <div className="relative w-full sm:w-64">
                    <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }} />
                    <input type="search" value={q} onChange={e => setQ(e.target.value)} placeholder="Cliente, venta, dirección o producto" aria-label="Buscar pedido"
                        className="w-full h-10 text-sm rounded-xl pl-8 pr-3 border outline-none focus:ring-2"
                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }} />
                </div>
            </div>
            <ul className="max-h-[420px] overflow-y-auto px-2 pb-2" aria-busy={cargando}>
                {cargando && Array.from({ length: 3 }).map((_, i) => (
                    <li key={`s${i}`} className="h-16 mx-2 my-1.5 rounded-xl animate-pulse" style={{ backgroundColor: 'var(--color-bg)' }} />
                ))}
                {!cargando && visibles.length === 0 && (
                    <li className="px-3 py-6 text-center text-sm" style={{ color: 'var(--color-text-muted)' }}>
                        {q ? 'Ningún pedido coincide.' : 'No hay pedidos por cargar: todo está entregado o ya va en un viaje.'}
                    </li>
                )}
                {!cargando && visibles.map(p => {
                    const peso = p.items.reduce((s, i) => s + (i.peso_unitario ?? 0) * i.disponible, 0);
                    return (
                        <li key={p.id}>
                            <button type="button" onClick={() => onAgregar(p)}
                                className="w-full text-left rounded-xl px-3 py-2.5 flex items-start gap-3 transition-colors duration-150 hover:bg-[color-mix(in_srgb,var(--vp-navy)_5%,transparent)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)]">
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-semibold truncate" style={{ color: 'var(--color-text)' }}>
                                        {p.cliente} <span className="font-normal" style={{ color: 'var(--color-primary)' }}>{p.venta}</span>
                                    </p>
                                    <p className="text-[13px] truncate" style={{ color: 'var(--color-text-muted)' }}>
                                        {p.items.map(i => `${fmtCantidad(i.disponible)} ${i.producto_nombre}`).join(' · ')}
                                    </p>
                                    {(p.direccion || p.ruta) && (
                                        <p className="text-[12px] truncate" style={{ color: 'var(--color-text-muted)' }}>{[p.ruta, p.direccion].filter(Boolean).join(' · ')}</p>
                                    )}
                                </div>
                                <div className="text-right flex-shrink-0">
                                    {peso > 0 && <p className="text-[13px] tabular-nums font-semibold" style={{ color: 'var(--color-text)' }}>{fmtKg(peso)}</p>}
                                    {p.saldo_venta > 0.009 && <p className="text-[12px] font-semibold" style={{ color: 'var(--vp-coral-ink)' }}>Cobrar {soles(p.saldo_venta)}</p>}
                                    <span className="inline-flex items-center gap-1 text-[13px] font-semibold mt-0.5" style={{ color: 'var(--vp-navy)' }}><Plus size={13} /> Cargar</span>
                                </div>
                            </button>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}

function VentasEntregadas({ viajeId, enUso, onAgregar, destacado }: {
    viajeId: number | null; enUso: Set<number>; onAgregar: (v: VentaDisp) => void; destacado: boolean;
}) {
    const [abierto, setAbierto] = useState(destacado);
    const [q, setQ] = useState('');
    const [lista, setLista] = useState<VentaDisp[] | null>(null);
    const [cargando, setCargando] = useState(false);

    useEffect(() => { if (destacado) setAbierto(true); }, [destacado]);

    useEffect(() => {
        if (!abierto) return;
        const t = setTimeout(async () => {
            setCargando(true);
            try {
                const { data } = await axios.get<VentaDisp[]>(route('viajes.ventas'), { params: { buscar: q.trim() || undefined, viaje: viajeId ?? undefined } });
                setLista(data);
            } finally { setCargando(false); }
        }, q ? 300 : 0);
        return () => clearTimeout(t);
    }, [q, abierto]); // eslint-disable-line react-hooks/exhaustive-deps

    if (!abierto) {
        return (
            <button type="button" onClick={() => setAbierto(true)}
                className="w-full rounded-2xl px-4 py-3 text-left text-sm flex items-center gap-2 border border-dashed transition-colors hover:bg-black/[0.02]"
                style={{ borderColor: 'var(--color-border)', color: 'var(--color-text-muted)' }}>
                <Receipt size={15} /> ¿Lleva una venta ya entregada (flete o «puesto en obra»)? <span className="font-semibold" style={{ color: 'var(--color-primary)' }}>Agregarla</span>
            </button>
        );
    }

    const visibles = (lista ?? []).filter(v => !enUso.has(v.id));

    return (
        <div className="rounded-2xl" style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
            <div className="p-4 pb-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="text-sm font-bold flex items-center gap-1.5" style={{ color: 'var(--color-text)' }}><Receipt size={16} /> Ventas ya entregadas</h2>
                    <p className="text-[12px]" style={{ color: 'var(--color-text-muted)' }}>Salieron del stock al venderse: el viaje solo las lleva. Últimos 15 días.</p>
                </div>
                <div className="relative w-full sm:w-56">
                    <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: 'var(--color-text-muted)' }} />
                    <input type="search" value={q} onChange={e => setQ(e.target.value)} placeholder="N.º de venta o cliente" aria-label="Buscar venta"
                        className="w-full h-10 text-sm rounded-xl pl-8 pr-3 border outline-none focus:ring-2"
                        style={{ borderColor: 'var(--color-border)', backgroundColor: 'var(--color-bg)', color: 'var(--color-text)' }} />
                </div>
            </div>
            <ul className="max-h-[300px] overflow-y-auto px-2 pb-2" aria-busy={cargando}>
                {cargando && <li className="h-14 mx-2 my-1.5 rounded-xl animate-pulse" style={{ backgroundColor: 'var(--color-bg)' }} />}
                {!cargando && visibles.length === 0 && (
                    <li className="px-3 py-5 text-center text-sm" style={{ color: 'var(--color-text-muted)' }}>{q ? 'Ninguna venta coincide.' : 'No hay ventas recientes para agregar.'}</li>
                )}
                {!cargando && visibles.map(v => (
                    <li key={v.id}>
                        <button type="button" onClick={() => onAgregar(v)}
                            className="w-full text-left rounded-xl px-3 py-2 flex items-center gap-3 transition-colors duration-150 hover:bg-[color-mix(in_srgb,var(--vp-navy)_5%,transparent)]">
                            <div className="flex-1 min-w-0">
                                <p className="text-sm font-semibold truncate" style={{ color: 'var(--color-text)' }}>{v.cliente} <span className="font-normal" style={{ color: 'var(--color-primary)' }}>{v.numero}</span></p>
                                <p className="text-[13px] truncate" style={{ color: 'var(--color-text-muted)' }}>{v.items.map(i => `${fmtCantidad(i.cantidad)} ${i.producto_nombre}`).join(' · ')}</p>
                            </div>
                            <span className="inline-flex items-center gap-1 text-[13px] font-semibold flex-shrink-0" style={{ color: 'var(--vp-navy)' }}><Plus size={13} /> Agregar</span>
                        </button>
                    </li>
                ))}
            </ul>
        </div>
    );
}

interface LineaCarga { producto_id: number; unidades: number; peso_kg: number | null }

/**
 * Misma regla que ViajeService::usoDeCarga: cada producto ocupa la fracción de
 * su límite (el menor entre el de peso y el de volumen); el uso es la suma.
 */
function usoDeCarga(lineas: LineaCarga[], capacidadKg: number, maximos: Record<number, number>): Carga {
    let peso = 0; let fraccion = 0; let sinPeso = false; let sinLimite = false; let porVolumen = false;
    for (const l of lineas) {
        if (l.unidades <= 0) continue;
        if (l.peso_kg == null) sinPeso = true; else peso += l.peso_kg;
        const pesoUnidad = l.peso_kg != null ? l.peso_kg / l.unidades : null;
        const porPeso = capacidadKg > 0 && pesoUnidad && pesoUnidad > 0 ? capacidadKg / pesoUnidad : null;
        const porVol = (maximos[l.producto_id] ?? 0) > 0 ? maximos[l.producto_id] : null;
        if (porPeso == null && porVol == null) { sinLimite = true; continue; }
        const limite = Math.min(porPeso ?? Infinity, porVol ?? Infinity);
        if (porVol != null && porVol <= (porPeso ?? Infinity)) porVolumen = true;
        fraccion += l.unidades / limite;
    }
    const hayLimite = capacidadKg > 0 || Object.keys(maximos).length > 0;
    return {
        peso_kg: Math.round(peso * 1000) / 1000,
        capacidad_kg: capacidadKg > 0 ? capacidadKg : null,
        porcentaje: hayLimite && !sinLimite && lineas.length ? Math.round(fraccion * 1000) / 10 : (capacidadKg > 0 ? Math.round(peso / capacidadKg * 1000) / 10 : null),
        sin_peso: sinPeso,
        por_volumen: porVolumen,
    };
}
