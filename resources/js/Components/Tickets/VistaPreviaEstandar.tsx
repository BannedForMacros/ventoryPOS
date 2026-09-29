import { Image as ImageIcon } from 'lucide-react';
import { partir } from '@/lib/ticketBloques';
import type { TicketPayload } from '@/lib/ticketPrinter';
import { Papel, Linea } from './VistaPreviaTicket';

/**
 * El ticket ESTÁNDAR, el de siempre, tal como lo imprime el agente. Es una
 * copia línea por línea de Printing/TicketRenderer.cs (nota de venta y
 * cotización): mismas columnas, mismas etiquetas, mismos anchos.
 */
export default function VistaPreviaEstandar({ ticket, papel = 80 }: { ticket: TicketPayload; papel?: 80 | 58 }) {
    // Columnas del agente: 80 mm = 26+5+8+9, 58 mm = 14+4+6+8.
    const l = papel === 80 ? { qty: 5, pu: 8, sub: 9, ancho: 48 } : { qty: 4, pu: 6, sub: 8, ancho: 32 };
    const W = l.ancho;
    const n = ticket.negocio ?? {};
    const d = ticket.documento ?? {};
    const c = ticket.cliente ?? {};
    const t = ticket.totales;
    const p = ticket.pago ?? {};

    const raya = '-'.repeat(W);
    const dinero = (v: number) => v.toFixed(2);
    const cantidad = (v: number) => String(Number(v.toFixed(2)));
    const sym = t?.moneda === 'USD' ? '$ ' : 'S/ ';

    const etiquetaFija = (etq: string, valor: string) => {
        if (etq.length + 1 + valor.length >= W) return `${etq} ${valor}`;
        const col = Math.max(etq.length, Math.min(Math.min(20, Math.floor(W * 0.55)), W - 1 - valor.length));
        return `${etq.padEnd(col)} ${valor}`;
    };
    const conSangria = (etq: string, valor: string, col = 10) => {
        const c0 = Math.max(col, etq.length + 1);
        if (W - c0 < 8) return [`${etq} ${valor}`];
        const partes = partir(valor, W - c0);
        return [etq.padEnd(c0) + partes[0], ...partes.slice(1).map(x => ' '.repeat(c0) + x)];
    };
    const monto = (etq: string, valor: string) =>
        etq.length + valor.length >= W ? `${etq} ${valor}` : etq.padEnd(W - valor.length) + valor;
    const fila = (nombre: string, cant: string, pu: string, sub: string) => {
        const numeros = ` ${cant.padStart(l.qty - 1)} ${pu.padStart(l.pu - 1)} ${sub.padStart(l.sub - 1)}`;
        const a = Math.max(1, W - numeros.length);
        const partes = partir(nombre, a);
        return [partes[0].padEnd(a) + numeros, ...partes.slice(1).map(x => x.padEnd(a))];
    };

    const tipo = (d.tipo?.trim() || 'NOTA DE VENTA').toUpperCase();
    const llevaIgv = !!n.mostrarIgv && /BOLETA|FACTURA/.test(tipo);

    const izquierda: string[] = [
        raya,
        ...(d.fecha ? [etiquetaFija('Fecha:', d.fecha)] : []),
        ...(d.vendedor ? [etiquetaFija('Cajero:', d.vendedor)] : []),
        ...(d.caja ? [etiquetaFija('Caja:', d.caja)] : []),
        ...conSangria('Cliente:', c.nombre?.trim() || 'Cliente Varios'),
        ...(c.doc ? conSangria('Doc:', c.doc) : []),
        ...(c.telefono ? conSangria('Celular:', c.telefono) : []),
        ...(c.direccion ? conSangria('Direc:', c.direccion) : []),
        '',
        raya,
        ...fila('Producto', 'Cant.', 'P.U.', 'Impte.'),
        raya,
        ...ticket.items.flatMap(it => fila(it.unidad ? `${it.desc} x ${it.unidad}` : it.desc, cantidad(it.cant), dinero(it.precio), dinero(it.importe))),
        raya,
        ...(t && llevaIgv && t.subtotal && t.subtotal > 0 && t.subtotal !== t.total ? [monto('SUBTOTAL:', sym + dinero(t.subtotal))] : []),
        ...(t && llevaIgv && t.igv && t.igv > 0 ? [monto('IGV:', sym + dinero(t.igv))] : []),
        ...(t?.descuento && t.descuento > 0 ? [monto('DESCUENTO:', '-' + sym + dinero(t.descuento))] : []),
    ];

    return (
        <Papel papel={papel} etiqueta="Vista previa del ticket estándar">
            {ticket.logo && (
                <div className="flex justify-center pb-2">
                    <span className="flex items-center gap-1.5 px-5 py-3 text-[11px]" style={{ border: '1px dashed #9ca3af', color: '#6b7280' }}>
                        <ImageIcon size={14} /> Logo
                    </span>
                </div>
            )}
            {n.nombre && <Linea texto={n.nombre.toUpperCase()} tamano="ancho" negrita alinear="centro" />}
            {(n.mostrarRuc ?? true) && n.ruc && <Linea texto={`RUC: ${n.ruc}`} alinear="centro" />}
            {n.direccion && partir(n.direccion, W).map((x, i) => <Linea key={`d${i}`} texto={x} alinear="centro" />)}
            {n.telefono && <Linea texto={`Telf: ${n.telefono}`} alinear="centro" />}
            <Linea texto="" />

            <Linea texto={tipo} negrita alinear="centro" />
            {d.numero && <Linea texto={d.numero} negrita alinear="centro" />}

            {izquierda.map((x, i) => <Linea key={i} texto={x} />)}
            {t && <Linea texto={monto('TOTAL:', sym + dinero(t.total))} tamano="alto" negrita />}

            {p.metodo && <Linea texto={monto('PAGO:', p.metodo.toUpperCase())} />}
            {!!p.recibido && p.recibido > 0 && <Linea texto={monto('RECIBIDO:', sym + dinero(p.recibido))} />}
            {!!p.vuelto && p.vuelto > 0 && <Linea texto={monto('VUELTO:', sym + dinero(p.vuelto))} />}

            <Linea texto="" />
            {(ticket.pie?.trim() || 'Gracias por su preferencia').split('\n')
                .flatMap(x => partir(x.trim(), W))
                .map((x, i) => <Linea key={`p${i}`} texto={x} alinear="centro" />)}
        </Papel>
    );
}
