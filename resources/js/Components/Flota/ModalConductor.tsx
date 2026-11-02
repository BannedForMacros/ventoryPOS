import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Switch from '@/Components/UI/Switch';
import Modal from '@/Components/UI/Modal';

/**
 * Alta / edición de un chofer o ayudante. La usan Flota → Conductores y el
 * formulario del viaje («+ Nuevo chofer», «+ Ayudante»), sin salir de él.
 */

export type TipoConductor = 'chofer' | 'ayudante';

export interface ConductorEditable {
    id: number; tipo: TipoConductor; nombres: string; apellidos: string | null; nombre_completo: string;
    tipo_documento: string; numero_documento: string | null; telefono: string | null;
    licencia: string | null; licencia_categoria: string | null; licencia_vence: string | null;
    observacion: string | null; activo: boolean;
}

interface Form {
    tipo: TipoConductor; nombres: string; apellidos: string; tipo_documento: string; numero_documento: string;
    telefono: string; licencia: string; licencia_categoria: string; licencia_vence: string;
    observacion: string; activo: boolean;
}

const vacio = (tipo: TipoConductor): Form => ({
    tipo, nombres: '', apellidos: '', tipo_documento: 'DNI', numero_documento: '', telefono: '',
    licencia: '', licencia_categoria: '', licencia_vence: '', observacion: '', activo: true,
});

const deConductor = (c: ConductorEditable): Form => ({
    tipo: c.tipo, nombres: c.nombres, apellidos: c.apellidos ?? '', tipo_documento: c.tipo_documento,
    numero_documento: c.numero_documento ?? '', telefono: c.telefono ?? '', licencia: c.licencia ?? '',
    licencia_categoria: c.licencia_categoria ?? '', licencia_vence: c.licencia_vence ?? '',
    observacion: c.observacion ?? '', activo: c.activo,
});

/** Categorías de licencia en Perú para transporte de carga. */
const CATEGORIAS = ['A-I', 'A-IIa', 'A-IIb', 'A-IIIa', 'A-IIIb', 'A-IIIc'];

interface Props {
    isOpen: boolean;
    onClose: () => void;
    editando?: ConductorEditable | null;
    /** Función con la que se abre un alta nueva. */
    tipoInicial?: TipoConductor;
    rapido?: boolean;
    only?: string[];
    onGuardado?: (page: { props: Record<string, unknown> }) => void;
}

