import { getThumbmark } from '@thumbmarkjs/thumbmarkjs';
import { api } from './api';

/**
 * A small, always-available summary of the device (navigator/screen/timezone)
 * used for the indexed display columns on the admin side. The rich, robust
 * fingerprint itself comes from ThumbmarkJS below.
 */
function summary(): Record<string, string | number | boolean> {
  const nav = navigator as Navigator & { deviceMemory?: number };
  const s = window.screen;
  let timezone = '';
  try {
    timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
  } catch {
    /* ignore */
  }
  return {
    userAgent: nav.userAgent || '',
    platform: nav.platform || '',
    vendor: nav.vendor || '',
    language: nav.language || '',
    languages: (nav.languages || []).join(','),
    timezone,
    timezoneOffset: new Date().getTimezoneOffset(),
    screen: `${s.width}x${s.height}`,
    colorDepth: s.colorDepth,
    pixelRatio: window.devicePixelRatio || 1,
    hardwareConcurrency: nav.hardwareConcurrency || 0,
    deviceMemory: nav.deviceMemory ?? 0,
    maxTouchPoints: nav.maxTouchPoints || 0,
    cookieEnabled: nav.cookieEnabled,
  };
}

/**
 * Capture the device fingerprint via ThumbmarkJS (bundled in this plugin, runs
 * fully client-side — no external API) and POST it to be saved. Called on each
 * login. Best-effort: never throws.
 */
export async function captureFingerprint(): Promise<void> {
  const base = summary();
  let visitorId = '';
  let components: Record<string, unknown> = { ...base };

  try {
    const tm = await getThumbmark(); // local computation only
    visitorId = tm.thumbmark || '';
    components = {
      ...base,
      thumbmark: tm.thumbmark,
      thumbmarkVersion: tm.version,
      thumbmarkComponents: tm.components,
    };
  } catch {
    /* ThumbmarkJS unavailable — fall back to the flat summary */
  }

  try {
    await api('/me/fingerprint', { method: 'POST', body: { visitorId, components } });
  } catch {
    /* fingerprinting is non-critical; ignore failures */
  }
}
