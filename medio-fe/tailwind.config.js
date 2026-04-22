/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        primary: "hsl(var(--primary))",
        "primary-container": "hsl(var(--primary-container))",
        secondary: "hsl(var(--secondary))",
        "secondary-container": "hsl(var(--secondary-container))",
        background: "hsl(var(--background))",
        surface: "hsl(var(--surface))",
        "surface-container-low": "hsl(var(--surface-container-low))",
        "surface-container-high": "hsl(var(--surface-container-high))",
        "on-surface": "hsl(var(--on-surface))",
        "on-surface-variant": "hsl(var(--on-surface-variant))",
        outline: "hsl(var(--outline))",
        "outline-variant": "hsl(var(--outline-variant))",
      },
      fontFamily: {
        headline: ["Outfit", "sans-serif"],
        body: ["Inter", "sans-serif"],
        label: ["JetBrains Mono", "monospace"],
      },
    },
  },
  plugins: [],
}
