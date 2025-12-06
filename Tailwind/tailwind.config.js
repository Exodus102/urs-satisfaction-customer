/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "../*.{html,js,php}",
    "../pages/**/*.{html,js,php}",
    "../function/**/*.{html,js,php}",
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ["SF Pro", "sans-serif"],
      },
    },
  },
  plugins: [],
};
