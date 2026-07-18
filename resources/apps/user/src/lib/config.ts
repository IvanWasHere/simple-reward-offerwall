/**
 * The boot object injected by the PHP takeover (SpaRouteServiceProvider) /
 * shortcode provider as `window.SimpleRO`. Shape mirrors
 * SimpleRO\Services\SpaBoot::data().
 */
export interface SimpleROConfig {
  restBase: string;
  app: 'user' | 'admin' | 'support';
  appName: string;
  appIconUrl: string;
  cookieCsrf: string;
  csrfHeader: string;
  pages: Record<string, string>;
  homeUrl: string;
  rewardUrl: string;
  adminUrl: string;
}

declare global {
  interface Window {
    SimpleRO: SimpleROConfig;
  }
}

export const config = (): SimpleROConfig => window.SimpleRO;
