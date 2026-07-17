import { useMemo, useState } from 'react';
import { OfferCard } from '../components/OfferCard';
import { Loading, EmptyState, ErrorState } from '../components/States';
import { useOffers } from '../hooks/useOffers';
import type { UiOffer } from '../lib/types';

const PLATFORMS = ['all', 'android', 'ios', 'desktop'] as const;

export function OffersPage({ onSelectOffer }: { onSelectOffer: (o: UiOffer) => void }) {
  const { offers, loading, error } = useOffers();
  const [search, setSearch] = useState('');
  const [platform, setPlatform] = useState<(typeof PLATFORMS)[number]>('all');

  const filtered = useMemo(() => {
    let list = offers;
    const q = search.trim().toLowerCase();
    if (q) list = list.filter((o) => o.name.toLowerCase().includes(q));
    if (platform !== 'all') list = list.filter((o) => o.platforms.includes(platform));
    return list;
  }, [offers, search, platform]);

  return (
    <div className="page-enter">
      <div className="section-title">
        <i className="fa-solid fa-gift" />
        All Offers
        <span
          style={{ fontSize: 14, color: 'var(--text-muted)', fontWeight: 400, marginLeft: 8 }}
        >
          {filtered.length} available
        </span>
      </div>

      <div className="filter-bar">
        <div className="search-wrap">
          <i className="fa-solid fa-magnifying-glass" />
          <input
            className="search-input"
            type="text"
            placeholder="Search offers..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>
        {PLATFORMS.map((p) => (
          <button
            key={p}
            className={'filter-btn' + (platform === p ? ' active' : '')}
            onClick={() => setPlatform(p)}
          >
            {p === 'all' ? 'All Platforms' : p.charAt(0).toUpperCase() + p.slice(1)}
          </button>
        ))}
      </div>

      {loading ? (
        <Loading label="Loading offers…" />
      ) : error ? (
        <ErrorState message={error} />
      ) : filtered.length === 0 ? (
        <EmptyState icon="fa-magnifying-glass" message="No offers found matching your criteria" />
      ) : (
        <div className="offers-grid">
          {filtered.map((o) => (
            <OfferCard key={o.key} offer={o} onClick={() => onSelectOffer(o)} />
          ))}
        </div>
      )}
    </div>
  );
}
