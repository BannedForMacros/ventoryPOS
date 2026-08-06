import { useEffect, useRef, useState } from 'react';
import { Printer, Settings2, X } from 'lucide-react';
import { getAgenteUrlConfigured, setAgenteUrl } from '@/lib/ticketPrinter';

export default function AgenteConfigButton() {
  const [open, setOpen] = useState(false);
  const [url, setUrl] = useState('');
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    setUrl(getAgenteUrlConfigured());
  }, [open]);

  useEffect(() => {
    if (!open) return;
    const onDoc = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    };
    const onEsc = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOpen(false);
    };
    document.addEventListener('mousedown', onDoc);
    document.addEventListener('keydown', onEsc);
    return () => {
      document.removeEventListener('mousedown', onDoc);
      document.removeEventListener('keydown', onEsc);
    };
  }, [open]);

  const guardar = () => {
    setAgenteUrl(url);
    setOpen(false);
    window.location.reload();
  };

  return (
    <div className="relative" ref={ref}>
      <button
        onClick={() => setOpen(o => !o)}
        title="Configurar impresora/agente"
        aria-label="Configurar impresora/agente"
        className="relative flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg transition-all duration-200 hover:bg-black/5 active:scale-95"
        style={{ color: 'var(--color-text)' }}
      >
        <Printer size={19} />
      </button>

      {open && (
        <div
          className="absolute right-0 mt-2 w-80 origin-top-right rounded-xl border shadow-xl p-4 space-y-3"
          style={{
            backgroundColor: 'var(--color-surface)',
            borderColor: 'var(--color-border)',
          }}
        >
          <div className="flex items-center justify-between">
            <span className="text-sm font-semibold" style={{ color: 'var(--color-text)' }}>
              Agente de impresión
            </span>
            <button
              onClick={() => setOpen(false)}
              className="rounded p-1 transition-colors hover:bg-black/5"
              style={{ color: 'var(--color-text-muted)' }}
            >
              <X size={14} />
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

          <button
            onClick={guardar}
            className="w-full rounded-lg px-3 py-2 text-sm font-medium text-white transition-colors"
            style={{ backgroundColor: 'var(--color-primary)' }}
          >
            Guardar y recargar
          </button>
        </div>
      )}
    </div>
  );
}
