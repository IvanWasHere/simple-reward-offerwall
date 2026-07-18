import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

/**
 * Vite build for the RewardVault Admin SPA.
 *
 * Source lives here (resources/apps/admin) — outside resources/assets/apps so the
 * legacy webpack config ignores it. Output → public/apps/admin with a manifest;
 * the PHP takeover (SpaRouteServiceProvider) reads .vite/manifest.json to emit the
 * hashed entry tags at the admin slug.
 */
export default defineConfig({
  root: __dirname,
  base: '/wp-content/plugins/simple-reward-offerwall/public/apps/admin/',
  plugins: [react()],
  build: {
    outDir: resolve(__dirname, '../../../public/apps/admin'),
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: resolve(__dirname, 'index.html'),
    },
  },
});
