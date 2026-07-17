/**
 * REST client for the RewardVault user SPA.
 *
 * - Sends the httpOnly session cookie automatically (credentials: 'include').
 * - Attaches the double-submit CSRF header (read from the non-httpOnly ro_csrf
 *   cookie) on every mutating request.
 * - Normalizes WP_Error responses ({ code, message, data:{status} }) into a
 *   thrown ApiError.
 *
 * Ported from resources/assets/apps/shared/api.ts.
 */
import { config } from './config';

export class ApiError extends Error {
  status: number;
  code: string;

  constructor(message: string, status: number, code: string) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.code = code;
  }
}

function getCookie(name: string): string {
  const match = document.cookie.match(
    new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)')
  );
  return match ? decodeURIComponent(match[1]) : '';
}

export interface ApiOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  body?: Record<string, unknown>;
}

export async function api<T = unknown>(path: string, opts: ApiOptions = {}): Promise<T> {
  const cfg = config();
  const method = opts.method ?? 'GET';
  const headers: Record<string, string> = { Accept: 'application/json' };

  if (method !== 'GET') {
    headers['Content-Type'] = 'application/json';
    const csrf = getCookie(cfg.cookieCsrf);
    if (csrf) headers[cfg.csrfHeader] = csrf;
  }

  const res = await fetch(cfg.restBase + path, {
    method,
    credentials: 'include',
    headers,
    body: opts.body ? JSON.stringify(opts.body) : undefined,
  });

  let data: unknown = {};
  try {
    data = await res.json();
  } catch {
    /* empty / non-JSON body */
  }

  if (!res.ok) {
    const err = data as { code?: string; message?: string };
    throw new ApiError(
      err.message || 'Request failed',
      res.status,
      err.code || 'error'
    );
  }

  return data as T;
}
