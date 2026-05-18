# Pendientes — ventoryPOS

Hoja de ruta de QA. Lo que ya está hecho en sesiones previas no aparece aquí.
Cada ítem incluye archivo y nivel de severidad por color:

- 🔴 **Bloqueador** — no salir a producción sin esto
- 🟠 **Alto** — atender en piloto o primeras semanas
- 🟡 **Medio** — atender en backlog ordinario
- 🔵 **Validación pendiente** — código ya escrito, falta probar en runtime
- ✅ **Cerrado** — implementado y validado con tests

---

## 🔴 Bloqueadores pendientes

### B1 — Secretos del repo y modo desarrollo
**Archivo:** `.env`
- `APP_ENV=local` y `APP_DEBUG=true` → en producción debe ser `production` / `false`.
- `APP_KEY` versionada → regenerar con `php artisan key:generate` e invalidar la actual.
- `DECOLECTA_TOKEN=sk_2512.os1jqrKBkTYs2xDPATzIkaRAevshrjBe` versionado → **rotar inmediatamente** con Decolecta.
- `DB_PASSWORD=postgres` (default trivial) → cambiar antes de exponer.

**Acción:** quitar `.env` del repo (agregar a `.gitignore` si no está), regenerar key, rotar token, usar variables de entorno reales del host.

**Estado:** se atiende durante el deploy. La data de prod será restaurada desde el local actual.

---

## ✅ Bloqueadores cerrados

### B7 — Tests del negocio ✅
**Sesión 17-may.** Suite Pest completa montada sobre Postgres real con `DatabaseTransactions` (rollback automático, BD intacta).

Cobertura por flujo:
- **POS** — venta, anulación, idempotencia, vuelto en pago mixto, sobrepago no-efectivo bloqueado, retry en concurrencia (A10), rate-limit (M17), motivo de anulación (M21), tope de descuento (M20).
- **Stock** — descuento por venta, restock por devolución, `InsufficientStockException`, scope negativos (A9).
- **Turnos** — abrir, cerrar con/sin diferencia, reabrir con motivo (A8), anulación de `CierreInventario` asociado.
- **Devoluciones** — total, parcial, motivo `obliga_merma`, producto no-retornable, anulación que deja stock negativo (A11).
- **Agenda** — agendar, completar+cobrar, producto inactivo prellenado, anti-colisión (A13), fecha futura obligatoria.
- **IGV** — gravado, exonerado, mixto, descuento prorrateado, `tasa_igv` configurable.
- **Inventario** — auditoría al borrar entrada/transferencia en borrador (M18), paginación (M19).
- **Configuración** — Cliente General por flag (A15), tope de descuento por rol (M20).
- **WhatsApp** — permiso explícito + auditoría (A12).

**Total:** 103 tests, 304 assertions, ~11 segundos.

**Cómo correr:**
```powershell
vendor/bin/pest tests/Feature/Pos tests/Feature/Stock tests/Feature/Turnos tests/Feature/Devoluciones tests/Feature/Agenda tests/Feature/Igv tests/Feature/Ventas tests/Feature/Clientes tests/Feature/Inventario tests/Feature/Reportes tests/Feature/Configuracion tests/Feature/Support
```

(Los tests `tests/Feature/Auth/*` y `tests/Feature/ProfileTest.php` son scaffolding de Laravel Breeze y siguen fallando por su uso de `User::factory()` — no son tests de negocio. Para una limpieza se pueden borrar.)

---

## ✅ Riesgos altos cerrados (A8–A15)

### A8 — Reabrir turno con motivo + anulación de CierreInventario ✅
- `ReabrirTurnoRequest` exige `motivo` (≥10 chars).
- `TurnoController::reabrir` anula los `cierres_inventario` con `estado='confirmado'` asociados al turno (nueva migración añadió `'anulado'` al CHECK constraint).
- Auditoría registra motivo + IDs de cierres anulados.
- **FE conectado:** modal con textarea + validación cliente, contador de caracteres, observación actualizada.

### A9 — Stock no enmascara negativos + alerta visible ✅
- `Stock::reconstruir` removió el `max(0, ...)` — los saldos negativos se propagan tal cual.
- Nuevo scope `Stock::scopeNegativo`.
- `StockController::index` ahora expone `stocksNegativosCount` y `es_negativo` por fila.
- **FE conectado:** banner rojo en `Inventario/Stock` cuando hay productos con saldo negativo, fila con badge danger + ⚠.

