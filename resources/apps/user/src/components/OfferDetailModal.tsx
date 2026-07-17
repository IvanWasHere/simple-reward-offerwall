import { useState } from 'react';
import { Modal } from './Modal';
import { api, ApiError } from '../lib/api';
import { formatCoins, platformClass, titleCase } from '../lib/format';
import { toast } from '../store/toast';
import type { UiOffer } from '../lib/types';

/**
 * Offer detail modal. "Start Offer" records a click server-side (POST /clicks),
 * which returns the tamper-proof outbound URL, then opens it in a new tab.
 */
export function OfferDetailModal({ offer, onClose }: { offer: UiOffer; onClose: () => void }) {
  const [busy, setBusy] = useState(false);

  const start = async () => {
    if (!offer.providerId || !offer.providerOfferId) {
      toast('This offer is not available right now.', 'error');
      return;
    }
    setBusy(true);
    try {
      const res = await api<{ url: string }>('/clicks', {
        method: 'POST',
        body: { provider_id: offer.providerId, provider_offer_id: offer.providerOfferId },
      });
      window.open(res.url, '_blank', 'noopener,noreferrer');
      toast('Offer opened in a new tab. Coins land on completion.', 'success');
      onClose();
    } catch (e) {
      toast(e instanceof ApiError ? e.message : 'Could not open the offer.', 'error');
    } finally {
      setBusy(false);
    }
  };

  return (
    <Modal onClose={onClose} variant="detail">
      {offer.iconLarge ? (
        <img className="detail-banner" src={offer.iconLarge} alt={offer.name} />
      ) : (
        <div
          className="detail-banner"
          style={{
            background: 'var(--bg-elevated)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
          }}
        >
          {offer.icon && (
            <img
              src={offer.icon}
              style={{ width: 80, height: 80, borderRadius: 16 }}
              alt={offer.name}
            />
          )}
        </div>
      )}
      <div className="detail-body">
        <div className="detail-icon-row">
          {offer.icon && <img className="detail-icon" src={offer.icon} alt={offer.name} />}
          <div className="detail-name">{offer.name}</div>
        </div>
        <div className="detail-payout-big">+{formatCoins(offer.coins)} coins</div>

        <div className="detail-platforms">
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

        {offer.screenshots.length > 0 && (
          <div>
            <div className="detail-section-label">Screenshots</div>
            <div className="detail-screenshots">
              {offer.screenshots.map((s, i) => (
                <img
                  key={i}
                  className="detail-screenshot"
                  src={s}
                  alt="Screenshot"
                  loading="lazy"
                />
              ))}
            </div>
          </div>
        )}

        {(offer.instructions || offer.description) && (
          <div>
            <div className="detail-section-label">How to Complete</div>
            <div className="detail-instructions">{offer.instructions || offer.description}</div>
          </div>
        )}

        {offer.tasks.length > 0 && (
          <div>
            <div className="detail-section-label">Tasks</div>
            <div className="detail-tasks-list">
              {offer.tasks.map((t, i) => (
                <div className="detail-task" key={i}>
                  <div className="detail-task-name">
                    <i
                      className="fa-solid fa-circle-check"
                      style={{ color: 'var(--accent-dim)', marginRight: 8, fontSize: 13 }}
                    />
                    {t.name}
                  </div>
                  {typeof t.coins === 'number' && t.coins > 0 && (
                    <div className="detail-task-payout">+{formatCoins(t.coins)}</div>
                  )}
                </div>
              ))}
            </div>
          </div>
        )}

        <button className="detail-go-btn" onClick={start} disabled={busy}>
          {busy ? 'Opening…' : 'Start Offer'}
        </button>
      </div>
    </Modal>
  );
}
