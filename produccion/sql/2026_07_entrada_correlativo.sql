-- Correlativo interno de entradas (E-AAAAMMDD-NNN). Aditivo, no toca numero_documento.
-- Tras aplicar: php artisan entradas:correlativos  (rellena las entradas existentes).
ALTER TABLE entradas ADD COLUMN IF NOT EXISTS correlativo varchar(30);
CREATE UNIQUE INDEX IF NOT EXISTS entradas_empresa_correlativo_uq
  ON entradas (empresa_id, correlativo) WHERE correlativo IS NOT NULL;
