import type { UiOffer } from './types';

export interface Wall {
  id: string;
  name: string;
  icon: string;
  color: string;
  bg: string;
  offers: UiOffer[];
}

/** Deterministic per-provider visual palette (icon + accent). */
const PALETTE = [
  { icon: 'fa-bolt', color: '#00b67a', bg: 'rgba(0,182,122,0.1)' },
  { icon: 'fa-fire', color: '#ff2d6c', bg: 'rgba(255,45,108,0.1)' },
  { icon: 'fa-dragon', color: '#13a0e8', bg: 'rgba(19,160,232,0.1)' },
  { icon: 'fa-gem', color: '#4179d6', bg: 'rgba(65,121,214,0.1)' },
  { icon: 'fa-shield-halved', color: '#005f7b', bg: 'rgba(0,95,123,0.1)' },
  { icon: 'fa-crosshairs', color: '#386cb5', bg: 'rgba(56,108,181,0.1)' },
];

function hash(s: string): number {
  let h = 0;
  for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) >>> 0;
  return h;
}

/** Group offers into per-provider "walls", preserving payout order within each. */
export function groupWalls(offers: UiOffer[]): Wall[] {
  const byProvider = new Map<string, UiOffer[]>();
  for (const o of offers) {
    const key = o.providerName || 'Offers';
    const list = byProvider.get(key) ?? [];
    list.push(o);
    byProvider.set(key, list);
  }

  return [...byProvider.entries()].map(([name, list]) => {
    const p = PALETTE[hash(name) % PALETTE.length];
    return {
      id: name.toLowerCase().replace(/\s+/g, '-'),
      name,
      icon: p.icon,
      color: p.color,
      bg: p.bg,
      offers: list,
    };
  });
}
