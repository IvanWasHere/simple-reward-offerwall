/** Domain types shared across the RewardVault user SPA. */

export interface User {
  id: number;
  email: string;
  type: string;
  status: string;
  displayName: string;
  hash: string;
}

/** Raw offer as returned by GET /offers (static + live merged). */
export interface ApiOffer {
  providerId?: number;
  providerName: string;
  providerOfferId?: string;
  name: string;
  tasks?: Array<Record<string, unknown>> | null;
  totalPayout?: number;
  coins?: number;
  device?: string;
  os?: string;
  country?: string;
  icons?: string[];
  source?: string;
  // Optional rich fields some live adapters may include.
  description?: string;
  link?: string;
  rating?: string | number | null;
  screenshots?: string[];
  instructions?: string;
}

export interface OfferTask {
  name: string;
  coins?: number;
}

/** Normalized offer the UI renders (see lib/offers.ts). */
export interface UiOffer {
  key: string;
  providerId: number;
  providerOfferId: string;
  providerName: string;
  name: string;
  icon: string;
  iconLarge: string;
  coins: number;
  platforms: string[];
  tasks: OfferTask[];
  description: string;
  instructions: string;
  screenshots: string[];
  rating: string | null;
}

export interface Offerwall {
  id: number;
  name: string;
  type: 'iframe' | 'offerwall_api';
  hash: string;
  placement: 'hot' | 'all';
}

export interface Payout {
  id: number;
  name: string;
  valueMoney: number;
  valueCoins: number;
  currency: string;
  smallIcon: string;
  midsizeIcon: string;
  largeIcon: string;
}

export interface Redemption {
  id: number;
  coins_spent: number;
  status: string;
  created_at: string;
  payout_name: string;
  value_money: number;
  currency: string;
}
