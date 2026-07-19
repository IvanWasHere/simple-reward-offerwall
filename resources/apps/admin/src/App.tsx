import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { Layout } from './components/Layout';
import { AdminLogin } from './auth/AdminLogin';
import { AuthProvider, useAuth } from './hooks/useAuth';
import { config } from './lib/config';
import { DashboardPage } from './pages/DashboardPage';
import { ProvidersPage } from './pages/ProvidersPage';
import { ProviderDetailPage } from './pages/providers/ProviderDetailPage';
import { OffersPage } from './pages/OffersPage';
import { CallbacksPage } from './pages/CallbacksPage';
import { RewardsPage } from './pages/RewardsPage';
import { RedemptionsPage } from './pages/RedemptionsPage';
import { PayoutsPage } from './pages/PayoutsPage';
import { UsersPage } from './pages/UsersPage';
import { UserDetailPage } from './pages/users/UserDetailPage';
import { SupportPage } from './pages/SupportPage';
import { SettingsPage } from './pages/SettingsPage';

function basename(): string {
  try {
    return new URL(config().adminUrl).pathname.replace(/\/$/, '') || '/';
  } catch {
    return '/offerwall-admin';
  }
}

function FullScreen({ children }: { children: React.ReactNode }) {
  return (
    <div
      style={{
        minHeight: '100vh',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        background: 'var(--bg)',
      }}
    >
      {children}
    </div>
  );
}

function Gate() {
  const { user, loading, setUser } = useAuth();

  if (loading) {
    return (
      <FullScreen>
        <i className="fa-solid fa-circle-notch fa-spin" style={{ fontSize: 32, color: 'var(--accent)' }} />
      </FullScreen>
    );
  }
  if (!user) return <AdminLogin onDone={setUser} />;
  // Non-admins belong in the user app — send them there.
  if (user.type !== 'admin') {
    window.location.replace(config().rewardUrl);
    return (
      <FullScreen>
        <i className="fa-solid fa-circle-notch fa-spin" style={{ fontSize: 32, color: 'var(--accent)' }} />
      </FullScreen>
    );
  }

  return (
    <BrowserRouter basename={basename()}>
      <Routes>
        <Route element={<Layout />}>
          <Route path="/" element={<DashboardPage />} />
          <Route path="/providers" element={<ProvidersPage />} />
          <Route path="/providers/new" element={<ProviderDetailPage />} />
          <Route path="/providers/:id" element={<ProviderDetailPage />} />
          <Route path="/offers" element={<OffersPage />} />
          <Route path="/callbacks" element={<CallbacksPage />} />
          <Route path="/rewards" element={<RewardsPage />} />
          <Route path="/redemptions" element={<RedemptionsPage />} />
          <Route path="/payouts" element={<PayoutsPage />} />
          <Route path="/users" element={<UsersPage />} />
          <Route path="/users/:id" element={<UserDetailPage />} />
          <Route path="/support" element={<SupportPage />} />
          <Route path="/settings" element={<SettingsPage />} />
          <Route path="*" element={<DashboardPage />} />
        </Route>
      </Routes>
    </BrowserRouter>
  );
}

export function App() {
  return (
    <AuthProvider>
      <Gate />
    </AuthProvider>
  );
}
