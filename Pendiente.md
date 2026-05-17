# Pendientes — ventoryPOS

Hoja de ruta de QA. Lo que ya está hecho en sesiones previas no aparece aquí.
Cada ítem incluye archivo y nivel de severidad por color:

- 🔴 **Bloqueador** — no salir a producción sin esto
- 🟠 **Alto** — atender en piloto o primeras semanas
- 🟡 **Medio** — atender en backlog ordinario
- 🔵 **Validación pendiente** — código ya escrito, falta probar en runtime

---

## 🔴 Bloqueadores pendientes

### B1 — Secretos del repo y modo desarrollo
**Archivo:** `.env`
- `APP_ENV=local` y `APP_DEBUG=true` → en producción debe ser `production` / `false`.
- `APP_KEY` versionada → regenerar con `php artisan key:generate` e invalidar la actual.
- `DECOLECTA_TOKEN=sk_2512.os1jqrKBkTYs2xDPATzIkaRAevshrjBe` versionado → **rotar inmediatamente** con Decolecta.
- `DB_PASSWORD=postgres` (default trivial) → cambiar antes de exponer.

**Acción:** quitar `.env` del repo (agregar a `.gitignore` si no está), regenerar key, rotar token, usar variables de entorno reales del host.

---

### B7 — Ausencia total de tests del negocio
**Archivos afectados:** `tests/Feature/*` (solo hay scaffolding de Auth)

**Falta cubrir:**
- POS: crear venta exitosa, anular, idempotencia, sobrepago no-efectivo bloqueado.
- Stock: descuento por venta, restock por devolución, `InsufficientStockException`.
- Turnos: abrir/cerrar con/sin diferencia, reabrir.
- Devoluciones: total, parcial, con/sin restock, motivo que fuerza merma.
- Agenda: agendar + cobrar desde POS, cita con productos inactivos.
- IGV: separación gravada/exonerada con descuento prorrateado.

**Mínimo recomendado:** 1 feature test por flujo crítico antes de exponer a clientes reales.

---

## 🟠 Riesgos altos pendientes

### A8 — Reabrir turno deja CierreInventario huérfano
**Archivo:** `app/Http/Controllers/Turnos/TurnoController.php:120-156`
- `reabrir()` borra `arqueo` y `arqueoMetodos`, pero **no toca el `CierreInventario` confirmado** asociado.
- Reabres → cierras de nuevo → otro cierre confirmado y el viejo queda colgado.
- Falta razón obligatoria al reabrir (auditoría débil).

**Acción:** anular/desconectar el cierre de inventario al reabrir; exigir `motivo` obligatorio; loguear en auditoría.

---

### A9 — `Stock::reconstruir` enmascara stock negativo
**Archivo:** `app/Models/Stock.php:247`
```php
$stock->cantidad = max(0, round($cantidad, 4));  // 👈 trunca negativos
```
- Si hubo merma grande, queda en 0 cuando debería ser negativo y disparar alerta.
- El admin no se entera de la inconsistencia.

**Acción:** permitir cantidad negativa y mostrar alerta en el módulo Stock cuando aparezca.

---

### A10 — Concurrencia de `Venta::generarNumero`
**Archivo:** `app/Models/Venta.php:70-86`
- `lockForUpdate()` sobre subquery no garantiza bloqueo confiable en Postgres.
- Dos POS abriendo a la vez pueden colisionar pese al lock.

**Acción:** usar secuencia Postgres por `turno_id` o `UNIQUE(turno_id, numero) + retry`.

---

### A11 — Devoluciones — anulación con stock negativo silencioso
**Archivo:** `app/Models/Devolucion.php:138-175`
- Al anular devolución completada con restock, hace `Stock::ajustar(..., permitirNegativo: true)`.
- Comentario indica "no bloqueamos por consistencia contable" — pero no hay alerta visible para admin.

**Acción:** registrar el stock negativo resultante en una tabla de "alertas de stock" o flag y mostrarlo en el dashboard.

---

### A12 — `WhatsappController::urlAprobacion` sin permiso explícito
**Archivo:** `app/Http/Controllers/Ventas/WhatsappController.php`
- Solo `auth+verified`, sin `permiso:`.
- Falta auditoría del intento (quién pidió aprobación de qué descuento).

