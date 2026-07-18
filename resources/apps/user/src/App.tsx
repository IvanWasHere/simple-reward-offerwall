import { useState } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { Backgrounds } from './components/Backgrounds';
import { Navbar } from './components/Navbar';
import { BalanceBar } from './components/BalanceBar';
import { ToastHost } from './components/ToastHost';
import { OfferDetailModal } from './components/OfferDetailModal';
import { ProfileModal } from './components/ProfileModal';
import { AuthScreens } from './auth/AuthScreens';
import { AuthProvider, useAuth } from './hooks/useAuth';
import { useApiData } from './hooks/useApiData';
import { useTheme } from './lib/theme';
import { config } from './lib/config';
import { EarnPage } from './pages/EarnPage';
import { OfferwallView } from './pages/OfferwallView';
import { OffersPage } from './pages/OffersPage';
import { SurveysPage } from './pages/SurveysPage';
import { WithdrawPage } from './pages/WithdrawPage';
import { LeaderboardPage } from './pages/bonus/LeaderboardPage';
import { WheelPage } from './pages/bonus/WheelPage';
import { RewardsPage } from './pages/bonus/RewardsPage';
import { ReferralPage } from './pages/bonus/ReferralPage';
import type { UiOffer } from './lib/types';

function basename(): string {
  try {
    return new URL(config().rewardUrl).pathname.replace(/\/$/, '') || '/';
  } catch {
    return '/reward';
  }
}

function FullScreenSpinner() {
  return (
    <div
      style={{
        minHeight: '100vh',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        position: 'relative',
        zIndex: 1,
      }}
    >
      <i className="fa-solid fa-circle-notch fa-spin" style={{ fontSize: 32, color: 'var(--accent)' }} />
    </div>
  );
}

function AuthedApp() {
  const { theme, toggle } = useTheme();
  const { balance } = useAuth();
  const [selected, setSelected] = useState<UiOffer | null>(null);
  const [showProfile, setShowProfile] = useState(false);

  // Approved-rewards count for the balance bar + profile.
  const rewards = useApiData<{ rewards: Array<{ status: string }> }>('/me/rewards');
  const rewardCount = rewards.data?.rewards.length ?? 0;

  return (
    <BrowserRouter basename={basename()}>
      <Navbar theme={theme} onToggleTheme={toggle} onOpenProfile={() => setShowProfile(true)} />
      <main className="main-content">
        <BalanceBar balance={balance} completed={rewardCount} />
        <Routes>
          <Route path="/" element={<EarnPage onSelectOffer={setSelected} />} />
          <Route path="/offerwall/:id" element={<OfferwallView />} />
          <Route path="/offers" element={<OffersPage onSelectOffer={setSelected} />} />
          <Route path="/surveys" element={<SurveysPage />} />
          <Route path="/withdraw" element={<WithdrawPage />} />
          <Route path="/bonus" element={<Navigate to="/bonus/leaderboard" replace />} />
          <Route path="/bonus/leaderboard" element={<LeaderboardPage />} />
          <Route path="/bonus/wheel" element={<WheelPage />} />
          <Route path="/bonus/rewards" element={<RewardsPage />} />
          <Route path="/bonus/referral" element={<ReferralPage />} />
          <Route path="*" element={<EarnPage onSelectOffer={setSelected} />} />
        </Routes>
      </main>

      {selected && <OfferDetailModal offer={selected} onClose={() => setSelected(null)} />}
      {showProfile && <ProfileModal completed={rewardCount} onClose={() => setShowProfile(false)} />}
    </BrowserRouter>
  );
}

function Gate() {
  const { user, loading, setUser } = useAuth();
  if (loading) return <FullScreenSpinner />;
  if (!user) return <AuthScreens onAuthenticated={setUser} />;
  // Admins belong in the admin dashboard, not the user app.
  if (user.type === 'admin') {
    window.location.replace(config().adminUrl);
    return <FullScreenSpinner />;
  }
  return <AuthedApp />;
}

export function App() {
  return (
    <AuthProvider>
      <Backgrounds />
      <Gate />
      <ToastHost />
    </AuthProvider>
  );
}
