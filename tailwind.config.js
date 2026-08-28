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
                // Warm neutral foundation — intentionally avoids the usual blue/purple SaaS palette.
                app: {
                    bg:       '#0F0E0D',
                    sidebar:  '#141210',
                    surface:  '#1A1816',
                    surface2: '#24211E',
                },
                accent: {
                    DEFAULT: '#E16A4B',
                    light:   'rgba(225,106,75,0.12)',
                    hover:   '#F07A59',
                },
                ink: {
                    1: '#F4F0EA',
                    2: 'rgba(244,240,234,0.66)',
                    3: 'rgba(216,207,196,0.46)',
                },
                border: {
                    DEFAULT: 'rgba(235,225,214,0.09)',
                    strong:  'rgba(235,225,214,0.16)',
                },
                // Legacy / landing palette kept for compatibility, now aligned with the product identity.
                brand: {
                    50:  '#FFF7F3',
                    100: '#FDE9E2',
                    200: '#F9CFC1',
                    300: '#F3A88F',
                    400: '#EB8061',
                    500: '#E16A4B',
                    600: '#C85439',
                    700: '#A84230',
                    800: '#843628',
                    900: '#4D241E',
                    950: '#2B1512',
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
