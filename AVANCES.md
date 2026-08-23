# Resumen de Avances — VentoryPOS

**Fecha:** 23 de agosto de 2026
**Rama/estado:** implementación de correcciones operativas en anticipos/entregas y reportes de caja.

---

## 1. Objetivo general

Permitir correcciones operativas sobre anticipos de clientes y sus entregas de material sin romper la contabilidad de caja, cuentas por cobrar, kárdex, turnos ni comprobantes electrónicos.

---

## 2. Funcionalidades implementadas

### 2.1 Cambiar producto en un anticipo
- **Descripción:** permite reemplazar el producto de un ítem de anticipo antes de que se entregue.
- **Criterios:** actualiza el ítem original, conserva cantidades/precios y refleja el cambio en el kárdex y en la venta ligada.
- **Archivos clave:**
  - `app/Http/Controllers/Finanzas/AnticipoClienteController.php`
  - `resources/js/Pages/Finanzas/Anticipos.tsx`

### 2.2 Editar entrega de material
- **Descripción:** permite modificar la cantidad entregada de un ítem ya parcialmente entregado, incluyendo llevarla a **0** para anular la entrega.
- **Validación corregida:** se cambió la regla de `items` de `required|array|min:1` a `present|array`, permitiendo arrays vacíos.
- **Impacto:** ajusta `cantidad_pendiente`, `saldo`, `estado` del anticipo y la venta asociada.
- **Archivos clave:**
  - `app/Http/Controllers/Finanzas/AnticipoClienteController.php` (`editarEntregaMaterial`)
  - `tests/Feature/Pos/PendienteEntregaTest.php`

### 2.3 Convertir contado ↔ crédito en anticipo
- **Descripción:** permite cambiar la forma de pago de un anticipo entre contado y crédito, moviendo saldos entre `monto_pagado` y `saldo_pendiente` y generando/ajustando los movimientos de tesorería/CxC correspondientes.
- **Archivos clave:**
  - `app/Http/Controllers/Finanzas/AnticipoClienteController.php`
  - `app/Models/ClienteAnticipo.php`
  - `resources/js/Pages/Finanzas/Anticipos.tsx`

### 2.4 Refinamiento de búsqueda de productos
- Se mejoró el buscador para anticipos/ventas con coincidencias por código, nombre y presentación.
- Archivos relacionados en `resources/js/Components/` y `app/Http/Controllers/Ventas/`.

### 2.5 Cancelar pendiente (nueva funcionalidad)

#### Backend
- **Nueva tabla:** `cliente_anticipo_cancelaciones`.
  - SQL de producción: `database/scripts/create_cliente_anticipo_cancelaciones.sql`
  - Rollback: `database/scripts/rollback_cliente_anticipo_cancelaciones.sql`
- **Modelo:** `app/Models/ClienteAnticipoCancelacion.php`
- **Relaciones agregadas:**
  - `app/Models/ClienteAnticipo.php` → `cancelaciones()`
  - `app/Models/ClienteAnticipoItem.php` → `cancelaciones()`
  - `app/Models/Turno.php` → `cancelacionesAnticipo()`
- **Controlador:** nuevo método `cancelarPendienteItem` en `app/Http/Controllers/Finanzas/AnticipoClienteController.php`.
- **Ruta:** `POST /anticipos/{anticipo}/items/{item}/cancelar-pendiente` en `routes/web.php`.
- **Afectación a caja/turno:**
  - Integrado con `app/Support/AfectaCaja.php` (módulo `anticipos_cancelacion`).
  - `app/Models/Turno.php::calcularMontoEsperado()` descuenta las cancelaciones en efectivo.
  - `app/Http/Controllers/Reportes/ReporteCajaController.php` agrega el total de cancelaciones.
  - `app/Http/Controllers/Finanzas/BalanceDiarioController.php` etiqueta el movimiento como `anticipo_cancelacion`.
- **Reglas de negocio:**
  - No se permite cancelar si el anticipo tiene un comprobante electrónico informado a SUNAT.
  - Contado: genera egreso en tesorería; crédito: reduce `saldo_pendiente` sin movimiento de caja.
  - Ajusta `Venta.total`, `Venta.monto_pagado`, `Venta.saldo_pendiente`, `ClienteAnticipoItem.cantidad`, `cantidad_pendiente`, `saldo` y `estado`.

