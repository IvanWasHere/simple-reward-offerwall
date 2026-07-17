import { useMemo } from 'react';
import { OfferwallButtons } from '../components/OfferwallButtons';
import { Loading, EmptyState, ErrorState } from '../components/States';
import { useOffers } from '../hooks/useOffers';
import { useApiData } from '../hooks/useApiData';
import { formatCoins } from '../lib/format';
import type { Offerwall, UiOffer } from '../lib/types';

const FALLBACK_ICON =
  "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='180' height='100'%3E%3Crect width='180' height='100' fill='%23003356'/%3E%3C/svg%3E";

export function EarnPage({ onSelectOffer }: { onSelectOffer: (o: UiOffer) => void }) {
  const { offers, loading, error } = useOffers();
  const walls = useApiData<{ offerwalls: Offerwall[] }>('/offerwalls');

  // Only iframe providers open in an <iframe> wall. Hot Walls = admin-flagged
  // 'hot'; All Walls = every visible offerwall (placement != 'none').
  const iframeWalls = useMemo(
    () => (walls.data?.offerwalls ?? []).filter((w) => w.type === 'iframe'),
    [walls.data]
  );
  const hotWalls = iframeWalls.filter((w) => w.placement === 'hot');

  const featured = offers.slice(0, 8);

  return (
    <div className="page-enter">
      <div className="section-block">
        <div className="section-title">
          <i className="fa-solid fa-fire-flame-curved" />
          Featured Offers
        </div>
        {loading ? (
          <Loading label="Loading offers…" />
        ) : error ? (
          <ErrorState message={error} />
        ) : featured.length === 0 ? (
          <EmptyState icon="fa-gift" message="No offers available yet. Check back soon!" />
        ) : (
          <div className="featured-scroll">
            {featured.map((o) => (
              <div className="featured-card" key={o.key} onClick={() => onSelectOffer(o)}>
                <div className="featured-badge">HOT</div>
                <img
                  className="featured-card-img"
                  src={o.iconLarge || o.icon || FALLBACK_ICON}
                  alt={o.name}
                  loading="lazy"
                  onError={(e) => {
                    (e.currentTarget as HTMLImageElement).src = FALLBACK_ICON;
                  }}
                />
                <div className="featured-card-body">
                  <div className="featured-card-name">{o.name}</div>
                  <div className="featured-card-payout">+{formatCoins(o.coins)} coins</div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      <div className="section-block">
        <div className="section-title">
          <i className="fa-solid fa-fire" />
          Hot Walls
        </div>
        {walls.loading ? (
          <Loading label="Loading offerwalls…" />
        ) : hotWalls.length === 0 ? (
          <EmptyState icon="fa-fire" message="No featured offerwalls right now." />
        ) : (
          <OfferwallButtons offerwalls={hotWalls} />
        )}
      </div>

      <div className="section-block">
        <div className="section-title">
          <i className="fa-solid fa-layer-group" />
          All Walls
        </div>
        {walls.loading ? (
          <Loading label="Loading offerwalls…" />
        ) : iframeWalls.length === 0 ? (
          <EmptyState icon="fa-layer-group" message="No offerwalls available yet." />
        ) : (
          <OfferwallButtons offerwalls={iframeWalls} />
        )}
      </div>
    </div>
  );
}
