# Contexto: Ferretería HYC Ferromateriales — Balance Financiero Diario

> Documento de contexto para retomar el trabajo en cualquier sesión.
> Última actualización: 2026-07-06.

---

## 1. El cliente y su Excel

Cliente real: **HYC Ferromateriales SRL** (ferretería, vende ladrillo, fierro, cemento, etc.).
Lleva su control en 2 Excel que están en la raíz del repo:

- `BALANCE FERRETERIA H&C.xlsx` — hoja `BALANCE`: balance patrimonial, empezó semanal (abril) y pasó a **DIARIO**.
- `STOCK DE LADRILLOO 111.xlsx` — kardex semanal de ladrillos: INGRESO / SALIDA / PÉRDIDA por tipo, y `SALDO × "punitario actual"` (precio unitario del día) = stock valorizado.

### Estructura del balance del Excel

```
BALANCE HOY   = Σ(A FAVOR) − Σ(EN CONTRA)
UTILIDAD REAL = (BALANCE HOY − BALANCE AYER) + GASTOS DEL DÍA
```

**A FAVOR (activos):**
- Cuenta BCP Soles, Cuenta BBVA Soles, Efectivo (saldos reales del día, los conciliaba a mano con "OK")
- Stock (inventario) **valorizado a precio del día**
- Deudas por cobrar (ventas a crédito, ~S/80-120k — su activo más grande)
- Adelantos a proveedores (dinero pagado SIN recibir aún el material: Uyustools, Ardiles, Prodac, Cofesa)
- Préstamos otorgados a terceros

**EN CONTRA (pasivos):**
- Proveedores por pagar (paga en abonos)
- Clientes anticipos — **valorizados a precio del día** (si deben material y el ladrillo sube, la deuda vale más)
- Deudas bancarias (DEUDA BCP 1 - 7630, DEUDA BCP 2 - 5557)
- Deudas personales (Jeiner, Jordin, Inversiones JH...) y al personal
- Material comprometido en especie

**GASTOS DEL DÍA:** lista lateral (combustible, SUNAT, personal...) que se suma de vuelta para la utilidad real (un gasto baja el balance pero no es pérdida operativa).

### Exigencias claves del cliente (aprendidas en iteraciones)
1. **Trazabilidad 100%**: nada de montos "de la nada"; todo con fecha, monto, origen y QUIÉN lo registró.
2. **Nada manual sin origen**: el efectivo/cuentas NO se digitan — se calculan de las operaciones; los ajustes requieren motivo y quedan auditados.
3. **Mostrar BRUTO**: en el balance, las cuentas A FAVOR muestran TODO lo ingresado; las salidas van EN CONTRA como "Gastos emitidos (salidas de dinero)" que SÍ suma. El neto sale de la resta (favor − contra) — igual resultado, más transparencia.
4. **Todo por método de pago**: cada canal (Efectivo, Yape, Tarjeta, BCP...) es su propia cuenta. Si un método no tiene cuenta vinculada, el sistema **crea automáticamente** una cuenta con el nombre del método (el dinero de Yape NUNCA cae a Efectivo).
5. **Pago a proveedores = costo ya emitido, NO gasto del día** (el costo nació con la compra/CxP; el pago solo cancela la deuda). Se muestran como "Gastos emitidos" en el card EN CONTRA con separador.
6. Detalles **por día y por concepto** ("las ventas del lunes generaron X"), no documento por documento; y cada fila desplegable para ver el historial.
7. **La cadena debe cuadrar visible**: en deudas, `monto_original − amortizaciones + incrementos = saldo`, y el historial SIEMPRE termina en el evento "Registro de la deuda (saldo inicial)" (agregado en el detalle del balance y en el modal de Deudas). Para deudas pre-sistema, `monto_original` = saldo al implementar el sistema y el préstamo original va en la observación — nunca un original que no cuadre con los pagos registrados.

---

## 2. Módulos implementados (2026-07-04 y 05)

Stack: Laravel 12 + Inertia + React TS + Tailwind + Postgres. Menú/permisos vía tablas `modulos`/`permisos` (seeder `ModulosFinanzasSeeder`, idempotente).

