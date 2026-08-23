ALTER TABLE deuda_pagos
    ADD COLUMN IF NOT EXISTS compensacion_grupo_id VARCHAR(36) NULL,
    ADD COLUMN IF NOT EXISTS compensacion_deuda_id INTEGER NULL;

CREATE INDEX IF NOT EXISTS deuda_pagos_compensacion_grupo_id_idx ON deuda_pagos(compensacion_grupo_id);
CREATE INDEX IF NOT EXISTS deuda_pagos_compensacion_deuda_id_idx ON deuda_pagos(compensacion_deuda_id);
