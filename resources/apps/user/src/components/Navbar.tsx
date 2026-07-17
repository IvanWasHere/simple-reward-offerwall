import { useEffect, useRef, useState } from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import type { Theme } from '../lib/theme';

const LINKS = [
  { to: '/', label: 'Earn', icon: 'fa-coins', end: true },
  { to: '/offers', label: 'Offers', icon: 'fa-gift', end: false },
  { to: '/surveys', label: 'Surveys', icon: 'fa-clipboard-list', end: false },
  { to: '/withdraw', label: 'Withdraw', icon: 'fa-wallet', end: false },
];

const BONUS_ITEMS = [
  { to: '/bonus/leaderboard', label: 'Leaderboard', icon: 'fa-ranking-star' },
  { to: '/bonus/wheel', label: 'Lucky Wheel', icon: 'fa-dharmachakra' },
  { to: '/bonus/rewards', label: 'Rewards', icon: 'fa-award' },
  { to: '/bonus/referral', label: 'Refer a Friend', icon: 'fa-user-plus' },
];

function BonusDropdown() {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement | null>(null);
  const location = useLocation();
  const active = location.pathname.startsWith('/bonus');

  // Close on outside click.
  useEffect(() => {
    if (!open) return;
    const onDown = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', onDown);
    return () => document.removeEventListener('mousedown', onDown);
  }, [open]);

  // Close when the route changes (an item was chosen).
  useEffect(() => {
    setOpen(false);
  }, [location.pathname]);

  return (
    <div className="nav-dropdown" ref={ref}>
      <button
        type="button"
        className={'nav-link' + (active ? ' active' : '')}
        aria-haspopup="true"
        aria-expanded={open}
        onClick={() => setOpen((o) => !o)}
      >
        <i className="fa-solid fa-star" style={{ marginRight: 6, fontSize: 13 }} />
        <span>Bonus</span>
        <i className={'fa-solid fa-chevron-down nav-dropdown-caret' + (open ? ' open' : '')} />
      </button>
      {open && (
        <div className="nav-dropdown-menu" role="menu">
          {BONUS_ITEMS.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              role="menuitem"
              className={({ isActive }) => 'nav-dropdown-item' + (isActive ? ' active' : '')}
            >
              <i className={'fa-solid ' + item.icon} />
              {item.label}
            </NavLink>
          ))}
        </div>
      )}
    </div>
  );
}

interface NavbarProps {
  theme: Theme;
  onToggleTheme: () => void;
  onOpenProfile: () => void;
}

export function Navbar({ theme, onToggleTheme, onOpenProfile }: NavbarProps) {
  const isDark = theme === 'dark';
  return (
    <nav className="navbar">
      <div className="navbar-inner">
        <NavLink to="/" className="nav-brand" end>
          <i className="fa-solid fa-vault" />
          RewardVault
        </NavLink>
        <div className="nav-links">
          {LINKS.map((l) => (
            <NavLink
              key={l.to}
              to={l.to}
              end={l.end}
              className={({ isActive }) => 'nav-link' + (isActive ? ' active' : '')}
            >
              <i className={'fa-solid ' + l.icon} style={{ marginRight: 6, fontSize: 13 }} />
              <span>{l.label}</span>
            </NavLink>
          ))}
          <BonusDropdown />
          <div className="nav-sep" />
          <button className="theme-toggle" aria-label="Toggle theme" onClick={onToggleTheme}>
            <i className={'fa-solid ' + (isDark ? 'fa-sun' : 'fa-moon')} />
          </button>
          <button className="nav-profile" aria-label="Profile" onClick={onOpenProfile}>
            <i className="fa-solid fa-user" />
          </button>
        </div>
      </div>
    </nav>
  );
}
