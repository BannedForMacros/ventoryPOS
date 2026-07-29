-- ============================================================================
--  ventoryPOS · Facturación electrónica — conexión con el emisor POR EMPRESA
--  Fecha: 2026-07-29
--
--  QUÉ HACE: crea UNA TABLA NUEVA (`facturacion_conexiones`) y registra el
--  submódulo de menú "Facturación Electrónica". No modifica ni borra nada
--  existente. Es seguro ejecutarlo con el sistema en marcha y es idempotente
--  (IF NOT EXISTS + INSERT ... WHERE NOT EXISTS), así que repetirlo no hace daño.
--
--  ─── EL FALLO QUE ESTO CIERRA ───────────────────────────────────────────────
--
--  Hasta ahora la conexión con el emisor vivía en el `.env` de la instalación:
--  FACTURAMAC_ENABLED / FACTURAMAC_URL / FACTURAMAC_TOKEN. Eso es UN token para
--  TODA la instalación, y un token de FacturaMac identifica a UNA empresa
--  emisora (de él se deducen el RUC, el certificado y la clave SOL).
--
--  Esta instalación sirve a dos contribuyentes distintos:
--      #1     20612345678  MacSoft E.I.R.L.
--      #1097  20600134648  HYC FERROMATERIALES SRL
--
--  Con un solo token, si HYC vendía, su boleta salía firmada con el RUC de
--  MacSoft: facturar a nombre de otro contribuyente. No es un problema de
--  comodidad y no tenía arreglo en el `.env`, porque no hay dónde poner el
--  segundo token. Por eso la conexión pasa a ser una fila POR EMPRESA.
--
--  La URL del emisor NO está aquí a propósito: FacturaMac es un único servicio
--  para toda la instalación, así que sigue en config/facturamac.php leyendo
--  FACTURAMAC_URL. Eso es "dónde vive el servicio", no configuración del usuario.
--
--  ─── ORDEN DE DESPLIEGUE ────────────────────────────────────────────────────
--
--  Da igual si ejecutas esto antes o después de subir el código: el código
--  comprueba con `Schema::hasTable()` que la tabla exista y, si no está, se
--  comporta como si la facturación electrónica estuviera APAGADA (que es
--  exactamente lo que ocurre: sin fila no hay token, sin token no se emite).
--  Aun así se recomienda ejecutarlo ANTES, para poder conectar desde la
--  interfaz en cuanto el despliegue termine.
--
--  ─── DESPUÉS DE EJECUTARLO ──────────────────────────────────────────────────
--
--  NO hay que tocar el `.env` ni la base a mano. Todo se hace por interfaz:
--      Configuración → Facturación Electrónica → pegar el código de vinculación
--      que da FacturaMac → "Conectar".
--  Las claves FACTURAMAC_ENABLED y FACTURAMAC_TOKEN del `.env` ya NO se leen;
--  pueden quedarse ahí sin efecto o borrarse cuando se quiera.
-- ============================================================================

BEGIN;

