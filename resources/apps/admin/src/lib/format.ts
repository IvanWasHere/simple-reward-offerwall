/** Formatting helpers ported from the admin prototype. */

export function fmtCoins(n: number): string {
  const v = Math.round(n || 0);
  if (v >= 1000) return (v / 1000).toFixed(1).replace(/\.0$/, '') + 'k';
  return v.toLocaleString('en-US');
}

/** Money in integer minor units → currency string. */
export function fmtMoney(minor: number, currency = 'USD'): string {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: currency || 'USD' }).format(
    (minor || 0) / 100
  );
}

export function fmtDate(d: string | number | null | undefined): string {
  if (!d) return '—';
  // Server timestamps are UTC without a zone; treat them as such.
  const iso = typeof d === 'string' && /^\d{4}-\d{2}-\d{2} /.test(d) ? d.replace(' ', 'T') + 'Z' : d;
  const date = new Date(iso);
  return isNaN(date.getTime())
    ? '—'
    : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
