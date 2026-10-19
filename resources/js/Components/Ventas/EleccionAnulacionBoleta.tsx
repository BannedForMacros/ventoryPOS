/**
 * Cómo se anula una venta con BOLETA: la pregunta que decide el documento ante SUNAT.
 *
 *  · El cliente NO se llevó la boleta → baja por Resumen Diario, sin nota de crédito
 *    (se anula al instante aunque la boleta aún no haya salido en el resumen).
 *  · El cliente se llevó la boleta y devuelve → nota de crédito (solo si la boleta
 *    ya está en SUNAT; si no, se explica por qué todavía no).
 *
 * No hay opción marcada por defecto: elegir mal emite un documento fiscal que no se
 * deshace, así que quien anula tiene que contestar.
 */
export type ViaAnulacion = 'baja' | 'nota_credito';

interface Props {
    comprobante: string;
    valor: ViaAnulacion | null;
    onChange: (via: ViaAnulacion) => void;
    /** null = la nota de crédito se puede emitir; texto = por qué no. */
    motivoSinNc: string | null;
    disabled?: boolean;
}

export default function EleccionAnulacionBoleta({ comprobante, valor, onChange, motivoSinNc, disabled }: Props) {
    const opciones: { via: ViaAnulacion; titulo: string; detalle: string; habilitada: boolean }[] = [
        {
            via: 'baja',
            titulo: 'El cliente NO se llevó la boleta',
            detalle: `Error al cobrar o venta que no se concretó. Se anula la boleta ${comprobante} ante SUNAT sin nota de crédito, al instante.`,
            habilitada: true,
        },
        {
            via: 'nota_credito',
            titulo: 'El cliente se llevó la boleta y devuelve',
            detalle: motivoSinNc ?? 'Se emite la nota de crédito de la boleta.',
            habilitada: motivoSinNc === null,
        },
    ];

    return (
        <fieldset className="space-y-2" disabled={disabled}>
            <legend className="text-sm font-medium mb-1" style={{ color: 'var(--color-text)' }}>
                ¿Qué pasó con la boleta? <span style={{ color: 'var(--color-danger)' }}>*</span>
            </legend>
            {opciones.map(o => (
                <label
                    key={o.via}
                    className={`flex gap-2 rounded-xl px-3 py-2 text-sm ${o.habilitada ? 'cursor-pointer' : 'opacity-60 cursor-not-allowed'}`}
                    style={{
                        border: `1px solid ${valor === o.via ? 'var(--color-primary)' : 'var(--color-border)'}`,
                        backgroundColor: 'var(--color-surface)',
                    }}
                >
                    <input
                        type="radio"
                        name="via-anulacion"
                        className="mt-1"
                        checked={valor === o.via}
                        disabled={!o.habilitada || disabled}
                        onChange={() => onChange(o.via)}
                    />
                    <span>
                        <span className="block font-semibold" style={{ color: 'var(--color-text)' }}>{o.titulo}</span>
                        <span className="block text-xs mt-0.5" style={{ color: 'var(--color-text-muted)' }}>{o.detalle}</span>
                    </span>
                </label>
            ))}
        </fieldset>
    );
}
