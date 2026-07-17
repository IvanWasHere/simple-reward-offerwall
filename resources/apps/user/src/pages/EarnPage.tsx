import { OfferWalls } from '../components/OfferWalls';
import { Loading, EmptyState, ErrorState } from '../components/States';
import { useOffers } from '../hooks/useOffers';
import { formatCoins } from '../lib/format';
import type { UiOffer } from '../lib/types';

const FALLBACK_ICON =
  "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='180' height='100'%3E%3Crect width='180' height='100' fill='%23003356'/%3E%3C/svg%3E";

export function EarnPage({ onSelectOffer }: { onSelectOffer: (o: UiOffer) => void }) {
  const { offers, loading, error } = useOffers();

  if (loading) return <Loading label="Loading offers…" />;
  if (error) return <ErrorState message={error} />;
  if (offers.length === 0) {
    return <EmptyState icon="fa-gift" message="No offers available yet. Check back soon!" />;
  }

  const featured = offers.slice(0, 8);
  const hot = offers.slice(0, 6);

  return (
    <div className="page-enter">
      <div className="section-block">
        <div className="section-title">
          <i className="fa-solid fa-fire-flame-curved" />
          Featured Offers
        </div>
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
      </div>

      <div className="section-block">
        <div className="section-title">
          <i className="fa-solid fa-fire" />
          Hot Walls
        </div>
        <OfferWalls offers={hot} idPrefix="hot_" onSelect={onSelectOffer} />
      </div>

      <div className="section-block">
        <div className="section-title">
          <i className="fa-solid fa-layer-group" />
          All Walls
        </div>
        <OfferWalls offers={offers} idPrefix="all_" onSelect={onSelectOffer} />
      </div>
    </div>
  );
}
