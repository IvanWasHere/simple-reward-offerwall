/** Formatting helpers ported from the RewardVault prototype. */

/** 1234 → "1.2k"; smaller values get thousands separators. */
export function formatCoins(n: number): string {
  const v = Math.round(n || 0);
  if (v >= 1000) {
    return (v / 1000).toFixed(1).replace(/\.0$/, '') + 'k';
  }
  return v.toLocaleString('en-US', { maximumFractionDigits: 0 });
}

/** Map a platform slug to the offer-tag CSS modifier class. */
export function platformClass(p: string): string {
  if (p === 'android') return 'platform-android';
  if (p === 'ios') return 'platform-ios';
  return 'platform-desktop';
}

export function titleCase(s: string): string {
  return s ? s.charAt(0).toUpperCase() + s.slice(1) : s;
}
