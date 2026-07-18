import { NavLink } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';

const SECTIONS = [
  {
    label: 'Overview',
    items: [{ to: '/', icon: 'fa-chart-pie', text: 'Dashboard', end: true }],
  },
  {
    label: 'Management',
    items: [
      { to: '/offers', icon: 'fa-gift', text: 'Offers', end: false },
      { to: '/providers', icon: 'fa-server', text: 'Providers', end: false },
      { to: '/callbacks', icon: 'fa-arrow-right-arrow-left', text: 'Callbacks', end: false },
      { to: '/rewards', icon: 'fa-award', text: 'Rewards', end: false },
    ],
  },
  {
    label: 'Finance',
    items: [
      { to: '/redemptions', icon: 'fa-money-bill-transfer', text: 'Redemptions', end: false },
      { to: '/payouts', icon: 'fa-gifts', text: 'Payouts', end: false },
    ],
  },
  {
    label: 'People',
    items: [
      { to: '/users', icon: 'fa-users', text: 'Users', end: false },
      { to: '/support', icon: 'fa-life-ring', text: 'Support', end: false },
    ],
  },
  {
    label: 'System',
    items: [{ to: '/settings', icon: 'fa-gear', text: 'Settings', end: false }],
  },
];

export function Sidebar({ open, onNavigate }: { open: boolean; onNavigate: () => void }) {
  const { user } = useAuth();
  const initial = (user?.displayName || user?.email || 'A').charAt(0).toUpperCase();

  return (
    <aside className={'sidebar' + (open ? ' open' : '')}>
      <div className="sidebar-brand">
        <i className="fa-solid fa-vault" />
        <span>RewardVault</span>
        <span className="sidebar-badge">Admin</span>
      </div>
      <nav className="sidebar-nav">
        {SECTIONS.map((sec) => (
          <div key={sec.label}>
            <div className="sidebar-label">{sec.label}</div>
            {sec.items.map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.end}
                onClick={onNavigate}
                className={({ isActive }) => 'sidebar-link' + (isActive ? ' active' : '')}
              >
                <i className={'fa-solid ' + item.icon} />
                {item.text}
              </NavLink>
            ))}
          </div>
        ))}
      </nav>
      <div className="sidebar-footer">
        <div className="sidebar-footer-avatar">{initial}</div>
        <div className="sidebar-footer-info">
          <div className="sidebar-footer-name">{user?.displayName || 'Admin'}</div>
          <div className="sidebar-footer-role">{user?.type || 'admin'}</div>
        </div>
      </div>
    </aside>
  );
}
