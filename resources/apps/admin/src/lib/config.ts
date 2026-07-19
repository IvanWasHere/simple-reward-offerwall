/**
 * Boot object injected by the PHP takeover (SpaRouteServiceProvider) as
 * `window.SimpleRewardOffer`. Shape mirrors SimpleRewardOffer\Services\SpaBoot::data().
 */
export interface SimpleRewardOfferConfig {
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
    SimpleRewardOffer: SimpleRewardOfferConfig;
  }
}

export const config = (): SimpleRewardOfferConfig => window.SimpleRewardOffer;