### Finanzas (menú lateral)
| Módulo | Ruta | Qué hace |
|---|---|---|
| Balance diario | `/finanzas/balance` | Foto patrimonial: genera líneas automáticas, líneas manuales auditadas, confirmar = snapshot inmutable ("balance ayer" del día siguiente). Card consolidador abajo (favor/contra/gastos/balance). Cada línea clickeable → modal de detalle. |
| Tesorería | `/finanzas/tesoreria` | Libro de movimientos por cuenta (`cuenta_movimientos`). Cada sol con origen (`ref_tipo`/`ref_id`). "Ajustar saldo" = diferencia con motivo, auditada. |
| Consolidación de caja | `/finanzas/consolidacion` | Segundo conteo del supervisor sobre cada cierre de turno: verifica EFECTIVO **y cada método** (grilla declarado/esperado/contado por línea). Su conteo manda; la diferencia asienta por cuenta. Faltante total → descuento de planilla opcional. |
| Cuentas por cobrar | `/finanzas/cuentas-por-cobrar` | Ventas a crédito + abonos (con "nuevo saldo pendiente" en vivo y tope validado). Detalle = pago inicial del POS + abonos. |
| Cuentas por pagar | `/finanzas/cuentas-por-pagar` | Compras con saldo, abonos parciales (pendiente→parcial→pagado), pagar consumiendo adelanto (no mueve caja). |

**Entradas (compras) — pago al crear**: el form de Nueva Entrada tiene 3 modos (Pendiente / Pago parcial / Pagado) con **líneas de pago múltiples** (método + cuenta + monto, como el POS); crea `entrada_pagos` + egresos de tesorería con la fecha de la entrada y sincroniza `monto_pagado`/`estado_pago` vía `aplicarPago()`. El botón "Pagar" del listado registra un pago trazado por el saldo (ya NO marca sin rastro); revertir a pendiente elimina los pagos y revierte asientos (auditado; bloqueado si consumió adelanto). `update()` de entradas NO toca pagos (track independiente; resincroniza estado contra el nuevo total) y el Edit muestra el pago solo-lectura. `destroy()` de borradores revierte pagos.
| Anticipos de clientes | `/finanzas/anticipos` | Modalidad 'monto' o 'material' (valorizada al precio CONGELADO que el cliente pagó: `valorPasivo()`). Aplicar entregas, devolver/anular. **Despacho con excedente**: si la entrega supera lo pendiente (unidades en material, saldo en monto) se BLOQUEA salvo confirmación (`exceso_a_cxc`) y el exceso se crea como Deuda por_cobrar a nombre del cliente, valorizada a precio del día (el excedente sí es venta nueva) (patrón "Jhon Astonitas"; no mueve tesorería porque salió mercadería, no dinero). En material el monto aplicado se calcula solo (prorrata: cantidad × monto/cantidad original), ya no se digita. |
| Adelantos a proveedores | `/finanzas/adelantos` | Activo a favor; se consume desde CxP o se devuelve. |
| Deudas y préstamos | `/finanzas/deudas` | direccion por_pagar/por_cobrar, tipo bancaria/personal/trabajador/otro. Amortización/incremento con dirección de caja correcta. Caso "moto del trabajador" = deuda por_cobrar tipo trabajador; cuota semanal entra sola a caja. |
| Descuentos de planilla | `/finanzas/descuentos-planilla` | Faltantes de caja u otros cargos por trabajador; pendiente→aplicado/anulado. NO mueve tesorería. **En el balance**: línea A FAVOR "Por descontar en planilla (faltantes y cargos)" = Σ pendientes (el derecho a recuperar del sueldo; el faltante en sí ya golpeó EN CONTRA vía gastos emitidos). Al APLICAR el descuento la línea baja — ese es su movimiento en el balance. Detalle clickeable (categoria `planilla_descuento`): pendientes y aplicados con trabajador/estado/fecha. |

### POS
- Toggle **"Venta a crédito"**: exige cliente identificado (no Cliente General), pago inicial opcional/parcial, muestra "Saldo a crédito" en vivo. `ventas.es_credito/monto_pagado/saldo_pendiente/fecha_vencimiento`.
- Gastos: campo **"Se paga con"** (cuenta; default Efectivo).