**Acción:** agregar middleware `permiso:descuentos.aprobacion` o equivalente; loguear vía `AuditoriaService`.

---

### A13 — Agenda sin validación anti-colisión
**Archivo:** `app/Http/Requests/Agenda/StoreCitaRequest.php`
- No valida solape de citas para el mismo `profesional_id` o `local_id`.
- Veterinaria puede agendar 3 cirugías a las 10:00 sin advertencia.
- Tampoco valida que `fecha_hora` sea futura.

**Acción:** regla `after_or_equal:now` + validador post-rules que verifica solape considerando `duracion_min`.

---

### A14 — Admin global sin `local_id` no puede vender (UX)
**Archivo:** `app/Services/LocalScopeService.php:165-194`, `app/Http/Controllers/Ventas/VentaController.php::pos`
- En modo `central_y_local`, usuario sin `local_id` recibe `abort(422)` al intentar registrar venta.
- Carga el POS, llena el carrito, intenta cobrar → error al final.

**Acción:** detectar al cargar `/pos` y bloquear el botón con mensaje claro: "Selecciona un local para operar el POS".

---

### A15 — Cliente General codificado como DNI `99999999`
**Archivos:** `app/Models/Cliente.php:62-64`, `app/Http/Controllers/Configuracion/EmpresaController.php:30-37`, `app/Services/VentaService.php:53` (`Cliente::generalDeEmpresa`)
- Si en algún país (o regla SUNAT) este DNI deja de ser válido, el sistema rompe.

**Acción:** agregar columna `es_cliente_general` booleana o `tipo` y migrar la lógica. Eliminar `Cliente::generalDeEmpresa` que filtra por número mágico.

---

## 🟡 Riesgos medios pendientes

### M16 — Agenda no reserva stock al agendar
**Archivo:** `app/Services/CitaService.php::crear`
- Si la cita incluye productos físicos, entre agendar y completar otra venta puede dejar sin stock.
- Aceptable para servicios; problemático en veterinaria/taller con kits.

**Acción:** decidir si reservar stock al agendar (con liberación al cancelar/no-asistir) o solo avisar al cobrar. Configurable por empresa.

---

### M17 — POS sin rate-limit por usuario
**Archivo:** `routes/web.php:248-253` (rutas `ventas.*`)
- Solo `permiso:ventas,crear` y CSRF estándar.
- Atacante autenticado puede generar miles de ventas.

**Acción:** `throttle:60,1` en `ventas.store` (o por turno).

---

### M18 — Borrar transferencia/entrada "borrador" sin auditoría
**Archivos:** `app/Http/Controllers/Inventario/TransferenciaController.php::destroy`, `app/Http/Controllers/Inventario/EntradaController.php::destroy`
- Borra detalles y cabecera sin `AuditoriaService::log`.

**Acción:** loguear destrucción con snapshot mínimo (productos, cantidades, total).

---

### M19 — Reportes y listados sin paginación
**Archivos:**
- `app/Http/Controllers/Devoluciones/DevolucionController.php::index` (línea 36, usa `->get()`)
- `app/Http/Controllers/Inventario/EntradaController.php::index`
- `app/Http/Controllers/Inventario/SalidaController.php::index`
- `app/Http/Controllers/Inventario/TransferenciaController.php::index`

**Problema:** con meses de operación los `->get()` cargan miles de registros en memoria.

**Acción:** convertir a `->paginate(25)` con `->withQueryString()` y ajustar el frontend.

---

### M20 — Descuento sin tope ni razón obligatoria
**Archivo:** `app/Http/Requests/Ventas/StoreVentaRequest.php`
- `descuento_total` se acepta hasta el total sin obligar `descuento_concepto_id`.
- Cajero puede aplicar 100% de descuento sin justificación.

**Acción:** exigir `descuento_concepto_id` cuando `descuento_total > 0`; tope porcentual configurable por rol/empresa.

---

### M21 — Anular venta no audita motivo
**Archivo:** `app/Http/Controllers/Ventas/VentaController.php::anular` (línea 181-192)
- No exige razón ni la guarda.
- Solo registra `venta.anulada` con datos básicos.

**Acción:** exigir `motivo` (string, max:500) y guardarlo en `auditorias.contexto`.

---

