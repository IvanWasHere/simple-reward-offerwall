import { formatCoins, platformClass, titleCase } from '../lib/format';
import type { UiOffer } from '../lib/types';

const FALLBACK_ICON =
  "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='56'%3E%3Crect width='56' height='56' rx='10' fill='%23003356'/%3E%3C/svg%3E";

export function OfferCard({ offer, onClick }: { offer: UiOffer; onClick: () => void }) {
  return (
    <div className="offer-card" onClick={onClick}>
      <img
        className="offer-card-icon"
        src={offer.icon || FALLBACK_ICON}
        alt={offer.name}
        loading="lazy"
        onError={(e) => {
          (e.currentTarget as HTMLImageElement).src = FALLBACK_ICON;
        }}
      />
      <div className="offer-card-info">
        <div className="offer-card-name">{offer.name}</div>
        <div className="offer-card-desc">
          {offer.description || `Earn with ${offer.providerName}`}
        </div>
        <div className="offer-card-meta">
          {offer.platforms.map((p) => (
            <span key={p} className={'offer-tag ' + platformClass(p)}>
              {titleCase(p)}
            </span>
          ))}
          {offer.rating && (
            <span className="offer-tag">
              <i
                className="fa-solid fa-star"
                style={{ color: '#ffd700', fontSize: 10, marginRight: 3 }}
              />
              {offer.rating}
            </span>
          )}
        </div>
      </div>
      <div className="offer-card-right">
        <div className="offer-card-payout">+{formatCoins(offer.coins)}</div>
        <div className="offer-card-payout-label">coins</div>
        {offer.tasks.length > 0 && (
          <div className="offer-card-tasks">
            <i className="fa-solid fa-list-check" />
            {offer.tasks.length} task{offer.tasks.length > 1 ? 's' : ''}
          </div>
        )}
      </div>
    </div>
  );
}
