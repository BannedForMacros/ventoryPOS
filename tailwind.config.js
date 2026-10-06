import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    theme: {
        extend: {
            // Monitores de caja chicos (1366×768 → ~657 px útiles): el POS se compacta.
            // Solo en pantallas anchas: en un celular los botones siguen grandes para el dedo.
            screens: {
                bajo: { raw: '(max-height: 760px) and (min-width: 1024px)' },
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                // Cifras y títulos de sección en reportes: numerales anchos y legibles.
                display: ['Sora', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
