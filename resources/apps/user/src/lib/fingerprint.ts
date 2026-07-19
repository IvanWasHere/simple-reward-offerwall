import { Fingerprint, type FingerprintResult } from 'fingerprinter-js';
import { api } from './api';

/**
 * A small, always-available summary of the device (navigator/screen/timezone)
 * used for the indexed display columns on the admin side. The rich fingerprint
 * hash + the full collector data come from fingerprinter-js below.
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
 * Capture the device fingerprint via fingerprinter-js (bundled in this plugin,
 * runs fully client-side — no external API — with 19 collectors + bot detection)
 * and POST it to be saved. Called on each login. Best-effort: never throws.
 *
 * `visitorId` = the library's stable SHA-256 hash; `data` = the FULL library
 * result (all collectors under `components`, plus confidence / entropy /
 * suspectAnalysis) so admins have the complete signal set.
 */
export async function captureFingerprint(): Promise<void> {
  const components = summary();
  let visitorId = '';
  let data: FingerprintResult | Record<string, unknown> = { ...components };

  try {
    const result = await Fingerprint.generate({ includeSuspectAnalysis: true });
    visitorId = result.fingerprint || '';
    data = result; // full JSON from the library — all collectors + analysis.
  } catch {
    /* fingerprinter-js unavailable — fall back to the flat summary */
  }

  try {
    await api('/me/fingerprint', { method: 'POST', body: { visitorId, components, data } });
  } catch {
    /* fingerprinting is non-critical; ignore failures */
  }
}
