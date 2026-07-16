/**
 * Tiny REST client for the Simple Reward Offerwall user SPA.
 *
 * - Sends the httpOnly session cookie automatically (credentials: 'same-origin').
 * - Attaches the double-submit CSRF header (read from the non-httpOnly ro_csrf
 *   cookie) on every mutating request.
 * - Normalizes WP_Error responses ({ code, message, data:{status} }) into a
 *   thrown ApiError.
 */

export interface SimpleROConfig {
  restBase: string;
  app: 'user' | 'admin' | 'support';
  cookieCsrf: string;
  csrfHeader: string;
  pages: Record< string, string >;
  homeUrl: string;
}

declare global {
  interface Window {
    SimpleRO: SimpleROConfig;
  }
}

export class ApiError extends Error {
  status: number;
  code: string;

  constructor( message: string, status: number, code: string ) {
    super( message );
    this.name = 'ApiError';
    this.status = status;
    this.code = code;
  }
}

function getCookie( name: string ): string {
  const match = document.cookie.match(
    new RegExp( '(?:^|; )' + name.replace( /([.$?*|{}()[\]\\/+^])/g, '\\$1' ) + '=([^;]*)' )
  );
  return match ? decodeURIComponent( match[ 1 ] ) : '';
}

export interface ApiOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  body?: Record< string, unknown >;
}

export async function api< T = any >( path: string, opts: ApiOptions = {} ): Promise< T > {
  const cfg = window.SimpleRO;
  const method = opts.method ?? 'GET';
  const headers: Record< string, string > = { Accept: 'application/json' };

  if ( method !== 'GET' ) {
    headers[ 'Content-Type' ] = 'application/json';
    const csrf = getCookie( cfg.cookieCsrf );
    if ( csrf ) {
      headers[ cfg.csrfHeader ] = csrf;
    }
  }

  const res = await fetch( cfg.restBase + path, {
    method,
    credentials: 'same-origin',
    headers,
    body: opts.body ? JSON.stringify( opts.body ) : undefined,
  } );

  let data: any = {};
  try {
    data = await res.json();
  } catch {
    // empty / non-JSON body
  }

  if ( ! res.ok ) {
    const status = data?.data?.status ?? res.status;
    const message = data?.message ?? 'Request failed.';
    const code = data?.code ?? 'error';
    throw new ApiError( message, status, code );
  }

  return data as T;
}
