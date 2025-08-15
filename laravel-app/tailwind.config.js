import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Ableton Sans', 'AbletonSans-Regular', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Custom Ableton Cookbook palette
                ableton: {
                    gray: {
                        light: '#BBBBBB',
                        medium: '#C3C3C3',
                        dark: '#6C6C6C',
                        darker: '#4a4a4a',
                        black: '#0D0D0D',
                    },
                    yellow: '#ffdf00',
                    cyan: '#01CADA', 
                    pink: '#F87680',
                    green: '#01DA48',
                },
            },
        },
    },

    plugins: [forms, typography],
};
