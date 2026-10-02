import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/react';
import toast from 'react-hot-toast';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Rechazos del servidor (abort 422) llegan como el error "aviso": se muestran
// en cualquier pantalla. El id = mensaje evita duplicarlo si la pantalla
// también muestra su primer error.
router.on('error', (event) => {
    const aviso = (event.detail.errors as Record<string, string>).aviso;
    if (aviso) toast.error(aviso, { id: aviso });
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: false,
});
