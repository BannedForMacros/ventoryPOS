import React, { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { FileText, Link2, Unlink, Activity, ShieldCheck, TriangleAlert } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Badge from '@/Components/UI/Badge';
import Callout from '@/Components/UI/Callout';
import Input from '@/Components/UI/Input';
import Modal from '@/Components/UI/Modal';
import Switch from '@/Components/UI/Switch';
import type { PageProps } from '@/types';

/**
 * Configuración → Facturación Electrónica.
 *
 * ─── El fallo que esta pantalla cierra ───────────────────────────────────────
 *
 * La conexión con el emisor vivía en el `.env`: UN token para toda la
 * instalación. Como un token de FacturaMac identifica a UNA empresa emisora,
 * con dos contribuyentes en el mismo ventoryPOS las ventas del segundo salían
 * firmadas con el RUC del primero. Ahora cada empresa tiene su conexión, y se
 * hace desde aquí: sin SSH, sin editar archivos, sin tocar la base.
 *
 * ─── El flujo, y tiene que ser rapidísimo ────────────────────────────────────
 *
 * Un campo para el código y un botón «Conectar». El usuario no teclea URLs ni
 * tokens: pega el código corto que le dio FacturaMac y ya está. Todo lo demás
 * —RUC del emisor, razón social, modo— lo trae la respuesta.
 *
 * Las dos guardas se aplican EN EL SERVIDOR (VincularEmisor); aquí solo se
 * pintan. Deshabilitar el interruptor en el navegador es cortesía para que se
 * vea por qué no se puede, nunca la barrera.
 */

type Modo = 'produccion' | 'beta' | 'simulacion' | 'desactivado' | 'desconocido' | string;

interface Conexion {
    ruc_emisor: string;
    razon_social_emisor: string | null;
    modo: Modo;
    emision_activa: boolean;
    instalacion: string | null;
    conectado_at: string | null;
    diagnostico_at: string | null;
    diagnostico_ok: boolean | null;
    diagnostico_mensaje: string | null;
    puede_activar: boolean;
    en_pruebas: boolean;
    emite: boolean;
}

interface Props extends PageProps {
    empresa:   { id: number; razon_social: string; ruc: string };
    instalado: boolean;
    emisorUrl: string;
    conexion:  Conexion | null;
}

/** Cómo se pinta cada modo. El color es la información: rojo = SUNAT de verdad. */
const MODOS: Record<string, { etiqueta: string; variant: 'success' | 'warning' | 'danger' | 'secondary' | 'primary' }> = {
    produccion:  { etiqueta: 'PRODUCCIÓN',  variant: 'danger'    },
    beta:        { etiqueta: 'BETA',        variant: 'warning'   },
    simulacion:  { etiqueta: 'SIMULACIÓN',  variant: 'warning'   },
    desactivado: { etiqueta: 'DESACTIVADO', variant: 'secondary' },
    desconocido: { etiqueta: 'DESCONOCIDO', variant: 'secondary' },
};

function Dato({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="flex flex-col gap-0.5 min-w-0">
            <span className="text-xs uppercase tracking-wide" style={{ color: 'var(--color-text-muted)' }}>
                {label}
            </span>
            <span className="text-sm font-medium truncate" style={{ color: 'var(--color-text)' }}>
                {children}
            </span>
        </div>
    );
}

function fecha(valor: string | null): string {
    if (!valor) return '—';
    const d = new Date(valor);
    return isNaN(d.getTime()) ? '—' : d.toLocaleString('es-PE');
}

export default function FacturacionElectronica({ empresa, instalado, emisorUrl, conexion }: Props) {
    const { flash, errors } = usePage<Props>().props as any;

    const [codigo, setCodigo]           = useState('');
    const [conectando, setConectando]   = useState(false);
    const [probando, setProbando]       = useState(false);
    const [confirmDesc, setConfirmDesc] = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success as string);
        if (flash?.error)   toast.error(flash.error as string);
    }, [flash]);

    const modo = MODOS[conexion?.modo ?? 'desactivado'] ?? MODOS.desconocido;

    function conectar() {
        if (!codigo.trim()) return;
        setConectando(true);
        router.post(route('configuracion.facturacion.conectar'), { codigo: codigo.trim() }, {
            preserveScroll: true,
            onSuccess: () => setCodigo(''),
            onFinish:  () => setConectando(false),
        });
    }

    function probar() {
        setProbando(true);
        router.post(route('configuracion.facturacion.probar'), {}, {
            preserveScroll: true,
            onFinish: () => setProbando(false),
        });
    }

    function cambiarEmision(activa: boolean) {
        router.put(route('configuracion.facturacion.emision'), { emision_activa: activa }, {
            preserveScroll: true,
        });
    }

    function desconectar() {
        setConfirmDesc(false);
        router.delete(route('configuracion.facturacion.desconectar'), { preserveScroll: true });
    }

    return (
        <AppLayout title="Facturación Electrónica">
            <PageHeader
                icon={<FileText size={20} />}
                title="Facturación Electrónica"
                subtitle={`Conexión de «${empresa.razon_social}» (RUC ${empresa.ruc}) con su emisor.`}
                actions={conexion && (
                    <div className="flex items-center gap-2">
                        <Button variant="ghost" onClick={probar} loading={probando}
                                startContent={<Activity size={15} />}>
                            Probar conexión
                        </Button>
                        <Button variant="danger" flat onClick={() => setConfirmDesc(true)}
                                startContent={<Unlink size={15} />}>
                            Desconectar
                        </Button>
                    </div>
                )}
            />

            <div className="flex flex-col gap-4 max-w-3xl">

                {/* El SQL de producción no se ha ejecutado. Se dice antes de que el
                    usuario descubra el problema al pulsar «Conectar». */}
                {!instalado && (
                    <Callout variant="danger" title="El módulo no está instalado en este servidor">
                        Falta ejecutar <code>produccion/sql/2026_07_facturacion_conexion_empresa.sql</code>.
                        Mientras tanto la facturación electrónica se comporta como apagada: el punto de
                        venta funciona con normalidad y no se emite ningún comprobante.
                    </Callout>
                )}

                {/* ── Estado actual ─────────────────────────────────────────── */}
                <div className="rounded-2xl p-5"
                     style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>

                    <div className="flex items-start justify-between gap-3 mb-4">
                        <div className="flex items-center gap-2.5 min-w-0">
                            <div className="flex items-center justify-center h-9 w-9 rounded-xl flex-shrink-0"
                                 style={{ backgroundColor: 'color-mix(in srgb, var(--color-primary) 12%, transparent)' }}>
                                {conexion ? <ShieldCheck size={18} style={{ color: 'var(--color-primary)' }} />
                                          : <Link2 size={18} style={{ color: 'var(--color-text-muted)' }} />}
                            </div>
                            <div className="min-w-0">
                                <p className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>
                                    {conexion ? 'Conectada con FacturaMac' : 'Sin conectar'}
                                </p>
                                <p className="text-xs truncate" style={{ color: 'var(--color-text-muted)' }}>
                                    {emisorUrl}
                                </p>
                            </div>
                        </div>

                        {conexion && (
                            <div className="flex items-center gap-2 flex-shrink-0">
                                <Badge variant={modo.variant}>{modo.etiqueta}</Badge>
                                <Badge variant={conexion.emite ? 'success' : 'secondary'}>
                                    {conexion.emite ? 'Emitiendo' : 'Sin emitir'}
                                </Badge>
                            </div>
                        )}
                    </div>

                    {conexion ? (
                        <>
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                                <Dato label="Emisor">{conexion.razon_social_emisor ?? '—'}</Dato>
                                <Dato label="RUC del emisor">{conexion.ruc_emisor}</Dato>
                                <Dato label="Conectada el">{fecha(conexion.conectado_at)}</Dato>
                                <Dato label="Última prueba">{fecha(conexion.diagnostico_at)}</Dato>
                            </div>

                            {conexion.diagnostico_mensaje && (
                                <Callout variant={conexion.diagnostico_ok ? 'success' : 'danger'} className="mb-4">
                                    {conexion.diagnostico_mensaje}
                                </Callout>
                            )}

                            {/* ── El interruptor ───────────────────────────────
                                Bloqueado en beta Y en simulación: una boleta "de
                                prueba" que consume correlativo real deja un hueco
                                en la numeración que hay que justificar ante SUNAT.
                                Conectar y diagnosticar sí se permite — hay que
                                poder ver que la tubería funciona antes de pasar la
                                empresa a producción. */}
                            <div className="rounded-xl p-4"
                                 style={{ backgroundColor: 'var(--color-bg)', border: '1px solid var(--color-border)' }}>
                                <Switch
                                    label="Emitir comprobantes electrónicos"
                                    description={
                                        conexion.puede_activar
                                            ? 'Con esto encendido, las boletas y facturas de esta empresa se informan a SUNAT.'
                                            : 'Esta empresa está en modo de pruebas en FacturaMac (BETA/SIMULACIÓN). '
                                              + 'Comuníquese con soporte para activarla.'
                                    }
                                    checked={conexion.emision_activa}
                                    disabled={!conexion.puede_activar && !conexion.emision_activa}
                                    onChange={cambiarEmision}
                                />

                                {errors?.emision_activa && (
                                    <p className="mt-2 text-sm" style={{ color: 'var(--color-danger)' }}>
                                        {errors.emision_activa}
                                    </p>
                                )}

                                {conexion.en_pruebas && (
                                    <Callout variant="warning" className="mt-3"
                                             title="Modo de pruebas: no se puede activar la emisión">
                                        FacturaMac tiene esta empresa en <b>{modo.etiqueta}</b>. La conexión
                                        queda guardada y puedes probarla, pero no se emitirá nada ante SUNAT
                                        hasta que soporte pase la empresa a producción. Después, pulsa
                                        «Probar conexión» para que ventoryPOS se entere del cambio.
                                    </Callout>
                                )}

                                {conexion.emision_activa && !conexion.emite && (
                                    <Callout variant="danger" className="mt-3"
                                             title="El interruptor está encendido pero NO se está emitiendo">
                                        El emisor tiene esta empresa en <b>{modo.etiqueta}</b>. Pulsa
                                        «Probar conexión» para actualizar el estado.
                                    </Callout>
                                )}
                            </div>
                        </>
                    ) : (
                        <p className="text-sm" style={{ color: 'var(--color-text-muted)' }}>
                            Esta empresa todavía no tiene emisor. Las ventas se registran con normalidad,
                            pero no se informa nada a SUNAT.
                        </p>
                    )}
                </div>

                {/* ── Conectar ──────────────────────────────────────────────── */}
                <div className="rounded-2xl p-5"
                     style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)' }}>
                    <p className="text-sm font-semibold mb-1" style={{ color: 'var(--color-text)' }}>
                        {conexion ? 'Volver a conectar' : 'Conectar con FacturaMac'}
                    </p>
                    <p className="text-[13px] mb-4" style={{ color: 'var(--color-text-muted)' }}>
                        Pide en FacturaMac un código de vinculación para esta empresa y pégalo aquí.
                        Es de un solo uso y caduca.
                        {conexion && ' Al reconectar, la emisión se apaga hasta que vuelvas a activarla.'}
                    </p>

                    <div className="flex flex-wrap items-start gap-3">
                        <div className="flex-1 min-w-[220px]">
                            <Input
                                label="Código de vinculación"
                                placeholder="7K2P-9X4Q"
                                value={codigo}
                                onChange={e => setCodigo(e.target.value.toUpperCase())}
                                onKeyDown={e => { if (e.key === 'Enter') conectar(); }}
                                disabled={!instalado}
                                error={errors?.codigo}
                                autoFocus={!conexion}
                            />
                        </div>
                        <div className="pt-[26px]">
                            <Button onClick={conectar} loading={conectando}
                                    disabled={!instalado || !codigo.trim()}
                                    startContent={<Link2 size={15} />}>
                                Conectar
                            </Button>
                        </div>
                    </div>

                    {/* La guarda del RUC, explicada ANTES de que falle. Es la que
                        impide emitir a nombre de otro contribuyente. */}
                    <Callout variant="info" className="mt-4">
                        <div className="flex items-start gap-2">
                            <TriangleAlert size={14} className="flex-shrink-0 mt-0.5" />
                            <span>
                                El código tiene que ser de la empresa con <b>RUC {empresa.ruc}</b>. Si
                                pertenece a otro contribuyente, la conexión se rechaza y no se guarda
                                nada: emitir con ese token pondría el RUC equivocado en comprobantes
                                que no se pueden deshacer.
                            </span>
                        </div>
                    </Callout>
                </div>
            </div>

            <Modal
                isOpen={confirmDesc}
                onClose={() => setConfirmDesc(false)}
                title="Desconectar de FacturaMac"
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setConfirmDesc(false)}>Cancelar</Button>
                        <Button variant="danger" onClick={desconectar}>Desconectar</Button>
                    </>
                }
            >
                <p className="text-sm" style={{ color: 'var(--color-text)' }}>
                    Se borrará el token de esta empresa y dejará de emitirse cualquier comprobante.
                    Los comprobantes ya informados a SUNAT no se ven afectados: siguen declarados.
                </p>
            </Modal>
        </AppLayout>
    );
}
