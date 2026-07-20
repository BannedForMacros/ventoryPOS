import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';

// Solo muestra el overlay si la navegación tarda más de este tiempo (ms).
// Evita que flashee en navegaciones rápidas.
const SHOW_DELAY_MS = 300;

/**
 * Splash de navegación SOLO para cambios de página reales (otro pathname).
 * Las recargas de datos de la misma página (buscar, paginar, filtrar con
 * preserveState) NO lo disparan: de eso se encarga el loading propio de la
 * tabla/los componentes, que aparece al instante y no tapa la aplicación.
 */
export default function RouterLoadingOverlay() {
    const [visible, setVisible] = useState(false);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        const removeStart = router.on('start', (event) => {
            const visitUrl = (event as CustomEvent).detail?.visit?.url as URL | undefined;
            // Misma ruta => recarga de datos (búsqueda/paginación/filtros): sin splash.
            if (visitUrl && visitUrl.pathname === window.location.pathname) return;
            timer.current = setTimeout(() => setVisible(true), SHOW_DELAY_MS);
        });

        const removeFinish = router.on('finish', () => {
            if (timer.current) clearTimeout(timer.current);
            setVisible(false);
        });

        return () => {
            removeStart();
            removeFinish();
            if (timer.current) clearTimeout(timer.current);
        };
    }, []);

    if (!visible) return null;

    return (
        <div
            className="fixed inset-0 z-[9999] flex flex-col items-center justify-center gap-8"
            style={{ backgroundColor: '#0F4C81' }}
        >
            <img src="/logo-full-white.svg" alt="ventoryPOS" className="h-14 w-auto" />
            <div
                className="h-10 w-10 rounded-full animate-spin"
                style={{
                    border: '3px solid rgba(255,255,255,0.2)',
                    borderTopColor: '#ffffff',
                }}
            />
        </div>
    );
}
