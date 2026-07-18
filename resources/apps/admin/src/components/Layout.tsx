import { useEffect, useState } from 'react';
import { Outlet, useLocation } from 'react-router-dom';
import { Sidebar } from './Sidebar';
import { ToastHost } from './ToastHost';
import { ConfirmDialog } from './ConfirmDialog';
import { useAuth } from '../hooks/useAuth';

const TITLES: Record<string, string> = {
  '/': 'Dashboard',
  '/offers': 'Offers',
  '/providers': 'Providers',
  '/callbacks': 'Callbacks',
  '/rewards': 'Rewards',
  '/redemptions': 'Redemptions',
  '/payouts': 'Payouts',
  '/users': 'Users',
  '/support': 'Support Requests',
  '/settings': 'Settings',
};

export function Layout() {
  const [open, setOpen] = useState(false);
  const location = useLocation();
  const { user, logout } = useAuth();

  // Close the mobile drawer on navigation.
  useEffect(() => {
    setOpen(false);
  }, [location.pathname]);

  const base = '/' + (location.pathname.split('/')[1] || '');
  const title = TITLES[location.pathname] ?? TITLES[base] ?? 'Admin';

  return (
    <>
      <button className="mobile-toggle" aria-label="Menu" onClick={() => setOpen((o) => !o)}>
        <i className="fa-solid fa-bars" />
      </button>
      <Sidebar open={open} onNavigate={() => setOpen(false)} />
      <div className="main">
        <div className="topbar">
          <div className="topbar-title">{title}</div>
          <div className="topbar-actions">
            <span style={{ fontSize: 13, color: 'var(--text-sec)' }}>{user?.email}</span>
            <button className="btn btn-secondary btn-sm" onClick={logout}>
              <i className="fa-solid fa-arrow-right-from-bracket" style={{ marginRight: 6 }} />
              Sign out
            </button>
          </div>
        </div>
        <div className="content">
          <Outlet />
        </div>
      </div>
      <ConfirmDialog />
      <ToastHost />
    </>
  );
}
