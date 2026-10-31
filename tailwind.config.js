/** Tailwind config for Vulcatic. Rebuild CSS after adding classes (see README). */
module.exports = {
  content: ['./*.php', './*.html', './includes/**/*.php', './assets/js/**/*.js'],
  theme: {
    extend: {
      fontFamily: { sans: ['Poppins', 'system-ui', 'sans-serif'] },
      colors: {
        brand: { 50: '#FFF3EE', 100: '#FFE1D4', 500: '#E8501E', 600: '#D03F10', 700: '#A9320C' },
        ink:   { 900: '#1E2235', 800: '#2A2F45' },
        ok:    { 500: '#22C55E', 600: '#16A34A' },
      },
    },
  },
};
