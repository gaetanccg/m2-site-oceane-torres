/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './index.html',
        './src/**/*.{vue,js,ts,jsx,tsx}'
    ],
    theme: {
        extend: {
            colors: {
                white: '#ffffff',
                black: '#0a0708',
                gray: '#aaaaaa',
                grey: '#aaaaaa',
                gold: '#dcb253',
                cream: '#faf8f5'
            }
        }
    },
    plugins: []
};