#### Frontend
- Nuevo botón **“Cancelar pendiente”** en `resources/js/Pages/Finanzas/Anticipos.tsx`.
- Modal con:
  - Cantidad a cancelar.
  - Motivo y observación.
  - Selector contado/crédito.
  - Selector método de pago / cuenta (si aplica).
  - `AfectaCajaSelect` para decidir si afecta caja/turno.
- Listado de cancelaciones en el detalle del anticipo.

#### Tests
- Se agregaron tests en `tests/Feature/Pos/PendienteEntregaTest.php`:
  - Cancelación total parcial en contado.
  - Cancelación en crédito.
  - Cancelación con y sin afectación a caja.
  - Bloqueo por comprobante electrónico.
- Resultado: `php artisan test tests/Feature/Pos/PendienteEntregaTest.php` → **14 passed**.

### 2.6 Botón “Excel” en el componente de tabla
- **Archivo:** `resources/js/Components/UI/Table.tsx`
- **Props nuevas:**
  - `exportable?: boolean`
  - `exportFilename?: string` (default: `exportacion`)
  - `onExportExcel?: () => void`
- **Comportamiento:**
  - Si la tabla usa datos locales (array), el botón descarga un CSV con **todas las filas filtradas y ordenadas**, es decir, **todas las páginas**, no solo la visible.
  - Si la tabla es paginada por el servidor, se puede pasar `onExportExcel` para que el padre traiga todo el dataset y descargue el Excel real.
  - Si no hay callback y la paginación es server-side, el botón no se muestra.
- El archivo se abre directamente en Excel por ser CSV con BOM UTF-8.

---

## 3. Scripts SQL para producción

- **Creación:** `database/scripts/create_cliente_anticipo_cancelaciones.sql`
- **Rollback:** `database/scripts/rollback_cliente_anticipo_cancelaciones.sql`

> **Nota importante:** el script de creación ya fue ejecutado en el servidor de producción el 23/08/2026. La migración Laravel (`2026_08_23_115616_create_cliente_anticipo_cancelaciones_table.php`) sigue en el repo y **debe ser eliminada o evitada en producción**, dado que el usuario pidió “nada de migraciones”.

---

## 4. Estado de pruebas y build

- `php artisan test tests/Feature/Pos tests/Feature/Finanzas tests/Feature/Ventas` → **133 passed**.
- `npx tsc --noEmit` → **sin errores**.
- `npm run build` → **exitoso**.

---

## 5. Decisiones pendientes

1. **Migración Laravel:**
   - ¿Se elimina del repo `database/migrations/2026_08_23_115616_create_cliente_anticipo_cancelaciones_table.php`?
   - Si se elimina, los tests/CI en una base de datos fresca necesitarán correr el script SQL en lugar de `php artisan migrate`.

2. **Excel server-side:**
   - Las páginas que usan `Table` con paginación server-side y quieran exportar **todas las páginas** deben implementar `onExportExcel` (por ejemplo abriendo una ruta backend que devuelva todas las filas filtradas).

3. **Deploy a producción:**
   - Subir cambios (`git pull`).
   - Ejecutar `composer install --no-dev --optimize-autoloader`.
   - Ejecutar `npm ci && npm run build`.
   - **No ejecutar `php artisan migrate`** para esta tabla, ya existe vía SQL.

---

## 6. Archivos más relevantes

- `resources/js/Components/UI/Table.tsx`
- `resources/js/Pages/Finanzas/Anticipos.tsx`
- `app/Http/Controllers/Finanzas/AnticipoClienteController.php`
- `app/Models/ClienteAnticipoCancelacion.php`
- `app/Models/ClienteAnticipo.php`
- `app/Models/ClienteAnticipoItem.php`
- `app/Models/Turno.php`
- `app/Support/AfectaCaja.php`
- `app/Http/Controllers/Reportes/ReporteCajaController.php`
- `app/Http/Controllers/Finanzas/BalanceDiarioController.php`
- `database/scripts/create_cliente_anticipo_cancelaciones.sql`
- `database/scripts/rollback_cliente_anticipo_cancelaciones.sql`
- `tests/Feature/Pos/PendienteEntregaTest.php`