-- ── 1. Conexión con el emisor, una fila por empresa ─────────────────────────
--
-- UNIQUE(empresa_id): una empresa = una conexión = un emisor. Es la barrera de
-- base de datos que impide que una empresa acabe con dos tokens y emita por el
-- que le toque al azar.
--
-- `token` guarda el personal access token de FacturaMac CIFRADO por la
-- aplicación (cast `encrypted` de Laravel, AES-256-CBC con APP_KEY). Por eso es
-- TEXT y no VARCHAR(100): el ciphertext en base64 abulta bastante más que el
-- token en claro. Nadie con acceso de solo lectura a la base puede emitir con
-- él, y un volcado de la base no filtra la capacidad de firmar comprobantes.
--
-- `ruc_emisor` es el RUC que FacturaMac declaró al vincular. Se guarda para
-- poder MOSTRARLO y para poder re-verificar que sigue coincidiendo con el RUC
-- de la empresa: es la guarda contra el fallo de arriba.
--
-- `modo` es el del emisor: 'produccion' | 'beta' | 'simulacion' | 'desactivado'.
-- Se guarda como copia informativa del último dato conocido; la fuente de
-- verdad sigue siendo GET /api/v1/configuracion.
--
-- `emision_activa` es el interruptor de ventoryPOS, y es LO QUE SUSTITUYE a
-- FACTURAMAC_ENABLED. Nace en FALSE: emitir mal es irreversible, así que cada
-- empresa se enciende a conciencia y nunca por arrastrar un default peligroso.
-- Solo puede ponerse en TRUE si `modo = 'produccion'` (lo aplica la aplicación).
CREATE TABLE IF NOT EXISTS public.facturacion_conexiones (
    id                  BIGSERIAL PRIMARY KEY,
    empresa_id          BIGINT       NOT NULL,
    token               TEXT         NOT NULL,              -- cifrado por la app
    ruc_emisor          VARCHAR(11)  NOT NULL,
    razon_social_emisor VARCHAR(255),
    modo                VARCHAR(20)  NOT NULL DEFAULT 'desactivado',
    emision_activa      BOOLEAN      NOT NULL DEFAULT FALSE,
    instalacion         VARCHAR(120),                       -- nombre libre con el que se vinculó
    conectado_at        TIMESTAMP(0) WITHOUT TIME ZONE,
    diagnostico_at      TIMESTAMP(0) WITHOUT TIME ZONE,
    diagnostico_ok      BOOLEAN,
    diagnostico_mensaje TEXT,
    created_at          TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at          TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT facturacion_conexiones_empresa_id_unique UNIQUE (empresa_id),
    CONSTRAINT facturacion_conexiones_empresa_id_foreign
        FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE
);

COMMENT ON TABLE public.facturacion_conexiones IS
    'Conexión con el emisor (FacturaMac) POR EMPRESA. Sustituye a FACTURAMAC_ENABLED/FACTURAMAC_TOKEN del .env.';

COMMENT ON COLUMN public.facturacion_conexiones.token IS
    'Personal access token de FacturaMac, CIFRADO con APP_KEY (cast encrypted de Laravel). Nunca en claro.';

COMMENT ON COLUMN public.facturacion_conexiones.modo IS
    'produccion | beta | simulacion | desactivado — modo declarado por el emisor.';

COMMENT ON COLUMN public.facturacion_conexiones.emision_activa IS
    'Interruptor de ventoryPOS: ¿esta empresa emite? Solo puede activarse con modo = produccion.';

-- ── 2. Submódulo de menú "Facturación Electrónica" ──────────────────────────
--
-- Sin esta fila la pantalla existe y responde, pero no aparece en el menú
-- lateral, y el requisito es que TODO se haga por interfaz: si no hay entrada
-- de menú, hay que escribir la URL a mano, que es justo lo que no queremos.
--
-- Cuelga del módulo padre 'configuracion'. `orden` = 7 para dejarla al final
-- del bloque de Configuración, después de "Permisos por Rol".
--
-- Los roles con `es_admin = true` la ven de inmediato (User::tienePermiso hace
-- bypass). Para el resto de roles se otorga desde Configuración → Permisos.
INSERT INTO public.modulos (padre_id, nombre, slug, icono, ruta, orden, activo, created_at, updated_at)
SELECT p.id,
       'Facturación Electrónica',
       'configuracion.facturacion',
       'FileText',
       '/configuracion/facturacion',
       7,
       TRUE,
       NOW(),
       NOW()
FROM public.modulos p
WHERE p.slug = 'configuracion'
  AND NOT EXISTS (
      SELECT 1 FROM public.modulos m WHERE m.slug = 'configuracion.facturacion'
  );

COMMIT;

-- ── Verificación (ejecútalo después) ────────────────────────────────────────
-- SELECT to_regclass('public.facturacion_conexiones') IS NOT NULL AS tabla,
--        EXISTS (SELECT 1 FROM public.modulos WHERE slug = 'configuracion.facturacion') AS menu;
--
-- Ambas deben devolver `t`.
--
-- Y para ver el estado de cada empresa (el token NO se puede leer: está cifrado):
-- SELECT e.id, e.ruc, e.razon_social, c.ruc_emisor, c.modo, c.emision_activa, c.conectado_at
--   FROM public.empresas e
--   LEFT JOIN public.facturacion_conexiones c ON c.empresa_id = e.id
--  ORDER BY e.id;
