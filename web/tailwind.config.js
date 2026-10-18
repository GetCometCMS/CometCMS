const defaultColors = require('tailwindcss/colors')
const defaultTheme = require('tailwindcss/defaultTheme')

// Neutrals are driven by CSS variables so the dark theme can invert the scale
// in one place (style.css) instead of overriding every slate utility.
const slateShades = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950]
const slate = Object.fromEntries(
  slateShades.map((shade) => [shade, `rgb(var(--color-slate-${shade}) / <alpha-value>)`]),
)

/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './index.html',
    './src/**/*.{vue,js}',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Inter Variable"', 'Inter', ...defaultTheme.fontFamily.sans],
      },
      boxShadow: {
        xs: '0 1px 2px 0 rgb(16 24 40 / 0.05)',
      },
      colors: {
        slate,
        // Fixed dark neutrals for surfaces that stay dark in every theme
        // (modal backdrops, toasts, code bars, image overlays).
        ink: defaultColors.slate,
        theme: {
          50: 'rgb(var(--color-theme-50) / <alpha-value>)',
          100: 'rgb(var(--color-theme-100) / <alpha-value>)',
          200: 'rgb(var(--color-theme-200) / <alpha-value>)',
          300: 'rgb(var(--color-theme-300) / <alpha-value>)',
          400: 'rgb(var(--color-theme-400) / <alpha-value>)',
          500: 'rgb(var(--color-theme-500) / <alpha-value>)',
          600: 'rgb(var(--color-theme-600) / <alpha-value>)',
          700: 'rgb(var(--color-theme-700) / <alpha-value>)',
          800: 'rgb(var(--color-theme-800) / <alpha-value>)',
          900: 'rgb(var(--color-theme-900) / <alpha-value>)',
        },
        sidebar: {
          DEFAULT: 'rgb(var(--color-sidebar) / <alpha-value>)',
          hover: 'rgb(var(--color-sidebar-hover) / <alpha-value>)',
          border: 'rgb(var(--color-sidebar-border) / var(--color-sidebar-border-alpha))',
        },
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
  ],
}
