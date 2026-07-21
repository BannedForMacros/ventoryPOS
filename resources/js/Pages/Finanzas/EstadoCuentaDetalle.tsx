import { useMemo } from 'react';
import { Users, Printer, Scale } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/UI/PageHeader';
import Button from '@/Components/UI/Button';
import Badge from '@/Components/UI/Badge';
import Callout from '@/Components/UI/Callout';
import StatGrid from '@/Components/UI/StatGrid';
import { ReportCard, Th, theadStyle, zebra, Empty, fmtS } from '@/Components/Reportes/ReportUI';

interface Movimiento {
    fecha: string;
    tipo: string;
    etiqueta: string;
    documento: string | null;
    detalle: string;
    monto: number;
    saldo: number;
    variant: 'primary' | 'success' | 'danger' | 'warning';
}

interface Tercero {
    clave: string;
    nombre: string;
    documento: string | null;
    nos_debe: number;
    le_debemos: number;
    su_anticipo: number;
    nuestro_adelanto: number;
    neto: number;
    rol: 'cliente' | 'proveedor' | 'ambos';
    dias_antiguedad: number | null;
    sin_identificar: boolean;
}

interface Props {
    tercero: Tercero;
    movimientos: Movimiento[];
}

const fecha = (d: string) => new Date(`${d}T00:00:00`).toLocaleDateString('es-PE');

export default function EstadoCuentaDetalle({ tercero, movimientos }: Props) {
    // El acumulado del timeline debe aterrizar en el mismo neto de la lista.
    // Si no lo hace, hay documentos descuadrados y hay que decirlo, no taparlo.
    const saldoFinal = movimientos.length ? movimientos[movimientos.length - 1].saldo : 0;
    const descuadre  = useMemo(
        () => Math.round((saldoFinal - tercero.neto) * 100) / 100,
        [saldoFinal, tercero.neto],
    );

    return (
        <AppLayout>
            <PageHeader
                icon={<Users size={22} />}
                title={tercero.nombre}
                subtitle={`${tercero.documento || 'Sin documento'} · ${
                    tercero.rol === 'ambos' ? 'Cliente y proveedor' : tercero.rol === 'cliente' ? 'Cliente' : 'Proveedor'
                }`}
                backHref="/finanzas/estado-cuenta"
                actions={
                    <Button variant="secondary" onClick={() => window.print()}>
                        <Printer size={16} /> Imprimir
                    </Button>
                }
            />

            <div className="p-4 sm:p-6 flex flex-col gap-4">
                <StatGrid
                    size="lg"
                    stats={[
                        { label: 'Nos debe',       valor: fmtS(tercero.nos_debe),         color: 'success' },
                        { label: 'Le debemos',     valor: fmtS(tercero.le_debemos),       color: 'danger'  },
                        { label: 'Su anticipo',    valor: fmtS(tercero.su_anticipo),      color: 'warning' },
                        { label: 'Ntro. adelanto', valor: fmtS(tercero.nuestro_adelanto), color: 'primary' },
                        {
                            label: 'Neto',
                            valor: fmtS(tercero.neto),
                            color: tercero.neto >= 0 ? 'success' : 'danger',
                            icon: <Scale size={18} />,
                            destacado: true,
                            sub: tercero.neto >= 0 ? 'A nuestro favor' : 'En nuestra contra',
                        },
                    ]}
                />

                {descuadre !== 0 && (
                    <Callout variant="warning" title="Los documentos no cuadran con el saldo">
                        Sumando los movimientos uno a uno sale <strong>{fmtS(saldoFinal)}</strong>, pero los saldos
                        guardados en los documentos dan <strong>{fmtS(tercero.neto)}</strong> — una diferencia de{' '}
                        <strong>{fmtS(descuadre)}</strong>. Suele significar que un abono o pago se registró sin
                        actualizar el documento. El número correcto para cobrar es el de los documentos (el Neto de
                        arriba); esta diferencia hay que revisarla.
                    </Callout>
                )}

                <ReportCard icon={<Scale size={16} />} title="Cuenta corriente" badge={`${movimientos.length} movimientos`} sinPadding>
                    {movimientos.length === 0 ? (
                        <Empty text="Sin movimientos" />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm" style={{ minWidth: 760 }}>
                                <thead style={theadStyle}>
                                    <tr>
                                        <Th>Fecha</Th>
                                        <Th>Movimiento</Th>
                                        <Th>Documento</Th>
                                        <Th>Detalle</Th>
                                        <Th right>Importe</Th>
                                        <Th right>Saldo</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {movimientos.map((m, i) => (
                                        <tr key={`${m.tipo}-${m.documento}-${i}`} style={zebra(i)}>
                                            <td className="px-3 py-2.5 whitespace-nowrap">{fecha(m.fecha)}</td>
                                            <td className="px-3 py-2.5">
                                                <Badge variant={m.variant}>{m.etiqueta}</Badge>
                                            </td>
                                            <td className="px-3 py-2.5 font-medium whitespace-nowrap">{m.documento || '—'}</td>
                                            <td className="px-3 py-2.5" style={{ color: 'var(--color-text-muted)' }}>
                                                {m.detalle}
                                            </td>
                                            <td
                                                className="px-3 py-2.5 text-right whitespace-nowrap"
                                                style={{
                                                    color: m.monto >= 0 ? 'var(--color-success)' : 'var(--color-danger)',
                                                    fontVariantNumeric: 'tabular-nums',
                                                }}
                                            >
                                                {m.monto >= 0 ? '+' : ''}{fmtS(m.monto)}
                                            </td>
                                            <td
                                                className="px-3 py-2.5 text-right font-semibold whitespace-nowrap"
                                                style={{ fontVariantNumeric: 'tabular-nums' }}
                                            >
                                                {fmtS(m.saldo)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </ReportCard>

                <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
                    Importe positivo = a nuestro favor · negativo = en nuestra contra. Los anticipos de mercadería se
                    valorizan al precio congelado de la venta, no al precio del día. Para cobrar o pagar, usa Cuentas
                    por cobrar / Cuentas por pagar.
                </p>
            </div>
        </AppLayout>
    );
}
