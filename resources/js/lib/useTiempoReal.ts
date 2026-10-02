import { useEffect, useRef } from 'react';
import { usePage } from '@inertiajs/react';
import { getEcho } from '@/lib/echo';

export type RecursoTiempoReal = 'clientes' | 'productos' | 'stock' | 'ventas' | 'despachos';

/**
 * La pantalla se pone al día sola cuando, en su empresa, cambia alguno de
 * `recursos` (lo avisa el backend con App\Events\Cambio). El aviso no trae
 * datos: `alCambiar` vuelve a pedirlos al servidor.
 *
 *  - Varios avisos seguidos → una sola recarga (espera `esperaMs`).
 *  - Al reconectarse tras una caída de internet también recarga, por si se
 *    perdió algún aviso mientras tanto.
 *
 *   useTiempoReal(['ventas'], () => router.reload({ only: ['ventas'] }));
 */
export function useTiempoReal(recursos: RecursoTiempoReal[], alCambiar: () => void, esperaMs = 700) {
    const empresaId = (usePage().props as any).auth?.user?.empresa_id as number | null | undefined;
    const alCambiarRef = useRef(alCambiar);
    alCambiarRef.current = alCambiar;
    const clave = recursos.join(',');

    useEffect(() => {
        const echo = getEcho();
        if (!echo || !empresaId) return;

        let timer: ReturnType<typeof setTimeout> | null = null;
        const programar = () => {
            if (timer) clearTimeout(timer);
            timer = setTimeout(() => alCambiarRef.current(), esperaMs);
        };

        const lista = clave.split(',');
        const canal = echo.private(`empresa.${empresaId}`);
        const oyente = (e: { recursos?: string[] }) => {
            if (e.recursos?.some(r => lista.includes(r))) programar();
        };
        canal.listen('.cambio', oyente);

        // Reconexión: si hubo corte, recargar lo que muestra la pantalla.
        const conexion = (echo.connector as any).pusher?.connection;
        let huboCorte = false;
        const alCambiarEstado = ({ current }: { current: string }) => {
            if (current === 'unavailable' || current === 'disconnected') huboCorte = true;
            if (current === 'connected' && huboCorte) { huboCorte = false; programar(); }
        };
        conexion?.bind('state_change', alCambiarEstado);

        return () => {
            if (timer) clearTimeout(timer);
            canal.stopListening('.cambio', oyente);
            conexion?.unbind('state_change', alCambiarEstado);
        };
    }, [empresaId, clave, esperaMs]);
}
