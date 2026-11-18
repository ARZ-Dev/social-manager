import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './app/**/*.php',
        './vendor/livewire/livewire/src/Features/SupportPagination/views/*.blade.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['"Inter Variable"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Brand blue (#009DFF). Use 600+ behind white text: it keeps 4.5:1 contrast.
                brand: {
                    50: '#ebf7ff',
                    100: '#d1edff',
                    200: '#a6dcff',
                    300: '#6cc6ff',
                    400: '#33b1ff',
                    500: '#009dff',
                    600: '#0077c7',
                    700: '#0062a3',
                    800: '#004e82',
                    900: '#003d66',
                    950: '#061f60',
                },
            },
        },
    },
    plugins: [forms],
};
