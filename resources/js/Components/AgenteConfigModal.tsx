import { useEffect, useRef, useState } from 'react';
import { Printer, X } from 'lucide-react';
import { getAgenteUrlConfigured, setAgenteUrl } from '@/lib/ticketPrinter';
import axios from 'axios';
import toast from 'react-hot-toast';

interface Props {
  open: boolean;
  onClose: () => void;
}

export default function AgenteConfigModal({ open, onClose }: Props) {
  const [url, setUrl] = useState('');
  const [pin, setPin] = useState('');
  const [loading, setLoading] = useState(false);
  const urlRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (open) {
      setUrl(getAgenteUrlConfigured());
      setPin('');
      // Pequeño delay para enfocar tras montar el modal
      setTimeout(() => urlRef.current?.focus(), 50);
    }
  }, [open]);

  useEffect(() => {
    if (!open) return;
    const onEsc = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', onEsc);
    return () => document.removeEventListener('keydown', onEsc);
  }, [open, onClose]);

  const guardar = async () => {
    setLoading(true);
    try {
      const { data } = await axios.post(route('configuracion.impresion.verificar-pin'), { pin });
      if (data.ok) {
        setAgenteUrl(url);
        onClose();
        toast.success('Configuración de impresora guardada. Se recargará la página.', { duration: 3000 });
        window.location.reload();
      } else {
        toast.error('PIN incorrecto.', { id: 'print-pin-error' });
      }
    } catch (e: any) {
      const msg = e.response?.data?.message || e.response?.data?.errors?.pin?.[0] || 'No se pudo validar el PIN.';
      toast.error(msg, { id: 'print-pin-error' });
    } finally {
      setLoading(false);
    }
  };

  if (!open) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4"
      onClick={onClose}
      aria-modal="true"
      role="dialog"
    >
      <div
        className="w-full max-w-sm rounded-2xl border shadow-2xl p-5 space-y-4"
        style={{
          backgroundColor: 'var(--color-surface)',
          borderColor: 'var(--color-border)',
        }}
        onClick={e => e.stopPropagation()}
      >
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <Printer size={18} style={{ color: 'var(--color-primary)' }} />
            <span className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>
              Agente de impresión
            </span>
          </div>
          <button
            onClick={onClose}
            className="rounded p-1 transition-colors hover:bg-black/5"
            style={{ color: 'var(--color-text-muted)' }}
          >
            <X size={16} />
          </button>
        </div>

        <p className="text-xs" style={{ color: 'var(--color-text-muted)' }}>
          URL donde corre VentoryPrint en esta red. Para tablets, apunta a la PC que tiene el agente instalado.
        </p>

        <div>
          <label className="text-xs font-medium block mb-1" style={{ color: 'var(--color-text)' }}>
            URL del agente
          </label>
          <input
            ref={urlRef}
            type="text"
            value={url}
            onChange={e => setUrl(e.target.value)}
            placeholder="http://192.168.1.50:9111"
            className="w-full rounded-xl border px-3 py-2 text-sm"
            style={{
              borderColor: 'var(--color-border)',
              backgroundColor: 'var(--color-bg)',
              color: 'var(--color-text)',
            }}
          />
          <p className="text-xs mt-1" style={{ color: 'var(--color-text-muted)' }}>
            Default: http://127.0.0.1:9111
          </p>
        </div>

        <div>
          <label className="text-xs font-medium block mb-1" style={{ color: 'var(--color-text)' }}>
            PIN de seguridad
          </label>
          <input
            type="password"
            value={pin}
            onChange={e => setPin(e.target.value)}
            placeholder="Ingresa el PIN"
            className="w-full rounded-xl border px-3 py-2 text-sm"
            style={{
              borderColor: 'var(--color-border)',
              backgroundColor: 'var(--color-bg)',
              color: 'var(--color-text)',
            }}
          />
          <p className="text-xs mt-1" style={{ color: 'var(--color-text-muted)' }}>
            Requerido para cambiar la configuración.
          </p>
        </div>

        <button
          onClick={guardar}
          disabled={loading}
          className="w-full rounded-lg px-3 py-2.5 text-sm font-medium text-white transition-colors disabled:opacity-60"
          style={{ backgroundColor: 'var(--color-primary)' }}
        >
          {loading ? 'Verificando...' : 'Guardar y recargar'}
        </button>
      </div>
    </div>
  );
}
