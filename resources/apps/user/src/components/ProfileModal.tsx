import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Modal } from './Modal';
import { formatCoins } from '../lib/format';
import { useAuth } from '../hooks/useAuth';
import { api, ApiError } from '../lib/api';
import { toast } from '../store/toast';
import type { User } from '../lib/types';

const inputStyle: React.CSSProperties = {
  width: '100%',
  padding: '11px 14px',
  marginTop: 6,
  background: 'var(--input-bg)',
  border: '1px solid var(--border)',
  borderRadius: 'var(--radius-sm)',
  color: 'var(--text)',
  fontSize: 14,
  fontFamily: '"DM Sans", sans-serif',
  outline: 'none',
};
const labelStyle: React.CSSProperties = {
  fontSize: 12,
  fontWeight: 600,
  color: 'var(--text-muted)',
  textTransform: 'uppercase',
  letterSpacing: '0.5px',
};

export function ProfileModal({ completed, onClose }: { completed: number; onClose: () => void }) {
  const { user, balance, logout, setUser } = useAuth();
  const navigate = useNavigate();
  const [editing, setEditing] = useState(false);
  const [name, setName] = useState(user?.displayName ?? '');
  const [email, setEmail] = useState(user?.email ?? '');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [signingOut, setSigningOut] = useState(false);

  if (!user) return null;

  const save = async () => {
    setBusy(true);
    setError(null);
    try {
      const res = await api<{ user: User }>('/me/profile', {
        method: 'PUT',
        body: { display_name: name, email },
      });
      setUser(res.user);
      setEditing(false);
      toast('Profile updated.', 'success');
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Update failed.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <Modal onClose={onClose} variant="box">
      <div className="profile-avatar-large">
        <i className="fa-solid fa-user" />
      </div>

      {editing ? (
        <div style={{ marginBottom: 20 }}>
          {error && (
            <div
              style={{
                background: 'var(--highlight-glow)',
                border: '1px solid var(--danger)',
                color: 'var(--danger)',
                borderRadius: 'var(--radius-sm)',
                padding: '10px 12px',
                fontSize: 13,
                marginBottom: 12,
              }}
            >
              {error}
            </div>
          )}
          <label style={{ display: 'block', marginBottom: 12 }}>
            <span style={labelStyle}>Name</span>
            <input style={inputStyle} value={name} onChange={(e) => setName(e.target.value)} />
          </label>
          <label style={{ display: 'block' }}>
            <span style={labelStyle}>Email</span>
            <input style={inputStyle} type="email" value={email} onChange={(e) => setEmail(e.target.value)} />
          </label>
          <div style={{ display: 'flex', gap: 8, marginTop: 16 }}>
            <button className="detail-go-btn" style={{ flex: 1 }} disabled={busy} onClick={save}>
              {busy ? 'Saving…' : 'Save'}
            </button>
            <button
              className="claim-btn"
              style={{ marginTop: 0, background: 'var(--bg-elevated)', color: 'var(--text-sec)' }}
              disabled={busy}
              onClick={() => {
                setEditing(false);
                setName(user.displayName);
                setEmail(user.email);
                setError(null);
              }}
            >
              Cancel
            </button>
          </div>
        </div>
      ) : (
        <>
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

          <div style={{ display: 'flex', flexDirection: 'column', gap: 8, marginBottom: 16 }}>
            <button className="detail-go-btn" onClick={() => setEditing(true)}>
              <i className="fa-solid fa-pen" style={{ marginRight: 8 }} />
              Edit profile
            </button>
            <button
              className="claim-btn"
              style={{ marginTop: 0, padding: '12px', background: 'var(--bg-elevated)', color: 'var(--text)', border: '1px solid var(--border)' }}
              onClick={() => {
                onClose();
                navigate('/support');
              }}
            >
              <i className="fa-solid fa-life-ring" style={{ marginRight: 8 }} />
              Support &amp; help
            </button>
          </div>
        </>
      )}

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
          disabled={signingOut}
          onClick={async () => {
            setSigningOut(true);
            await logout();
          }}
        >
          {signingOut ? 'Signing out…' : 'Sign out'}
        </button>
      </div>
    </Modal>
  );
}
