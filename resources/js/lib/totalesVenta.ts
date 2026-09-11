/**
 * Total de una venta con la MISMA fórmula que Venta::calcularTotales (PHP):
 * IGV por producto (incluye_igv) + descuento global prorrateado entre base
 * gravada y exonerada. Se usa para mostrar en vivo un total que coincida al
 * centavo con el que guardará el servidor.
 */
export interface LineaTotal { precio: number; desc: number; cantidad: number; incluyeIgv: boolean; }

/**
 * Redondeo a 2 decimales IGUAL al round() de PHP 8.4+: toma el entero de
 * céntimos por debajo y sube solo si el número llega al punto medio exacto
 * (i + 0.5)/100. Math.round() difería en 1 céntimo en valores como 86.555 y el
 * servidor rechazaba el cobro por esa diferencia. Verificado contra PHP con
 * 20.000 números y 500 ventas aleatorias.
 */
export const redondear2 = (x: number): number => {
    const a = Math.abs(x);
    let i = Math.floor(a * 100);
    if (i / 100 > a) i -= 1;
    if ((i + 1) / 100 <= a) i += 1;
    return (Math.sign(x) * (a >= (i + 0.5) / 100 ? i + 1 : i)) / 100;
};
const r2 = redondear2;

export function calcularTotalVenta(lineas: LineaTotal[], descuentoTotal: number, tasaPct: number): number {
    const tasa = tasaPct / 100;
    let baseGravadaRaw = 0, baseExonerada = 0;
    for (const l of lineas) {
        const importe = (l.precio - l.desc) * l.cantidad;
        if (l.incluyeIgv) baseGravadaRaw += tasa > 0 ? importe / (1 + tasa) : importe;
        else baseExonerada += importe;
    }
    const brutoGravado = baseGravadaRaw * (1 + tasa);
    const totalBruto = brutoGravado + baseExonerada;
    let descGravadoNeto = 0, descExonerado = 0;
    if (totalBruto > 0 && descuentoTotal > 0) {
        const descGravadoBruto = descuentoTotal * (brutoGravado / totalBruto);
        descExonerado = descuentoTotal * (baseExonerada / totalBruto);
        descGravadoNeto = tasa > 0 ? descGravadoBruto / (1 + tasa) : descGravadoBruto;
    }
    const baseGravadaFinal = Math.max(0, baseGravadaRaw - descGravadoNeto);
    const baseExonFinal = Math.max(0, baseExonerada - descExonerado);
    const igv = r2(baseGravadaFinal * tasa);
    return r2(baseGravadaFinal + igv + baseExonFinal);
}

