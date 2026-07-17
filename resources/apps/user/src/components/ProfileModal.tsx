import { useState } from 'react';
import { Modal } from './Modal';
import { formatCoins } from '../lib/format';
import { useAuth } from '../hooks/useAuth';

export function ProfileModal({
  completed,
  onClose,
}: {
  completed: number;
  onClose: () => void;
}) {
  const { user, balance, logout } = useAuth();
  const [busy, setBusy] = useState(false);

  if (!user) return null;

  return (
    <Modal onClose={onClose} variant="box">
      <div className="profile-avatar-large">
        <i className="fa-solid fa-user" />
      </div>
      <div className="profile-name">{user.displayName || user.email}</div>
      <div className="profile-joined">{user.email}</div>
      <div className="profile-stats">
        <div className="profile-stat">
          <div className="profile-stat-value">{formatCoins(balance)}</div>
          <div className="profile-stat-label">Coins</div>
        </div>
        <div className="profile-stat">
          <div className="profile-stat-value">{completed}</div>
          <div className="profile-stat-label">Rewards</div>
        </div>
        <div className="profile-stat">
          <div className="profile-stat-value" style={{ textTransform: 'capitalize' }}>
            {user.status}
          </div>
          <div className="profile-stat-label">Status</div>
        </div>
      </div>
      <div style={{ textAlign: 'center', paddingTop: 12, borderTop: '1px solid var(--border)' }}>
        <button
          style={{
            padding: '10px 24px',
            borderRadius: 'var(--radius-sm)',
            background: 'rgba(255,45,108,0.1)',
            border: '1px solid rgba(255,45,108,0.2)',
            color: 'var(--danger)',
            fontSize: 13,
            fontWeight: 600,
            cursor: 'pointer',
            fontFamily: '"DM Sans", sans-serif',
          }}
          disabled={busy}
          onClick={async () => {
            setBusy(true);
            await logout();
          }}
        >
          {busy ? 'Signing out…' : 'Sign out'}
        </button>
      </div>
    </Modal>
  );
}
