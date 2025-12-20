import { defineConfig } from 'vite'
import path from 'path'
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  build: {
    outDir: path.resolve(__dirname, '../assets/app'),
    emptyOutDir: true,
    sourcemap: false,
    target: 'es2018',
    cssCodeSplit: false,
    lib: {
      entry: path.resolve(__dirname, 'src/main.tsx'),
      name: 'TightShipWorkspace',
      formats: ['iife'],
      fileName: () => 'tightship-app.js',
    },
    rollupOptions: {
      output: {
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'tightship-app.css'
          }
          return 'tightship-[name][extname]'
        },
      },
    },
  },
})
