/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        "primary": "#000000",
        "secondary": "#775a19",
        "background": "#f8f9fa",
        "surface-container-low": "#f3f4f5",
        "surface-container-highest": "#e1e3e4",
        "outline-variant": "#c4c7c7",
        // ... add the rest of the tokens from your provided HTML
      },
      fontFamily: {
        headline: ['Noto Serif', 'serif'],
        body: ['Inter', 'sans-serif'],
        label: ['Inter', 'sans-serif'],
      }
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/container-queries'),
  ],
}