### M22 — Idempotencia tolera payload distinto con mismo key
**Archivo:** `app/Services/VentaService.php:37-44`
- Si el frontend reenvía mismo `idempotency_key` con items distintos, devuelve la venta original sin advertir.

**Acción:** calcular fingerprint del payload (hash de items+pagos+total) y guardarlo en `ventas.idempotency_fingerprint`. Si el key existe con otro fingerprint, devolver `409 Conflict`.

---

## 🔵 Validaciones pendientes (código ya hecho)

### V1 — FK #1 `tipos_metodo_pago` — validar en runtime
Después del refactor de la sesión 14-15/may, probar manualmente:

1. **Configuración → Métodos de pago**
   - [ ] Crear método nuevo "SIP" con tipo='otro' y `admite_vuelto=true`.
   - [ ] Editar uno existente y cambiar `admite_vuelto`.
   - [ ] Verificar que aparecen todos los tipos del catálogo en el dropdown.

2. **POS**
   - [ ] Hacer una venta con efectivo + vuelto.
   - [ ] Hacer una venta solo con tarjeta exacta.
   - [ ] Intentar sobrepagar con tarjeta → debe bloquear.
   - [ ] Pago mixto tarjeta 80 + efectivo 30 sobre total 100 → debe aceptar con vuelto 10.

3. **Turnos**
   - [ ] Abrir turno, hacer ventas con varios métodos, cerrar.
   - [ ] Verificar que el arqueo muestra solo métodos no-efectivo.
   - [ ] Verificar que `calcularMontoEsperado` suma correctamente las ventas en efectivo.

4. **Reportes / Dashboard**
   - [ ] Dashboard admin: el bloque "Ventas por método de pago" debe mostrar slugs correctos.
   - [ ] Reporte de ventas: filtro por método debe funcionar.

---

### V2 — Refactor IGV (sesión 12/may)
Validar en runtime que el cálculo separa gravado/exonerado:

1. **Empresa con productos mixtos**
   - [ ] Crear producto A con `incluye_igv=true`, producto B con `incluye_igv=false`.
   - [ ] Vender ambos en la misma boleta. Total debe coincidir con el cálculo del POS.
   - [ ] Aplicar descuento global y verificar prorrateo entre bases.

2. **Empresa con `tasa_igv != 18`**
   - [ ] Cambiar `tasa_igv` a 10 en Configuración → Empresa.
   - [ ] Hacer venta. El IGV debe calcularse al 10%.
   - [ ] Cambiar a 0 (exenta). El IGV debe quedar en 0.

---

### V3 — Cita prellenada con productos inactivos (sesión 13/may)
- [ ] Crear cita con un producto X.
- [ ] Desactivar producto X.
- [ ] Abrir POS desde la cita → debe mostrar toast + banner rojo + bloquear botón "Cobrar".
- [ ] Eliminar el ítem inactivo del carrito → botón se desbloquea.

---

## 📝 Notas de futuro

- **Componente compartido `<DocumentoTipoSelect>`**: `FormCliente.tsx` y `FormProveedor.tsx` duplican lógica de tipos de documento. Extraer si se siguen agregando forms.
- **Tests de integración**: cuando se aborde B7, considerar usar Pest + base de datos real (no mocks) para que los flujos cuenten.
- **Migración del .env y secretos**: documentar el proceso de rotación para que cualquiera del equipo pueda hacerlo sin perder secretos en commits.
- **Decisión arquitectónica registrada**: los enums nativos PG de `tipo_documento`, `estado_entrada`, `tipo_entrada`, `tipo_item`, `tipo_precio` se mantienen porque son conjuntos cerrados y estables. Solo `metodo_pago_tipo` se migró a FK porque el mercado de pagos sí evoluciona (Yape, Plin, BIM, Tunki).

---

## Resumen ejecutivo

| Severidad | Cantidad | Estado |
|-----------|---------:|--------|
| 🔴 Bloqueadores  | 2 (B1, B7)         | Pendiente |
| 🟠 Alto           | 8 (A8–A15)         | Pendiente |
| 🟡 Medio          | 7 (M16–M22)        | Pendiente |
| 🔵 Validación     | 3 (V1, V2, V3)     | Por probar en pantalla |

**No salir a producción sin resolver B1 y B7.** El resto se puede atender en piloto controlado.
