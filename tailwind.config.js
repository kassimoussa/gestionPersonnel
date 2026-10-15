import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Les couleurs du thème pointent vers des variables CSS (valeurs par défaut dans
 * resources/css/app.css, couleur d'accent surchargée par partials/variables-theme.blade.php
 * depuis les paramètres de l'application) : elles changent à l'exécution sans recompilation.
 *
 * @type {import('tailwindcss').Config}
 */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            boxShadow: {
                xs: '0 1px 2px 0 rgba(16, 24, 40, 0.05)',
            },
            colors: {
                ink: 'var(--color-ink)',
                fond: 'var(--color-bg)',
                surface: 'var(--color-surface)',
                bordure: 'var(--color-border)',
                'bordure-forte': 'var(--color-border-strong)',
                secondaire: 'var(--color-text-secondary)',
                tertiaire: 'var(--color-text-tertiary)',
                accent: {
                    DEFAULT: 'var(--color-accent)',
                    light: 'var(--color-accent-light)',
                    soft: 'var(--color-accent-soft)',
                },
                accent2: { DEFAULT: 'var(--color-accent-2)', doux: 'var(--color-accent-2-doux)' },
                succes: { DEFAULT: 'var(--color-succes)', doux: 'var(--color-succes-doux)', bord: 'var(--color-succes-bord)' },
                attention: { DEFAULT: 'var(--color-attention)', doux: 'var(--color-attention-doux)', bord: 'var(--color-attention-bord)' },
                danger: { DEFAULT: 'var(--color-danger)', doux: 'var(--color-danger-doux)', bord: 'var(--color-danger-bord)' },
                info: { DEFAULT: 'var(--color-info)', doux: 'var(--color-info-doux)', bord: 'var(--color-info-bord)' },
            },
        },
    },

    plugins: [forms],
};
