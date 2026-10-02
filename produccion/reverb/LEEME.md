# Tiempo real (Laravel Reverb) en producción

1. `.env` del servidor (claves nuevas, NO las de local):

       BROADCAST_CONNECTION=reverb
       REVERB_APP_ID=ventorypos
       REVERB_APP_KEY=<openssl rand -hex 10>
       REVERB_APP_SECRET=<openssl rand -hex 16>
       REVERB_HOST=127.0.0.1
       REVERB_PORT=8081
       REVERB_SCHEME=http
       REVERB_SERVER_PORT=8081
       VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
       VITE_REVERB_HOST=ventorypos.macsoftperu.com
       VITE_REVERB_PORT=443
       VITE_REVERB_SCHEME=https

   (el backend habla con Reverb por 127.0.0.1:8081; el navegador entra por
   https://ventorypos.macsoftperu.com/app → nginx → 8081)

2. Servicio: `reverb-ventorypos.service` (ver el archivo).
3. nginx: `nginx-location-app.conf` → `nginx -t && systemctl reload nginx`.
4. Deploy (`/usr/local/bin/deploy`): agregar `php artisan reverb:restart`
   junto a `queue:restart` (Reverb queda corriendo con el código cargado).
5. El build de Vite lee las VITE_REVERB_* del .env: el deploy ya corre
   `npm run build` después de actualizar el .env.

Si Reverb se cae, ventoryPOS funciona igual (sin actualización automática).