### Tesorería — la única fuente de verdad del dinero
`TesoreriaService` registra ingreso/egreso con origen desde TODOS los flujos:
ventas (neto de vuelto), anulación (revierte), abonos CxC, pagos CxP, anticipos (+devolución), adelantos (+devolución), cuotas de deudas (dirección según por_pagar/por_cobrar × amortización/incremento), gastos (store/destroy), reembolsos de devoluciones (completar/anular), sobrante/faltante de cierre de turno (`cierre_turno`) o de consolidación (`turno_consolidacion`), ajustes manuales.
`resolverCuenta()`: pivot elegido → cuentas del método → tipo efectivo → **auto-crea cuenta con nombre del método** y la vincula.
Reabrir turno revierte los asientos del cierre/consolidación.

### Configuración
- `empresas.requiere_consolidacion_caja` (checkbox en Configuración→Empresas): ON = el balance toma el conteo del CONSOLIDADOR (el cierre no asienta hasta consolidar); OFF = el cierre de la cajera asienta directo.
- El consolidador VE lo declarado (decisión del cliente, no conteo ciego).

### Balance diario — detalle técnico
- `BalanceDiarioService::generar()`: regenera líneas automáticas (es_manual=false) y purga legado; confirmado = inmutable.
- Cuentas A FAVOR en **BRUTO** (Σ ingresos hasta la fecha); EN CONTRA los `gastos_emitidos` van **DESGLOSADOS POR CUENTA** ("Gastos emitidos — Efectivo/BCP/Tarjeta...", una línea por cuenta con egresos > 0, ref cuenta, detalle filtrado por esa cuenta; balances confirmados viejos conservan la línea agregada). Neto = mismo saldo real.
- Sección **"Cuánto tengo por cuenta"** (cards bajo el resumen): saldo NETO real de cada cuenta a la fecha del balance (`TesoreriaService::saldo(cuenta, fecha)`), rojo si es negativo — responde "¿cuánto tengo en efectivo/tarjetas/bancos?" sin restar bruto − emitidos a mano.
- El panel **"Gastos del día"** muestra la cuenta de cada gasto (ícono efectivo/banco); el detalle de "Gastos emitidos — {cuenta}" lleva card "Salidas de la cuenta: {nombre}" y columna "Desde cuenta". Regla general: **toda cifra EN CONTRA debe poder responder "¿de qué cuenta/método salió?"**.
- Stock = Σ stock.cantidad × productos.precio_costo (precio del día).
- CxC = Σ saldo_pendiente; anticipos = Σ valorPasivo(); deudas línea por línea.
- **Sección "Movimientos del día"** en el balance (entre favor/contra y el consolidado): todos los `cuenta_movimientos` de ESA fecha agrupados por concepto (Ventas cobradas, Cobros CxC, Anticipos, Gastos, Pagos a proveedores, Consolidación de caja, Cuotas...), cada grupo desplegable con sus operaciones (descripción, cuenta, usuario, monto). Responde "¿por qué se movió el balance hoy?" — las líneas del balance son acumulados en bruto y una venta puntual no se distingue ahí.
- Detalle de líneas: endpoint `finanzas/balance/{fecha}/detalle/{categoria}` — respuesta NORMALIZADA `{tipo:'grupos', cards, itemCols (columnas a medida por categoría), montoLabel, grupos[{fecha, items[{...campos, sub, user, historial[]}]}]}`. Rango default 3 meses (pendiente: "fecha de corte" a definir con el cliente).

