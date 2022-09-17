const plugin = require('tailwindcss/plugin');

module.exports = {
  mode: 'jit',
  plugins: [
    require('tailwindcss-multi-column')(),
    require('@tailwindcss/typography'),
    require('@tailwindcss/aspect-ratio'),
    require('@tailwindcss/line-clamp'),
    require('@tailwindcss/forms'),
  ],
};
