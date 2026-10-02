import toast from 'react-hot-toast';

/**
 * Muestra un error del servidor en un aviso. El id = mensaje hace que, si el
 * aviso global de app.tsx ya mostró el mismo texto, salga UNA sola vez.
 */
export function avisoError(mensaje: unknown, siVacio = 'No se pudo completar la operación.') {
    const texto = typeof mensaje === 'string' && mensaje.trim() ? mensaje : siVacio;
    toast.error(texto, { id: texto });
}
