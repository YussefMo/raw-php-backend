import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    // This project lives in WSL but is often edited from Windows (via
    // \\wsl.localhost), and those writes do not reliably fire inotify inside the
    // WSL Vite process. Without polling, Vite can keep serving STALE modules
    // (e.g. new buttons never appear until a manual restart). Polling fixes it.
    watch: { usePolling: true, interval: 300 },
  },
});
