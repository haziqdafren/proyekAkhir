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
                    DEFAULT: '#F0F4F9',
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
                '14': '3.5rem',
                '18': '4.5rem',
                '56': '14rem',
                '88': '22rem',
            },
            boxShadow: {
                'card':    '0 1px 4px 0 rgba(17,45,78,0.10), 0 1px 2px 0 rgba(17,45,78,0.06)',
                'card-md': '0 4px 16px -2px rgba(17,45,78,0.14), 0 2px 6px -1px rgba(17,45,78,0.08)',
                'card-lg': '0 12px 28px -4px rgba(17,45,78,0.18), 0 4px 10px -2px rgba(17,45,78,0.10)',
            },
        },
    },
    plugins: [],
};
