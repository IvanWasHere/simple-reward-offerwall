import { useNavigate } from 'react-router-dom';
import type { Offerwall } from '../lib/types';

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

/**
 * A grid of offerwall "wall" buttons. Each opens the provider's offerwall inside
 * the in-app iframe view (/reward/offerwall/:id).
 */
export function OfferwallButtons({ offerwalls }: { offerwalls: Offerwall[] }) {
  const navigate = useNavigate();

  return (
    <div className="offers-grid">
      {offerwalls.map((ow) => {
        const p = PALETTE[hash(ow.name) % PALETTE.length];
        return (
          <button
            key={ow.id}
            className="offer-card"
            style={{ textAlign: 'left', width: '100%', font: 'inherit', color: 'inherit' }}
            onClick={() => navigate(`/offerwall/${ow.id}`, { state: { name: ow.name } })}
          >
            <div className="wall-icon" style={{ background: p.bg, color: p.color }}>
              <i className={'fa-solid ' + p.icon} />
            </div>
            <div className="offer-card-info">
              <div className="offer-card-name">{ow.name}</div>
              <div className="offer-card-desc">Open offerwall</div>
            </div>
            <div className="offer-card-right">
              <i
                className="fa-solid fa-arrow-up-right-from-square"
                style={{ color: 'var(--accent)', fontSize: 16 }}
              />
            </div>
          </button>
        );
      })}
    </div>
  );
}
