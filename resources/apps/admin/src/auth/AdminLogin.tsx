import { useState, type FormEvent } from 'react';
import { api, ApiError } from '../lib/api';
import type { Me } from '../hooks/useAuth';

export function AdminLogin({ onDone }: { onDone: (u: Me) => void }) {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const res = await api<{ user: Me }>('/auth/login', { method: 'POST', body: { email, password } });
      onDone(res.user);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Something went wrong.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="auth-screen">
      <div className="modal-box auth-card">
        <div className="auth-brand">
          <i className="fa-solid fa-vault" />
          <span>RewardVault</span>
          <span className="sidebar-badge">Admin</span>
        </div>
        <h2 className="auth-title">Sign in</h2>
        <p className="auth-sub">Admin dashboard access</p>
        {error && <div className="auth-error">{error}</div>}
        <form onSubmit={submit}>
          <div className="form-group">
            <label className="form-label">Email</label>
            <input
              className="form-input"
              type="email"
              autoComplete="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
            />
          </div>
          <div className="form-group">
            <label className="form-label">Password</label>
            <input
              className="form-input"
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
          </div>
          <button className="btn btn-primary" type="submit" disabled={busy} style={{ width: '100%' }}>
            {busy ? 'Signing in…' : 'Sign in'}
          </button>
        </form>
      </div>
    </div>
  );
}
