import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans:    ['DM Sans', ...defaultTheme.fontFamily.sans],
                display: ['Instrument Serif', 'Georgia', 'serif'],
                syne:    ['Syne', 'sans-serif'],
            },
            colors: {
                // ── Dark app theme ──────────────────────────────────────────
                app: {
                    bg:       '#08090f',
                    sidebar:  '#0c0e16',
                    surface:  '#111320',
                    surface2: '#161929',
                },
                accent: {
                    DEFAULT: '#6366f1',
                    light:   'rgba(99,102,241,0.12)',
                    hover:   '#4f46e5',
                },
                ink: {
                    1: '#e8e6e1',
                    2: 'rgba(232,230,225,0.55)',
                    3: 'rgba(232,230,225,0.25)',
                },
                border: {
                    DEFAULT: 'rgba(255,255,255,0.07)',
                    strong:  'rgba(255,255,255,0.13)',
                },
                // ── Legacy / landing ────────────────────────────────────────
                brand: {
                    50:  '#FFF8F0',
                    100: '#FEECD8',
                    200: '#FCCFA5',
                    300: '#F9A96A',
                    400: '#F07D38',
                    500: '#C2692A',
                    600: '#A55420',
                    700: '#87411A',
                    800: '#6D3316',
                    900: '#1C1917',
                    950: '#110F0D',
                },
                warm: {
                    50:  '#FAF7F2',
                    100: '#F5EFE6',
                    200: '#EDE4D8',
                    300: '#DDD3C5',
                    400: '#C8BAAA',
                    500: '#A89B8A',
                    600: '#8A7D6E',
                    700: '#6E6254',
                    800: '#544A3E',
                    900: '#3A332C',
                },
            },
        },
    },

    plugins: [forms],
};
