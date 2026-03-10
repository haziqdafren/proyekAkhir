import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Custom palette
                background: {
                    DEFAULT: '#F9F7F7',
                    light: '#FFFFFF',
                },
                surface: {
                    DEFAULT: '#DBE2EF',
                    light: '#E8EDF5',
                },
                primary: {
                    DEFAULT: '#3F72AF',
                    dark: '#112D4E',
                    light: '#5A8FD3',
                    50: '#EBF2FA',
                    100: '#DBE2EF',
                    200: '#B8CCE8',
                    300: '#8FB0D9',
                    400: '#6691C3',
                    500: '#3F72AF',
                    600: '#335E8F',
                    700: '#284A6F',
                    800: '#1C364F',
                    900: '#112D4E',
                    950: '#0A1A2E',
                },
            },
            spacing: {
                '18': '4.5rem',
                '88': '22rem',
            },
        },
    },
    plugins: [],
};