### Kit UI (reglas de diseño de modales)
- `Components/UI/Callout` — única caja de feedback (info/success/danger/warning/neutral, icono lucide).
- `Components/UI/StatGrid` — cifras clave SIEMPRE arriba del modal (card destacada para la protagonista).
- `Components/UI/Timeline` — historiales con columnas FIJAS simétricas (punto | fecha 78px | tipo badge 100px | detalle | usuario chip | monto 96px derecha).
- `Components/UI/Collapse` — TODO desplegable anima 300ms (grid-rows 0fr→1fr); chevrons rotan.
- `Components/Finanzas/DetalleAgrupado` — modal normalizado: cards + buscador propio + tabla Fecha/Operaciones/Monto desplegable → sub-tabla con columnas a medida + botón "Historial (N)" **colapsado por defecto**.
- Reglas: modales grandes (`size="5xl"`, Modal soporta hasta 5xl), acciones de tabla SOLO íconos lucide con tooltip `title` (nada de botones con texto ni emojis/caracteres de teclado), montos con `money()`, fechas es-PE.
- **Totales/cifras NUNCA en `actions` del PageHeader** (a la derecha del título van solo botones y controles). Los totales van DEBAJO del título como cards: `<StatGrid size="lg" cols="grid-cols-2 sm:grid-cols-3 lg:grid-cols-4" stats={[...]} />` con `destacado: true`, `icon` (lucide ~19px) y `sub` (línea de contexto) en cada stat (patrón aplicado en Adelantos, Anticipos, CxC, CxP y Deudas; Tesorería tiene sus cards de cuentas abajo).
- **PageHeader con `icon`**: cada página de Finanzas pasa su ícono lucide (~22px) → chip degradado azul junto al título (Balance=Scale, Tesorería=Landmark, Consolidación=ShieldCheck, CxC=HandCoins, CxP=ReceiptText, Anticipos=PiggyBank, Adelantos=Handshake, Deudas=CreditCard, DescuentosPlanilla=UserMinus).
- **Columnas de dinero SIEMPRE `align: 'right'`** en `Table` (el th/td se alinean y el tbody usa `tabular-nums` para que los dígitos encajen). El color semántico lo lleva el chip del ícono; el valor de las stat-cards usa tinte profundo (`color-mix` 80% con el ink), no el color plano.
- Micro-animación de entrada: clase `.vp-fade-up` (app.css, respeta `prefers-reduced-motion`) con `animationDelay` escalonado (~50-60ms por card) en stat-cards `lg` y cards de cuentas de Tesorería.

---

## 3. Gotchas / decisiones técnicas
- Fechas: casts `date:Y-m-d` en modelos nuevos (la serialización con hora rompía rutas `/balance/{fecha}` → 404) y `.slice(0,10)` defensivo en el front.
- **Zona horaria (bug del "día siguiente")**: NUNCA `new Date().toISOString()` para fechas de formularios — es UTC y en Lima (UTC−5) desde las 7 pm ya es "mañana". Usar `hoyLocal()`/`fechaLocal()`/`ahoraLocalInput()` de `@/lib/fechas` (aplicado en Finanzas, Inventario, Gastos, Clientes, Agenda). Backend: `config/app.php` → `'timezone' => env('APP_TIMEZONE', 'America/Lima')` (antes UTC; el `hoy` del Balance y todos los `now()` cambiaban de día a las 7 pm). Ojo: timestamps históricos guardados en UTC ahora se leen como hora Lima (hora +5 en registros viejos de la BD dev).
- `Select`/`SearchableSelect` comparan con `===` estricto → los values de options SIEMPRE `String(id)`.
- No usar PowerShell `Get-Content`+`Set-Content` para reensamblar archivos (rompe UTF-8/acentos); usar python con encoding utf-8.
- La tabla pivote `cuenta_metodo_pago` NO tiene timestamps.
- **`pagos.*.cuenta_metodo_pago_id` (POS) es el id del PIVOTE**, no el de la cuenta. `MetodoPago::cuentas()` lleva `withPivot('id')` y el select del POS usa `cuenta.pivot.id` (antes mandaba `cuenta.id`: funcionaba solo cuando los ids coincidían de casualidad).
- Tests de Auth/Profile (Breeze) fallan desde antes (UserFactory sin empresa_id) — no son regresiones.
- Migraciones base (productos, entradas, etc.) NO están en el repo (BD dev ya creada); las nuestras empiezan en `2026_07_04_*` y `2026_07_05_*`.
- Backfill histórico: `TesoreriaBackfillSeeder` (borra movimientos con origen ≠ 'ajuste' y regenera).

