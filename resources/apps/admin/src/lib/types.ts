/** Admin API resource shapes (mirror the Admin\* controllers' present()/index()). */

export interface Provider {
  id: number;
  hash: string;
  name: string;
  type: string;
  url: string;
  macros: Record<string, string> | Record<string, never>;
  adslotId: string;
  apiKey: string;
  hasApiSecret: boolean;
  coinRate: number;
  config: Record<string, unknown> | Record<string, never>;
  wallPlacement: string;
  status: string;
  callbackCount: number | null;
  createdAt: string;
}

export interface ProviderCallback {
  id: number;
  name: string;
  callbackUrl: string;
  signatureAlgo: string;
  active: boolean;
}

export interface OfferRow {
  id: number;
  providerName: string;
  providerOfferId: string;
  name: string;
  totalPayout: number;
  available: boolean;
  enabled: boolean;
}

export interface RewardRow {
  id: number;
  user_email: string | null;
  coins_value: number;
  provider_name: string | null;
  transaction_id: string | null;
  status?: string;
  created_at?: string;
}

export interface RedemptionRow {
  id: number;
  user_email: string | null;
  coins_spent: number;
  payout_name: string | null;
  status?: string;
  created_at?: string;
}

export interface Payout {
  id: number;
  name: string;
  valueCoins: number;
  valueMoney: number;
  currency?: string;
  stock: number;
  status: string;
}

export interface UserRow {
  id: number;
  email: string;
  type: string;
  status: string;
  balance: number;
  displayName?: string;
}

export interface CallbackAuditRow {
  id: string;
  provider_name: string | null;
  user_email: string | null;
  transaction_id: string;
  amount: string;
  status: string;
  signature_ok: string;
  created_at: string;
}
