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
                // Deep teal foundation: calm, trustworthy and connected to the WhatsApp context.
                app: {
                    bg:       '#071214',
                    sidebar:  '#0A181A',
                    surface:  '#0F1F21',
                    surface2: '#172B2E',
                },
                accent: {
                    DEFAULT: '#2DD4BF',
                    light:   'rgba(45,212,191,0.12)',
                    hover:   '#5EEAD4',
                },
                ink: {
                    1: '#ECFDFB',
                    2: 'rgba(219,242,238,0.68)',
                    3: 'rgba(173,207,201,0.48)',
                },
                border: {
                    DEFAULT: 'rgba(184,226,219,0.10)',
                    strong:  'rgba(184,226,219,0.18)',
                },
                // Legacy / landing palette kept for compatibility and aligned with the product identity.
                brand: {
                    50:  '#F0FDFA',
                    100: '#CCFBF1',
                    200: '#99F6E4',
                    300: '#5EEAD4',
                    400: '#2DD4BF',
                    500: '#14B8A6',
                    600: '#0D9488',
                    700: '#0F766E',
                    800: '#115E59',
                    900: '#134E4A',
                    950: '#042F2E',
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
