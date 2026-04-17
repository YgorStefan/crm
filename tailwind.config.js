/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        "./app/Views/**/*.php",
        "./public/assets/js/**/*.js",
    ],
    theme: {
        extend: {
            colors: {
                primary: {
                    50: '#eef2ff',
                    100: '#e0e7ff',
                    500: '#6366f1',
                    600: '#4f46e5',
                    700: '#4338ca',
                },
                sidebar: '#1e1b4b',
            },
        },
    },
    plugins: [],
};
