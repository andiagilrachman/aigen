import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

/**
 * Konfigurasi khusus smoke test.
 *
 * jsdom hanya bisa menjalankan skrip klasik, sedangkan bundel produksi berupa
 * ES module dengan code splitting. Di sini semuanya digabung menjadi satu
 * berkas IIFE supaya bisa dieksekusi window.eval(). Hanya untuk pengujian —
 * artefak yang dikirim ke pengguna tetap hasil `npm run build`.
 */
export default defineConfig({
  plugins: [react(), tailwindcss()],
  // Build library tidak otomatis mengganti process.env.NODE_ENV seperti build
  // aplikasi, sedangkan React membacanya saat modul dimuat.
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
    'process.env': '{}',
  },
  build: {
    outDir: 'dist-smoke',
    emptyOutDir: true,
    lib: {
      entry: 'src/main.tsx',
      formats: ['iife'],
      name: 'AigenSmoke',
      fileName: () => 'smoke.js',
    },
    rollupOptions: { output: { inlineDynamicImports: true } },
  },
});
