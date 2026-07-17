import { useState } from 'react';
import { OfferCard } from './OfferCard';
import { groupWalls } from '../lib/walls';
import type { UiOffer } from '../lib/types';

/** Collapsible per-provider offer walls (the prototype's accordion). */
export function OfferWalls({
  offers,
  idPrefix,
  onSelect,
}: {
  offers: UiOffer[];
  idPrefix: string;
  onSelect: (o: UiOffer) => void;
}) {
  const walls = groupWalls(offers);
  const [open, setOpen] = useState<Set<string>>(new Set());

  const toggle = (id: string) =>
    setOpen((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });

  return (
    <>
      {walls.map((wall) => {
        const key = idPrefix + wall.id;
        const isOpen = open.has(key);
        return (
          <div className="wall-section" key={key}>
            <div className="wall-header" onClick={() => toggle(key)}>
              <div className="wall-header-left">
                <div
                  className="wall-icon"
                  style={{ background: wall.bg, color: wall.color }}
                >
                  <i className={'fa-solid ' + wall.icon} />
                </div>
                <div>
                  <div className="wall-name">{wall.name}</div>
                  <div className="wall-count">{wall.offers.length} offers</div>
                </div>
              </div>
              <i className={'fa-solid fa-chevron-down wall-chevron' + (isOpen ? ' open' : '')} />
            </div>
            <div
              className={'wall-offers' + (isOpen ? ' open' : '')}
              style={isOpen ? { maxHeight: wall.offers.length * 108 + 40 } : undefined}
            >
              <div className="wall-offers-inner">
                {wall.offers.map((o) => (
                  <OfferCard key={o.key} offer={o} onClick={() => onSelect(o)} />
                ))}
              </div>
            </div>
          </div>
        );
      })}
    </>
  );
}
