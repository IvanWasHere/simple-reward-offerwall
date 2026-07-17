import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

/**
 * Vite build for the RewardVault user SPA.
 *
 * Source lives here (resources/apps/user) — intentionally OUTSIDE
 * resources/assets/apps so the legacy @wordpress/scripts webpack config
 * (webpack.config.js autoEntries) does not also try to build it.
 *
 * Output → public/apps/user with a manifest; the PHP takeover
 * (SpaRouteServiceProvider) reads .vite/manifest.json to emit the hashed
 * entry <script>/<link> tags on GET /reward.
 */
export default defineConfig({
  root: __dirname,
  // Assets are served from the plugin's public dir on the live WP site.
  base: '/wp-content/plugins/simple-reward-offerwall/public/apps/user/',
  plugins: [react()],
  build: {
    outDir: resolve(__dirname, '../../../public/apps/user'),
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: resolve(__dirname, 'index.html'),
    },
  },
});
