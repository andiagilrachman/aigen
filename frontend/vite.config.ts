import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

/**
 * Target proxy backend saat pengembangan.
 * Di XAMPP biasanya http://localhost/aigen-backend/public
 */
const BACKEND_ORIGIN = process.env.VITE_BACKEND_ORIGIN ?? 'http://127.0.0.1:8080';

export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    host: '0.0.0.0',
    port: 5173,
    // Preview dijalankan di balik host proxy, jadi allowlist bawaan Vite
    // harus dilonggarkan agar permintaan tidak ditolak.
    allowedHosts: true,
    /**
     * Browser pengguna TIDAK berada di mesin yang sama dengan backend, jadi
     * frontend tidak boleh memanggil localhost secara langsung. Semua panggilan
     * memakai path relatif /api dan Vite yang meneruskannya ke PHP.
     * Efek sampingnya bagus: origin-nya sama, sehingga cookie sesi ikut terkirim
     * tanpa perlu konfigurasi CORS di lingkungan pengembangan.
     */
    proxy: {
      '/api': {
        target: BACKEND_ORIGIN,
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api/, ''),
      },
    },
  },
});
