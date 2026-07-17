import { NavLink } from 'react-router-dom';
import type { Theme } from '../lib/theme';

const LINKS = [
  { to: '/', label: 'Earn', icon: 'fa-coins', end: true },
  { to: '/offers', label: 'Offers', icon: 'fa-gift', end: false },
  { to: '/surveys', label: 'Surveys', icon: 'fa-clipboard-list', end: false },
  { to: '/withdraw', label: 'Withdraw', icon: 'fa-wallet', end: false },
  { to: '/bonus', label: 'Bonus', icon: 'fa-star', end: false },
];

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
              <i
                className={'fa-solid ' + l.icon}
                style={{ marginRight: 6, fontSize: 13 }}
              />
              <span>{l.label}</span>
            </NavLink>
          ))}
          <div className="nav-sep" />
          <button
            className="theme-toggle"
            aria-label="Toggle theme"
            onClick={onToggleTheme}
          >
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
