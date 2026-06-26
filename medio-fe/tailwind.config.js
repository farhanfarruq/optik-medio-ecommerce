/** @type {import('tailwindcss').Config} */
export default {
  content: ["./index.html", "./src/**/*.{vue,js,ts,jsx,tsx}"],
  theme: { extend: {
    colors: {
      primary: "hsl(var(--primary))", "primary-container": "hsl(var(--primary-container))", secondary: "hsl(var(--secondary))", "secondary-container": "hsl(var(--secondary-container))", background: "hsl(var(--background))", surface: "hsl(var(--surface))", "surface-container-low": "hsl(var(--surface-container-low))", "surface-container-high": "hsl(var(--surface-container-high))", "on-surface": "hsl(var(--on-surface))", "on-surface-variant": "hsl(var(--on-surface-variant))", outline: "hsl(var(--outline))", "outline-variant": "hsl(var(--outline-variant))",
      ink: "#15120E", graphite: "#2B2926", ivory: "#F7F3EC", porcelain: "#FCFAF6", mist: "#E7E1D8", taupe: "#B8A999", gold: "#B88A44", olive: "#56604B", optical: "#3F6F8F",
    },
      fontFamily: { headline: ["Plus Jakarta Sans", "Inter", "system-ui", "sans-serif"], body: ["Inter", "system-ui", "sans-serif"], label: ["Inter", "system-ui", "sans-serif"] },
    borderRadius: { premium: "8px", drawer: "12px" },
    boxShadow: { soft: "0 10px 30px rgba(21, 18, 14, 0.08)", card: "0 6px 18px rgba(21, 18, 14, 0.06)" },
  } },
  plugins: [],
}
