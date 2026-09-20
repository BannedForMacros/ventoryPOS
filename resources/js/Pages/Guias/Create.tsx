import { useMemo, useState } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { Truck, Plus, Trash2, Building2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Callout from '@/Components/UI/Callout';
import Checkbox from '@/Components/UI/Checkbox';
import SearchableSelect from '@/Components/UI/SearchableSelect';
import type { PageProps } from '@/types';

/**
 * Nueva guía de remisión.
 *
 * ─── ESTA PANTALLA NO SABE NI UNA REGLA DE SUNAT ───────────────────────────────
 *
 * Qué campos pide cada motivo llega de FacturaMac en `catalogos`, y FacturaMac lo
 * saca del contrato compartido. Aquí solo se enseña u oculta.
 *
 * Es lo que hace que este formulario y el del portal coincidan sin que nadie los
 * coordine. Escribir las reglas aquí sería volver al problema que costó dos bugs
 * fiscales: dos sistemas con su propia idea de lo que SUNAT pide.
 *
 * ─── LO QUE NO SE ENSEÑA IMPORTA TANTO COMO LO QUE SÍ ──────────────────────────
 *
 * Un traslado entre almacenes propios NO pide cliente, y una venta NO pide el código
 * del local de llegada. Mandar esos campos donde no van es rechazo de SUNAT con la
 * guía ya numerada.
 */

interface Motivo {
    valor: string;
    etiqueta: string;
    exige_descripcion: boolean;
    destinatario_ayuda: string;
    exige_destinatario: boolean;
    prohibe_destinatario: boolean;
    exige_comprador: boolean;
    exige_codigo_establecimiento: boolean;
    admite_establecimiento_partida: boolean;
    admite_establecimiento_llegada: boolean;
}

interface Props extends PageProps {
    catalogos: {
        disponible: boolean;
        motivo?: string;
        motivos?: Motivo[];
        modalidades?: { valor: string; etiqueta: string; pregunta: string }[];
        empresa?: { ruc: string; razon_social: string; ubigeo: string | null; direccion: string | null };
    };
    emision: { disponible: boolean; motivo: string | null; ensayo: string | null };
    venta: {
        id: number; numero: string; cliente_id: number | null; cliente: string | null;
        comprobante: string | null;
        items: { descripcion: string; cantidad: number; unidad: string; codigo: string | null; peso: number | null }[];
    } | null;
    clientes?: { id: number; label: string }[];
}

type Linea = { descripcion: string; cantidad: string; unidad: string; codigo: string; peso: string };

const hoy = () => new Date().toISOString().slice(0, 10);

const UNIDADES = [
    { value: 'NIU', label: 'Unidad' },
    { value: 'BG', label: 'Bolsa / saco' },
    { value: 'BX', label: 'Caja' },
    { value: 'KGM', label: 'Kilogramo' },
    { value: 'MTQ', label: 'Metro cúbico' },
    { value: 'PK', label: 'Paquete' },
];

export default function GuiaCreate() {
    const { catalogos, emision, venta, clientes = [] } = usePage<Props>().props as any;

    const motivos: Motivo[] = catalogos.motivos ?? [];
    const empresa = catalogos.empresa ?? {};

    const { data, setData, post, processing, errors } = useForm<any>({
        motivo: motivos[0]?.valor ?? 'VENTA',
        modalidad: 'PRIVADO',
        fecha_inicio: hoy(),
        peso_total: '',
        unidad_peso: 'KGM',
        numero_bultos: '',
        descripcion_motivo: '',
        // La partida es casi siempre la dirección fiscal: viene rellenada para que
        // el caso corriente sea un formulario ya hecho.
        partida: { ubigeo: empresa.ubigeo ?? '', direccion: empresa.direccion ?? '', codigo_establecimiento: '' },
        llegada: { ubigeo: '', direccion: '', codigo_establecimiento: '' },
        vehiculo: { placa: '', tarjeta_circulacion: '' },
        vehiculo_menor: false,
        conductor: { tipo_documento: 'DNI', numero_documento: '', nombres: '', apellidos: '', licencia: '' },
        transportista: { ruc: '', razon_social: '', registro_mtc: '' },
        retorno_vehiculo_vacio: false,
        retorno_envases_vacios: false,
        transbordo_programado: false,
        cliente_id: venta?.cliente_id ?? '',
        comprador_id: '',
        venta_id: venta?.id ?? '',
        items: (venta?.items?.length
            ? venta.items.map((i: any) => ({
                descripcion: i.descripcion, cantidad: String(i.cantidad),
                unidad: i.unidad ?? 'NIU', codigo: i.codigo ?? '', peso: i.peso ? String(i.peso) : '',
            }))
            : [{ descripcion: '', cantidad: '', unidad: 'NIU', codigo: '', peso: '' }]) as Linea[],
        observaciones: '',
    });

    const motivo = useMemo(
        () => motivos.find((m) => m.valor === data.motivo) ?? motivos[0],
        [motivos, data.motivo],
    );

    const esPublico = data.modalidad === 'PUBLICO';
    const declaraVehiculo = !esPublico && !data.vehiculo_menor;

    const set = (ruta: string, valor: any) => {
        const partes = ruta.split('.');
        if (partes.length === 1) return setData(ruta, valor);
        const copia = structuredClone(data);
        let nodo: any = copia;
        for (const p of partes.slice(0, -1)) nodo = nodo[p];
        nodo[partes[partes.length - 1]] = valor;
        setData(copia);
    };

    const setLinea = (i: number, campo: keyof Linea, valor: string) => {
        const items = [...data.items];
        items[i] = { ...items[i], [campo]: valor };
        setData('items', items);
    };

    /** El peso que suman las líneas, para proponerlo sin que nadie lo sume a mano. */
    const pesoSugerido = useMemo(() => {
        const todas = data.items.every((l: Linea) => l.peso !== '');
        if (!todas || data.items.length === 0) return null;
        return data.items.reduce((s: number, l: Linea) => s + Number(l.peso) * Number(l.cantidad || 0), 0);
    }, [data.items]);

    if (!catalogos.disponible) {
        return (
            <AppLayout title="Nueva guía">
                <PageHeader title="Nueva guía de remisión" icon={<Truck size={20} />} backHref={route('guias.index')} />
                <Callout variant="warning" title="No se puede emitir">
                    {catalogos.motivo ?? 'FacturaMac no respondió.'}
                </Callout>
            </AppLayout>
        );
    }

    return (
        <AppLayout title="Nueva guía">
            <PageHeader
                title="Nueva guía de remisión"
                subtitle={venta ? `Del despacho de la venta ${venta.numero}` : undefined}
                icon={<Truck size={20} />}
                backHref={route('guias.index')}
            />

            <form onSubmit={(e) => { e.preventDefault(); post(route('guias.store')); }} className="space-y-4 pb-10">
                {emision.ensayo && <Callout variant="info">{emision.ensayo}</Callout>}

                {Object.keys(errors).length > 0 && (
                    <Callout variant="danger" title="Revisa estos datos">
                        <ul className="list-inside list-disc text-sm">
                            {Object.entries(errors).map(([c, m]) => <li key={c}>{m as string}</li>)}
                        </ul>
                    </Callout>
                )}

                {/* ─── Motivo ─── */}
                <section className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
                    <h2 className="mb-3 text-sm font-semibold">¿Por qué se mueve la mercadería?</h2>
                    <div className="grid gap-3 md:grid-cols-2">
                        <Select
                            label="Motivo del traslado"
                            value={data.motivo}
                            onChange={(e: any) => set('motivo', e.target.value)}
                            options={motivos.map((m) => ({ value: m.valor, label: m.etiqueta }))}
                        />
                        <Input
                            label="Fecha de inicio del traslado"
                            type="date"
                            value={data.fecha_inicio}
                            onChange={(e: any) => set('fecha_inicio', e.target.value)}
                        />
                    </div>
                    {motivo?.exige_descripcion && (
                        <div className="mt-3">
                            <Input
                                label="Explica de qué traslado se trata"
                                value={data.descripcion_motivo}
                                onChange={(e: any) => set('descripcion_motivo', e.target.value)}
                            />
                        </div>
                    )}
                    <p className="mt-2 text-xs text-[var(--color-text-muted)]">{motivo?.destinatario_ayuda}</p>
                </section>

                {/* ─── Quién recibe ─── */}
                {motivo && !motivo.prohibe_destinatario && (
                    <section className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
                        <h2 className="mb-3 text-sm font-semibold">¿Quién la recibe?</h2>
                        <SearchableSelect
                            label="Cliente"
                            value={data.cliente_id}
                            onChange={(v: any) => set('cliente_id', v)}
                            options={clientes}
                            placeholder="Busca por nombre o documento…"
                        />
                    </section>
                )}

                {motivo?.prohibe_destinatario && (
                    <Callout variant="neutral">
                        <span className="flex items-center gap-2">
                            <Building2 size={16} />
                            La mercadería viene hacia tu propia empresa: no hace falta indicar destinatario.
                        </span>
                    </Callout>
                )}

                {motivo?.exige_comprador && (
                    <section className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
                        <h2 className="mb-1 text-sm font-semibold">¿A quién se le factura?</h2>
                        <p className="mb-3 text-xs text-[var(--color-text-muted)]">Se entrega a uno y se factura a otro.</p>
                        <SearchableSelect
                            label="Comprador"
                            value={data.comprador_id}
                            onChange={(v: any) => set('comprador_id', v)}
                            options={clientes}
                        />
                    </section>
                )}

                {/* ─── Ruta ─── */}
                <section className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
                    <h2 className="mb-3 text-sm font-semibold">¿De dónde sale y a dónde va?</h2>
                    <div className="grid gap-4 md:grid-cols-2">
                        {(['partida', 'llegada'] as const).map((extremo) => {
                            const admite = extremo === 'partida'
                                ? motivo?.admite_establecimiento_partida
                                : motivo?.admite_establecimiento_llegada;

                            return (
                                <div key={extremo} className="space-y-3 rounded-lg border border-[var(--color-border)] p-3">
                                    <p className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)]">
                                        {extremo === 'partida' ? 'Punto de partida' : 'Punto de llegada'}
                                    </p>
                                    <Input
                                        label="Dirección"
                                        value={data[extremo].direccion}
                                        onChange={(e: any) => set(`${extremo}.direccion`, e.target.value)}
                                    />
                                    <Input
                                        label="Ubigeo"
                                        value={data[extremo].ubigeo}
                                        onChange={(e: any) => set(`${extremo}.ubigeo`, e.target.value.replace(/\D/g, '').slice(0, 6))}
                                        hint="Seis dígitos del distrito"
                                    />
                                    {admite && (
                                        <Input
                                            label="Código de local"
                                            value={data[extremo].codigo_establecimiento}
                                            onChange={(e: any) => set(`${extremo}.codigo_establecimiento`, e.target.value)}
                                            hint={motivo?.exige_codigo_establecimiento
                                                ? 'Obligatorio: el declarado en tu RUC'
                                                : 'Opcional, solo si es un local tuyo'}
                                        />
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </section>

                {/* ─── Transporte ─── */}
                <section className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
                    <h2 className="mb-3 text-sm font-semibold">¿Quién la lleva?</h2>

                    <div className="mb-4 grid gap-2 sm:grid-cols-2">
                        {(catalogos.modalidades ?? []).map((m: any) => (
                            <label
                                key={m.valor}
                                className={`flex cursor-pointer items-center gap-3 rounded-lg border px-3 py-2.5 text-sm ${
                                    data.modalidad === m.valor
                                        ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/5'
                                        : 'border-[var(--color-border)]'
                                }`}
                            >
                                <input
                                    type="radio"
                                    name="modalidad"
                                    value={m.valor}
                                    checked={data.modalidad === m.valor}
                                    onChange={(e) => set('modalidad', e.target.value)}
                                />
                                <Truck size={16} />
                                {m.pregunta}
                            </label>
                        ))}
                    </div>

                    {esPublico ? (
                        <div className="grid gap-3 md:grid-cols-3">
                            <Input label="RUC del transportista" value={data.transportista.ruc}
                                onChange={(e: any) => set('transportista.ruc', e.target.value.replace(/\D/g, '').slice(0, 11))} />
                            <Input label="Razón social" value={data.transportista.razon_social}
                                onChange={(e: any) => set('transportista.razon_social', e.target.value)} />
                            <Input label="Registro MTC" value={data.transportista.registro_mtc}
                                onChange={(e: any) => set('transportista.registro_mtc', e.target.value)} hint="Opcional" />
                        </div>
                    ) : (
                        <>
                            <Checkbox
                                label="Va en moto o auto particular (SUNAT no pide placa ni conductor)"
                                checked={data.vehiculo_menor}
                                onChange={(e: any) => set('vehiculo_menor', e.target.checked)}
                            />

                            {declaraVehiculo && (
                                <div className="mt-3 space-y-3">
                                    <div className="grid gap-3 md:grid-cols-2">
                                        <Input label="Placa" value={data.vehiculo.placa}
                                            onChange={(e: any) => set('vehiculo.placa', e.target.value)}
                                            hint="Como la lees en la tarjeta: ABC-123 vale" />
                                        <Input label="Tarjeta de circulación" value={data.vehiculo.tarjeta_circulacion}
                                            onChange={(e: any) => set('vehiculo.tarjeta_circulacion', e.target.value)} hint="Opcional" />
                                    </div>
                                    <p className="pt-1 text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)]">Conductor</p>
                                    <div className="grid gap-3 md:grid-cols-4">
                                        <Input label="DNI" value={data.conductor.numero_documento}
                                            onChange={(e: any) => set('conductor.numero_documento', e.target.value.replace(/\D/g, '').slice(0, 8))} />
                                        <Input label="Nombres" value={data.conductor.nombres}
                                            onChange={(e: any) => set('conductor.nombres', e.target.value)} />
                                        <Input label="Apellidos" value={data.conductor.apellidos}
                                            onChange={(e: any) => set('conductor.apellidos', e.target.value)} />
                                        <Input label="Licencia" value={data.conductor.licencia}
                                            onChange={(e: any) => set('conductor.licencia', e.target.value)} />
                                    </div>
                                </div>
                            )}
                        </>
                    )}

                    {/* Excepciones del viaje: lo normal es no marcar ninguna. */}
                    <div className="mt-4 space-y-2 border-t border-[var(--color-border)] pt-3">
                        <p className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)]">
                            ¿Algo más que declarar del viaje?
                        </p>
                        {([
                            ['retorno_vehiculo_vacio', 'El vehículo regresa vacío después de descargar'],
                            ['retorno_envases_vacios', 'Regresa con envases o embalajes vacíos'],
                            ['transbordo_programado', 'Hay un cambio de vehículo previsto en el camino'],
                        ] as const).map(([campo, etiqueta]) => (
                            <Checkbox
                                key={campo}
                                label={etiqueta}
                                checked={data[campo]}
                                onChange={(e: any) => set(campo, e.target.checked)}
                            />
                        ))}
                    </div>
                </section>

                {/* ─── Carga ─── */}
                <section className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
                    <h2 className="mb-3 text-sm font-semibold">¿Qué se mueve?</h2>

                    <div className="mb-4 grid gap-3 md:grid-cols-3">
                        <Input
                            label="Peso bruto total"
                            type="number"
                            step="0.001"
                            value={data.peso_total}
                            onChange={(e: any) => set('peso_total', e.target.value)}
                            hint={pesoSugerido ? `Las líneas suman ${pesoSugerido.toFixed(3)}` : undefined}
                        />
                        <Select
                            label="Unidad de peso"
                            value={data.unidad_peso}
                            onChange={(e: any) => set('unidad_peso', e.target.value)}
                            options={[{ value: 'KGM', label: 'Kilogramos' }, { value: 'TNE', label: 'Toneladas' }]}
                        />
                        <Input label="Número de bultos" type="number" value={data.numero_bultos}
                            onChange={(e: any) => set('numero_bultos', e.target.value)} hint="Opcional" />
                    </div>

                    <div className="space-y-2">
                        {data.items.map((linea: Linea, i: number) => (
                            <div key={i} className="grid gap-2 rounded-lg border border-[var(--color-border)] p-3 md:grid-cols-12">
                                <div className="md:col-span-4">
                                    <Input label={i === 0 ? 'Descripción' : undefined} value={linea.descripcion}
                                        onChange={(e: any) => setLinea(i, 'descripcion', e.target.value)} />
                                </div>
                                <div className="md:col-span-2">
                                    <Input label={i === 0 ? 'Código' : undefined} value={linea.codigo}
                                        onChange={(e: any) => setLinea(i, 'codigo', e.target.value)} />
                                </div>
                                <div className="md:col-span-2">
                                    <Select label={i === 0 ? 'Unidad' : undefined} value={linea.unidad}
                                        onChange={(e: any) => setLinea(i, 'unidad', e.target.value)} options={UNIDADES} />
                                </div>
                                <div className="md:col-span-2">
                                    <Input label={i === 0 ? 'Cantidad' : undefined} type="number" step="0.001"
                                        value={linea.cantidad} onChange={(e: any) => setLinea(i, 'cantidad', e.target.value)} />
                                </div>
                                <div className="md:col-span-1">
                                    <Input label={i === 0 ? 'Peso' : undefined} type="number" step="0.001"
                                        value={linea.peso} onChange={(e: any) => setLinea(i, 'peso', e.target.value)} />
                                </div>
                                <div className="flex items-end md:col-span-1">
                                    {data.items.length > 1 && (
                                        <Button type="button" variant="ghost" size="sm" iconOnly
                                            onClick={() => setData('items', data.items.filter((_: Linea, j: number) => j !== i))}>
                                            <Trash2 size={15} />
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>

                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        startContent={<Plus size={15} />}
                        onClick={() => setData('items', [...data.items, { descripcion: '', cantidad: '', unidad: 'NIU', codigo: '', peso: '' }])}
                    >
                        Añadir línea
                    </Button>
                </section>

                <section className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
                    <Input label="Observaciones" value={data.observaciones}
                        onChange={(e: any) => set('observaciones', e.target.value)} hint="Opcional. Sale impreso en la guía." />
                </section>

                <div className="flex justify-end gap-2">
                    <Link href={route('guias.index')}><Button type="button" variant="ghost">Cancelar</Button></Link>
                    <Button type="submit" loading={processing} disabled={!emision.disponible}>Emitir guía</Button>
                </div>
            </form>
        </AppLayout>
    );
}
