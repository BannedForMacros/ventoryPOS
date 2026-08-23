DROP INDEX IF EXISTS deuda_pagos_compensacion_grupo_id_idx;
DROP INDEX IF EXISTS deuda_pagos_compensacion_deuda_id_idx;

ALTER TABLE deuda_pagos
    DROP COLUMN IF EXISTS compensacion_grupo_id,
    DROP COLUMN IF EXISTS compensacion_deuda_id;