export default function ModalConductor({ isOpen, onClose, editando = null, tipoInicial = 'chofer', rapido = false, only, onGuardado }: Props) {
    const [form, setForm] = useState<Form>(vacio(tipoInicial));
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [guardando, setGuardando] = useState(false);

    useEffect(() => {
        if (!isOpen) return;
        setForm(editando ? deConductor(editando) : vacio(tipoInicial));
        setErrores({});
    }, [isOpen, editando, tipoInicial]);

    const set = <K extends keyof Form>(k: K, v: Form[K]) => setForm(f => ({ ...f, [k]: v }));
    const esChofer = form.tipo === 'chofer';

    function guardar() {
        setGuardando(true);
        const opts = {
            preserveScroll: true,
            preserveState: true,
            ...(only ? { only } : {}),
            onSuccess: (page: any) => { onClose(); onGuardado?.(page); },
            onError: (e: Record<string, string>) => setErrores(e),
            onFinish: () => setGuardando(false),
        };
        const payload = { ...form, licencia_vence: form.licencia_vence || null };
        editando
            ? router.put(route('flota.conductores.update', editando.id), payload, opts)
            : router.post(route('flota.conductores.store'), payload, opts);
    }

    const titulo = editando ? `Editar a ${editando.nombre_completo}`
        : rapido ? (esChofer ? 'Nuevo chofer' : 'Nuevo ayudante') : 'Nuevo conductor';

    return (
        <Modal isOpen={isOpen} onClose={onClose} size="xl" title={titulo}
            footer={<>
                <Button variant="ghost" onClick={onClose} disabled={guardando}>Cancelar</Button>
                <Button onClick={guardar} loading={guardando}>{rapido && !editando ? 'Guardar y sumar al viaje' : 'Guardar'}</Button>
            </>}>
            <form className="space-y-4" onSubmit={e => { e.preventDefault(); guardar(); }}>
                <div role="radiogroup" aria-label="Función" className="grid grid-cols-2 gap-2">
                    {(['chofer', 'ayudante'] as TipoConductor[]).map(t => {
                        const activo = form.tipo === t;
                        return (
                            <button key={t} type="button" role="radio" aria-checked={activo} onClick={() => set('tipo', t)}
                                className="rounded-xl px-3 py-2.5 text-left border transition-colors duration-150"
                                style={{
                                    borderColor: activo ? 'var(--vp-navy)' : 'var(--color-border)',
                                    backgroundColor: activo ? 'color-mix(in srgb, var(--vp-navy) 7%, var(--color-surface))' : 'var(--color-surface)',
                                }}>
                                <span className="block text-sm font-semibold" style={{ color: 'var(--color-text)' }}>{t === 'chofer' ? 'Chofer' : 'Ayudante'}</span>
                                <span className="block text-[13px]" style={{ color: 'var(--color-text-muted)' }}>
                                    {t === 'chofer' ? 'Maneja el camión; lleva licencia' : 'Va en el viaje para cargar y descargar'}
                                </span>
                            </button>
                        );
                    })}
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <Input label="Nombres" required value={form.nombres} onChange={e => set('nombres', e.target.value)} error={errores.nombres} autoFocus />
                    <Input label="Apellidos" value={form.apellidos} onChange={e => set('apellidos', e.target.value)} error={errores.apellidos} />
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <Select label="Documento" value={form.tipo_documento} onChange={v => set('tipo_documento', String(v))}
                        options={[{ value: 'DNI', label: 'DNI' }, { value: 'CE', label: 'Carné de extranjería' }, { value: 'PASAPORTE', label: 'Pasaporte' }]} />
                    <Input label="Número" required={esChofer} inputMode={form.tipo_documento === 'DNI' ? 'numeric' : undefined}
                        value={form.numero_documento} error={errores.numero_documento}
                        onChange={e => set('numero_documento', form.tipo_documento === 'DNI' ? e.target.value.replace(/\D/g, '').slice(0, 8) : e.target.value)} />
                    <Input label="Teléfono" type="tel" inputMode="tel" value={form.telefono} onChange={e => set('telefono', e.target.value)} error={errores.telefono} />
                </div>

                {esChofer && (
                    <div className="rounded-xl p-3 space-y-3" style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)' }}>
                        <p className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>
                            Licencia de conducir {rapido && <span className="font-normal" style={{ color: 'var(--color-text-muted)' }}>· sale sola en la guía</span>}
                        </p>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <Input label="N.º de licencia" required value={form.licencia} onChange={e => set('licencia', e.target.value.toUpperCase())} error={errores.licencia} />
                            <Select label="Categoría" value={form.licencia_categoria} placeholder="Elegir…"
                                onChange={v => set('licencia_categoria', String(v))}
                                options={[{ value: '', label: 'Sin indicar' }, ...CATEGORIAS.map(c => ({ value: c, label: c }))]} />
                            <Input label="Vence" type="date" value={form.licencia_vence} onChange={e => set('licencia_vence', e.target.value)} error={errores.licencia_vence}
                                hint="Avisa 30 días antes" />
                        </div>
                    </div>
                )}

                {!rapido && <Input label="Observación" value={form.observacion} onChange={e => set('observacion', e.target.value)} error={errores.observacion} />}
                {errores.tipo && <p className="text-sm" style={{ color: 'var(--color-danger)' }}>{errores.tipo}</p>}
                {editando && <Switch label="Activo" checked={form.activo} onChange={v => set('activo', v)} />}
                <button type="submit" className="hidden" aria-hidden tabIndex={-1} />
            </form>
        </Modal>
    );
}