### A10 — Concurrencia de Venta::generarNumero ✅
- Migración nueva: `UNIQUE(turno_id, numero)` en `ventas`.
- `Venta::generarNumero` simplificado a MAX puro (sin lockForUpdate).
- `VentaService::crear` con retry pattern (hasta 5 intentos) ante `UniqueConstraintViolationException`.
- Test cubre el path de retry.

### A11 — Devolución anulada audita stock negativo resultante ✅
- `Devolucion::anular` captura productos que quedan con cantidad < 0 tras el reverso del restock.
- `auditoria.contexto.genero_stock_negativo` (boolean) + `auditoria.contexto.stocks_negativos` (lista de productos afectados).

### A12 — WhatsappController con permiso + auditoría ✅
- Rutas `whatsapp.aprobacion` y `whatsapp.confirmacion` ahora exigen `permiso:ventas,crear`.
- `AuditoriaService::log('whatsapp.aprobacion_solicitada' | 'whatsapp.confirmacion_enviada', ...)` en cada llamada.

### A13 — Agenda con fecha futura obligatoria + anti-colisión ✅
- `StoreCitaRequest` agregó `after_or_equal:now` a `fecha_hora`.
- Validador post-rules detecta solape considerando `duracion_min`: por `profesional_id` si está asignado, por `local_id` si no.
- Muestra mensaje con el número de la cita conflictiva y su rango horario.

### A14 — Admin sin local_id bloquea POS al cargar ✅
- `VentaController::pos` envía `puedeVender: bool` + `razonNoVender: string|null` al frontend.
- **FE conectado:** banner rojo + botón "Cobrar" deshabilitado con mensaje claro desde el inicio.

### A15 — Cliente General por flag `es_cliente_general` ✅
- Migración nueva: columna `clientes.es_cliente_general` (boolean) + backfill de los existentes con DNI `99999999` + partial unique index (`empresa_id` WHERE flag=true).
- `Cliente::generalDeEmpresa` ahora filtra por la flag.
- `EmpresaController::store` setea la flag al crear el cliente general de una empresa nueva.

---

## ✅ Riesgos medios cerrados (M17–M21)

### M17 — Rate-limit en POST /ventas ✅
- `throttle:60,1` en la ruta `ventas.store`. Permite ráfagas razonables, bloquea bots/loops.

### M18 — Auditoría al borrar entrada/transferencia en borrador ✅
- Ambos controllers ahora hacen snapshot de `{almacen, fecha, productos+cantidades}` ANTES del delete + log a `AuditoriaService`.

### M19 — Paginación en reportes/listados ✅
- `DevolucionController`, `EntradaController`, `SalidaController`, `TransferenciaController` cambiados a `->paginate(25)->withQueryString()`.
- **FE conectado:** los 4 archivos `Index.tsx` consumen `Paginado<T>` con `<button>`s de paginación al pie.

### M20 — Descuento exige concepto + tope por rol ✅
- Migración nueva: columna `roles.max_descuento_porcentaje` (decimal nullable; NULL = sin tope).
- `StoreVentaRequest`:
  - Si `descuento_total > 0` → exige `descuento_concepto_id`.
  - Si el rol del usuario tiene tope, valida `descuento_total / subtotal_bruto * 100 ≤ tope`.
- **FE conectado:** nueva columna "Tope descuento" en `Configuracion/Roles` + input numérico en el modal de crear/editar rol.

### M21 — Anular venta exige motivo en auditoría ✅
- `AnularVentaRequest` (motivo ≥10 chars).
- `VentaService::anular($venta, $user, ?$motivo)` — motivo va a `auditoria.contexto.motivo`.

---

## 🟡 Riesgos medios pendientes

### M16 — Agenda no reserva stock al agendar
**Archivo:** `app/Services/CitaService.php::crear`
- Si la cita incluye productos físicos, entre agendar y completar otra venta puede dejar sin stock.
- Aceptable para servicios; problemático en veterinaria/taller con kits.

**Acción:** decidir si reservar stock al agendar (con liberación al cancelar/no-asistir) o solo avisar al cobrar. Configurable por empresa.

**Costo estimado:** 4-6h. Decisión de producto antes que técnica.

---

### M22 — Idempotencia tolera payload distinto con mismo key
**Archivo:** `app/Services/VentaService.php:37-44`
- Si el frontend reenvía mismo `idempotency_key` con items distintos, devuelve la venta original sin advertir.

