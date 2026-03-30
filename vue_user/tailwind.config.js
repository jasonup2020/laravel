/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        primary: '#409eff',
        success: '#67c23a',
        warning: '#e6a23c',
        danger: '#f56c6c',
        info: '#909399',
      }
    },
  },
  plugins: [],
}