## 4. Datos de prueba (BD dev)
- Usuarios: `jesus@gmail.com` / `12345678` (admin) · `cajera@gmail.com` / `12345678` (cajera). Empresa 1, local "Tienda Chiclayo".
- **Demo ferretería completa**: `php artisan db:seed --class=DemoFerreteriaSeeder` (DESTRUCTIVO e idempotente: borra ventas/turnos/gastos/finanzas y reconstruye; usa fechas relativas ayer/hoy del día en que se corre). Recrea el Excel real: saldos iniciales BCP 44,915.86 / BBVA 4,693.54 / Efectivo 8,606.11; 12 productos de ferretería con stock valorizado ~S/226k (boutique desactivada); deudas DEUDA BCP 1-7630 (S/6,173.81 tras cuota de hoy, igual al Excel), BCP 2-5557 (32,546.43), JORDIN HERRERA (30k), sueldos personal (2k); préstamos otorgados JHON ASTONITAS (245.05) y moto trabajador; CxP Ferronor/Ardiles/Cofesa/Uyustools (S/94,900); adelantos Uyustools+Prodac (7,300); anticipos valorizados a precio del día (35,200 = 25,000 ladrillos×1.10 + montos); CxC 5 ventas a crédito (68,545.40); gastos clasificados con nombres reales del Excel (Combustible FUSO, Mecánico y fajas, Energía 5 recibos, Líneas de celulares...); faltante de caja ayer S/25.50 → descuento de planilla pendiente + herramienta dañada S/80. **Balance de AYER queda CONFIRMADO y el de HOY en borrador** → la demo es: abrir Balance hoy, revisar detalle por línea, y confirmar en vivo comparando contra ayer (diferencia y utilidad real ya calculadas). El turno de HOY queda ABIERTO a nombre de la Cajera en la Caja Principal (para el POS: loguearse como cajera, o como admin abrir turno en "Caja 2 — Mostrador", creada por el seeder para eso).
- Correr servidores: `php artisan serve` + `npm run dev`.
- Pruebas E2E: se hicieron con scripts transaccionales (BEGIN...ROLLBACK) llamando controladores reales — patrón útil: `Request::create` + `setUserResolver` + `setLaravelSession`; FormRequests con `setContainer`+`setRedirector`+route param+`validateResolved()`.

## 5. Pendientes / próximos pasos
- [ ] **Fecha de corte** por empresa (inicio de operaciones): congela historia previa en un saldo inicial; acota los "3 meses" por defecto de los detalles. ← EL MÁS IMPORTANTE antes de datos reales.
- [ ] **Antes de cada demo**: re-correr `DemoFerreteriaSeeder` ese mismo día (las fechas ayer/hoy son relativas al día en que se corre).
- [ ] Decidir qué hacer con **Tarjeta en −S/ 8,000** (pago de prueba a COFESA desde cuenta sin fondos; el desglose por cuenta lo delata — sirve de ejemplo o se corrige moviendo el asiento a BCP).
- [ ] **Vínculo descuento de planilla aplicado ↔ deuda "Sueldos pendientes"** (amortizar al aplicar) — validar el modelado con el cliente primero.
- [ ] Reporte de faltantes por cajera (los datos ya existen: consolidaciones + descuentos planilla).
- [ ] Valorizar la línea de stock del balance con opción precio_costo vs precio_venta (hoy: precio_costo) — decisión del cliente.
- [ ] Kardex de ladrillo estilo Excel (ingreso/salida/pérdida semanal por producto) — fase futura.
- [ ] Normalizar íconos de teclado (✓, ⚠) en páginas antiguas (Agenda, Turnos, Stock, Devoluciones).
- [x] Cliente vinculó métodos a sus cuentas reales (Yape→BCP, Transferencia→BCP/BBVA) en Configuración → Métodos de pago.

## 6. Auditoría de trazabilidad
`php artisan tinker database/scripts/auditoria_balance.php` — 65 verificaciones de punta a punta (solo lectura): aritmética del balance, cada línea vs su fuente de verdad, tesorería sin movimientos huérfanos ni sin origen, toda operación con su asiento, cadenas internas (original − pagos = saldo en deudas/ventas/entradas/anticipos/adelantos), saldos por cuenta e inmutabilidad de confirmados. Correrla antes de cada demo. OJO: la constante `$FECHA` del script apunta a la fecha del balance a auditar.
