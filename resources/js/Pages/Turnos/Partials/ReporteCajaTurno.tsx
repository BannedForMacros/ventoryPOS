import { FileSpreadsheet, Printer, ClipboardList } from 'lucide-react';

/**
 * Reporte de caja del turno (función opcional por empresa): es un reporte
 * para descargar, no una tabla en pantalla. Una fila por comprobante con el
 * dinero en las columnas de la empresa y una casilla por venta para
 * revisarla a mano.
 */
export default function ReporteCajaTurno({ turnoId }: { turnoId: number }) {
    return (
        <section className="rounded-2xl mb-6 flex flex-wrap items-center gap-x-5 gap-y-3 px-4 py-3.5"
            style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)', boxShadow: '0 6px 16px -10px rgb(15 76 129 / 0.14)' }}>
            <span className="flex h-10 w-10 items-center justify-center rounded-xl text-white flex-shrink-0" style={{ backgroundColor: 'var(--vp-navy)' }}>
                <ClipboardList size={19} />
            </span>
            <div className="min-w-0 flex-1">
                <h2 className="font-display text-base font-bold" style={{ color: 'var(--vp-navy)' }}>Reporte de caja del turno</h2>
                <p className="text-[13px] mt-0.5" style={{ color: 'var(--color-text-muted)' }}>
                    Una fila por comprobante con el dinero en tus columnas, salidas, pagos anteriores y el efectivo en caja.
                    Trae una casilla por venta para revisarla en el papel.
                </p>
            </div>
            <div className="flex items-center gap-2 w-full sm:w-auto">
                <a href={route('turnos.planilla.excel', turnoId)}
                    className="inline-flex flex-1 sm:flex-none items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition-colors hover:bg-black/[0.03]"
                    style={{ border: '1px solid var(--color-border)', color: 'var(--vp-mint-ink)', backgroundColor: 'var(--color-surface)' }}>
                    <FileSpreadsheet size={16} /> Descargar Excel
                </a>
                <a href={route('turnos.planilla.imprimir', turnoId)} target="_blank" rel="noopener noreferrer"
                    className="inline-flex flex-1 sm:flex-none items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:brightness-110"
                    style={{ backgroundColor: 'var(--vp-navy)' }}>
                    <Printer size={16} /> PDF / Imprimir
                </a>
            </div>
        </section>
    );
}
