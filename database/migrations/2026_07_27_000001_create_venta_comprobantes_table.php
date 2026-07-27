<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V1 — Comprobante electrónico SUNAT asociado a una venta (integración FacturaMac).
 *
 * Por qué una tabla aparte y no columnas en `ventas`: la emisión electrónica es un
 * proceso ASÍNCRONO con vida propia (reintentos, polling, rechazos, notas de crédito)
 * que no debe contaminar la fila de la venta ni obligar a tocar `VentaService`. La
 * venta se cierra SIEMPRE; el comprobante se emite después y puede fallar sin que la
 * caja se detenga. Acoplamiento de solo lectura: nada aquí modifica el flujo de venta.
 *
 * UNIQUE en venta_id: una venta = a lo sumo un comprobante electrónico. Si SUNAT
 * rechaza, se REINTENTA sobre esta misma fila (mismo correlativo) en vez de crear
 * otra — así no se queman correlativos y no se emiten duplicados fiscales (G11).
 *
 * Ciclo de vida del campo `estado`:
 *   pendiente        → creado localmente, aún no enviado a FacturaMac.
 *   enviando         → aceptado por FacturaMac, en cola hacia SUNAT (facturas, tipo 01).
 *   pendiente_resumen→ boleta (tipo 03) informada en el Resumen Diario de las 23:55.
 *                      El ticket ya se puede imprimir con serie-correlativo + QR.
 *   aceptado         → CDR de SUNAT con código 0.
 *   rechazado        → CDR con código de error; requiere corregir y reintentar.
 *   error_envio      → fallo de red/5xx/timeout hablando con FacturaMac. REINTENTABLE.
 *   error_mapeo      → el mapper abortó (§5.3: descuadre > 0.05, factura sin RUC,
 *                      tasa_igv ≠ 18, moneda ≠ PEN). NO reintentable sin arreglar el dato.
 *   anulado          → revertido mediante Nota de Crédito.
 *
 * `idempotency_key` viaja al header `Idempotency-Key` de FacturaMac: si el POS
 * reintenta por timeout de red, FacturaMac devuelve 409 con el comprobante original
 * en vez de emitir un segundo comprobante fiscal real (G6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta_comprobantes', function (Blueprint $table) {
            $table->id();
            // UNIQUE antes de constrained(): foreignId() devuelve ColumnDefinition,
            // constrained() ya devuelve ForeignKeyDefinition y no admite ->unique().
            $table->foreignId('venta_id')->unique()->constrained('ventas')->cascadeOnDelete();

            // '01' factura | '03' boleta (Catálogo 01 SUNAT). El tipo `ticket` del POS
            // NO llega aquí: es nota de venta interna y no se emite.
            $table->string('tipo', 2);

            $table->string('serie', 4)->nullable();
            $table->integer('correlativo')->nullable();
            $table->string('numero', 20)->nullable();

            $table->string('estado', 30)->default('pendiente');

            // Id del comprobante en FacturaMac: es la llave para consultar estado,
            // descargar PDF/XML/CDR y emitir la Nota de Crédito.
            $table->integer('facturamac_id')->nullable();

            $table->string('hash_cpe')->nullable();
            $table->text('qr')->nullable();

            // Respuesta del CDR de SUNAT (código 0 = aceptado).
            $table->string('sunat_codigo')->nullable();
            $table->text('sunat_descripcion')->nullable();

            $table->text('error')->nullable();
            $table->integer('intentos')->default(0);
            $table->timestamp('enviado_at')->nullable();

            $table->string('idempotency_key', 100)->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_comprobantes');
    }
};
