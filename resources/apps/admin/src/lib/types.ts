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
  offerSchema: string;
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
  paramMap: Record<string, string>;
  signatureParam: string;
  signatureAlgo: string;
  signatureSource: string;
  ipAllowlist: string;
  hasSecret: boolean;
  active: boolean;
}

export interface OfferSchemaMacro {
  token: string;
  label: string;
  description: string;
}

export interface OfferSchemaField {
  field: string;
  key: string;
  macro: string;
  label: string;
  description: string;
  required: boolean;
  mapped: boolean;
}

export interface OfferSchema {
  key: string;
  label: string;
  httpMethod: string;
  callbackMacros: OfferSchemaMacro[];
  callbackFields: OfferSchemaField[];
  postbackTemplate: string;
  defaultParamMap: Record<string, string>;
  signatureParam: string;
  signatureAlgo: string;
  signatureSource: string;
  allowsUnsigned: boolean;
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
  callback_type: string | null;
  provider_offer_id: string | null;
  task_id: string | null;
  amount: string;
  currency: string | null;
  status: string;
  signature_ok: string;
  ip: string | null;
  raw_payload: Record<string, unknown>;
  mapped: Record<string, unknown>;
  created_at: string;
}
