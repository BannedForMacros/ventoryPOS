import axios from 'axios';
import toast from 'react-hot-toast';
import { agenteActivo, imprimirCierreTurno, type ShiftClosurePayload } from '@/lib/ticketPrinter';

/** Imprime el reporte de cierre del turno en la ticketera de la caja (si hay agente). */
export async function imprimirCierreAuto(turnoId: number): Promise<void> {
    if (!(await agenteActivo())) {
        toast.error('No se imprimió el cierre: el agente VentoryPrint no está activo en esta PC.');
        return;
    }

    try {
        const { data } = await axios.get<ShiftClosurePayload>(route('turnos.cierre-ticket', turnoId));
        if (!data?.token) {
            toast.error('Esta caja no tiene ticketera configurada.');
            return;
        }
        const ok = await imprimirCierreTurno(data);
        if (ok) toast.success('Reporte de cierre enviado a la impresora');
        else    toast.error('No se pudo imprimir el cierre. Revisa VentoryPrint en esta PC.');
    } catch {
        toast.error('No se pudo obtener el reporte de cierre para imprimir.');
    }
}
