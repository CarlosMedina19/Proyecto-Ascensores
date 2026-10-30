import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  server: { port: 5173 },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: [fileURLToPath(new URL('./src/pruebas/configuracion.js', import.meta.url))],
    // Las pruebas de pantallas completas pasan de 5 s en equipos lentos o con Docker encendido.
    testTimeout: 20000,
  },
});
