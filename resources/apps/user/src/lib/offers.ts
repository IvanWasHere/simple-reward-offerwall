import type { ApiOffer, OfferTask, UiOffer } from './types';

/** Derive platform tags from an offer's device/os hints. */
function platforms(o: ApiOffer): string[] {
  const raw = [o.device, o.os]
    .filter(Boolean)
    .join(',')
    .toLowerCase();
  const out: string[] = [];
  if (raw.includes('android')) out.push('android');
  if (raw.includes('ios') || raw.includes('iphone') || raw.includes('ipad')) out.push('ios');
  if (
    raw.includes('desktop') ||
    raw.includes('web') ||
    raw.includes('pc') ||
    raw.includes('mac') ||
    raw.includes('all')
  ) {
    out.push('desktop');
  }
  return out.length ? out : ['desktop'];
}

function tasks(o: ApiOffer): OfferTask[] {
  if (!Array.isArray(o.tasks)) return [];
  const out: OfferTask[] = [];
  for (const t of o.tasks) {
    const rec = t as Record<string, unknown>;
    const name = String(rec.name ?? '').trim();
    if (!name) continue;
    const coinsRaw = rec.coins;
    out.push(typeof coinsRaw === 'number' ? { name, coins: coinsRaw } : { name });
  }
  return out;
}

/** Map a raw API offer onto the shape the cards/detail modal render. */
export function normalizeOffer(o: ApiOffer): UiOffer {
  const icons = Array.isArray(o.icons) ? o.icons.filter(Boolean) : [];
  const ratingStr =
    o.rating === null || o.rating === undefined || o.rating === ''
      ? null
      : String(o.rating);

  return {
    key: `${o.providerId ?? 0}:${o.providerOfferId ?? o.name}`,
    providerId: o.providerId ?? 0,
    providerOfferId: String(o.providerOfferId ?? ''),
    providerName: o.providerName ?? '',
    name: o.name,
    icon: icons[0] ?? '',
    iconLarge: icons[1] ?? icons[0] ?? '',
    coins: o.coins ?? Math.round(o.totalPayout ?? 0),
    platforms: platforms(o),
    tasks: tasks(o),
    description: o.description ?? '',
    instructions: o.instructions ?? '',
    screenshots: Array.isArray(o.screenshots) ? o.screenshots.filter(Boolean) : [],
    rating: ratingStr,
  };
}