**Acción:** calcular fingerprint del payload (hash de items+pagos+total) y guardarlo en `ventas.idempotency_fingerprint`. Si el key existe con otro fingerprint, devolver `409 Conflict`.

**Costo estimado:** 2h. Requiere migración + test bajo escenarios raros. Bajo riesgo real.

---

## 🔵 Validaciones pendientes (código ya hecho)

Todas las validaciones V1, V2, V3 quedaron implícitamente cubiertas por los tests de B7:
- **V1** FK `tipos_metodo_pago` — cubierto por tests de POS (vuelto, sobrepago, métodos por tipo).
- **V2** Refactor IGV — cubierto por `IgvCalculoTest` (gravado/exonerado/descuento prorrateado/tasa configurable).
- **V3** Cita con productos inactivos — cubierto por `CitaTest` (test del payload `tiene_inactivos`).

Aún así, **recomendado validar manualmente en pantalla** una vez antes de exponer a clientes, para confirmar UX:
- [ ] Hacer una venta con efectivo + vuelto en el POS real.
- [ ] Configurar `tasa_igv != 18` en Empresa y vender productos mixtos.
- [ ] Crear cita, desactivar producto, abrir POS desde la cita → debe mostrar banner rojo + bloquear cobro.

---

## 📝 Notas de futuro

- **Componente compartido `<DocumentoTipoSelect>`**: `FormCliente.tsx` y `FormProveedor.tsx` duplican lógica de tipos de documento. Extraer si se siguen agregando forms.
- **Migración del .env y secretos**: documentar el proceso de rotación para que cualquiera del equipo pueda hacerlo sin perder secretos en commits.
- **Decisión arquitectónica registrada**: los enums nativos PG de `tipo_documento`, `estado_entrada`, `tipo_entrada`, `tipo_item`, `tipo_precio` se mantienen porque son conjuntos cerrados y estables. Solo `metodo_pago_tipo` se migró a FK porque el mercado de pagos sí evoluciona (Yape, Plin, BIM, Tunki).

### Hallazgos secundarios para producción (independientes del Pendiente)

🔴 **~12 migraciones faltantes**: las tablas `productos`, `producto_unidades`, `stock`, `almacenes`, `categorias`, `unidades_medida`, `proveedores`, `entradas/_detalle`, `salidas/_detalle`, `transferencias/_detalle`, `cierres_inventario/_items`, `devoluciones/_detalle`, `devolucion_pagos`, `devolucion_motivos` existen en el Postgres local pero **no tienen migración versionada**. Sin éstas, `php artisan migrate` desde cero en otro servidor no recrea el schema completo.

**Mitigación actual:** el deploy a prod se hará vía restore del Postgres local, no vía `migrate:fresh`, así que no es bloqueador para el primer despliegue.

**Deuda pendiente:** generar las migraciones desde el schema actual cuando se quiera levantar staging o un segundo cliente desde cero.

### Errores TypeScript preexistentes (no propios de esta sesión)
`npx tsc --noEmit` reporta 4 errores en commits del 25-mar que el build de producción salta con `npx vite build`:
- `Inventario/Cierres/Create.tsx:132` — `UseFormSubmitOptions`
- `Reportes/Ventas.tsx:348` — `Record<string, unknown>`
- `Turnos/Show.tsx:136,138` — `turno.userCierre.name` typed as `unknown`

No bloquean el deploy pero conviene resolverlos cuando se ataque la deuda de FE.

---

## Resumen ejecutivo

| Severidad | Cantidad inicial | Cerrados | Pendientes |
|-----------|-----------------:|---------:|-----------:|
| 🔴 Bloqueadores  | 2 (B1, B7)            | 1 (B7)              | 1 (B1, al deploy) |
| 🟠 Alto           | 8 (A8–A15)            | 8                   | 0 |
| 🟡 Medio          | 7 (M16–M22)           | 5 (M17–M21)         | 2 (M16, M22) |
| 🔵 Validación     | 3 (V1, V2, V3)        | 3 (cubiertos por tests) | 0 |

**Estado:** listo para producción tras resolver B1 (secretos en el host real) durante el deploy. M16 y M22 son backlog ordinario, no bloquean.

**Métricas de cobertura:** 103 tests de negocio, 304 assertions, ~11s de ejecución sobre Postgres real con rollback automático.
