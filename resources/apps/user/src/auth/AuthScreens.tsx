import { useState, type ReactNode, type CSSProperties, type FormEvent } from 'react';
import { api, ApiError } from '../lib/api';
import type { User } from '../lib/types';

type Mode = 'login' | 'register' | 'forgot' | 'reset';

function initialMode(): { mode: Mode; token: string } {
  const params = new URLSearchParams(window.location.search);
  const token = params.get('token') || '';
  if (token) return { mode: 'reset', token };
  return { mode: 'login', token: '' };
}

const inputStyle: CSSProperties = {
  width: '100%',
  padding: '11px 14px',
  marginTop: 6,
  marginBottom: 14,
  background: 'var(--input-bg)',
  border: '1px solid var(--border)',
  borderRadius: 'var(--radius-sm)',
  color: 'var(--text)',
  fontSize: 14,
  fontFamily: '"DM Sans", sans-serif',
  outline: 'none',
};

const labelStyle: CSSProperties = {
  fontSize: 12,
  fontWeight: 600,
  color: 'var(--text-muted)',
  textTransform: 'uppercase',
  letterSpacing: '0.5px',
};

function Field({
  label,
  type = 'text',
  value,
  onChange,
  autoComplete,
}: {
  label: string;
  type?: string;
  value: string;
  onChange: (v: string) => void;
  autoComplete?: string;
}) {
  return (
    <label style={{ display: 'block' }}>
      <span style={labelStyle}>{label}</span>
      <input
        style={inputStyle}
        type={type}
        value={value}
        autoComplete={autoComplete}
        onChange={(e) => onChange(e.target.value)}
      />
    </label>
  );
}

function Shell({ title, subtitle, children }: { title: string; subtitle?: string; children: ReactNode }) {
  return (
    <div style={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 24, position: 'relative', zIndex: 1 }}>
      <div className="modal-box" style={{ width: '100%', maxWidth: 400 }}>
        <div style={{ textAlign: 'center', marginBottom: 20 }}>
          <div style={{ fontFamily: '"Space Grotesk", sans-serif', fontWeight: 700, fontSize: 24, color: 'var(--accent)' }}>
            <i className="fa-solid fa-vault" style={{ marginRight: 8 }} />
            RewardVault
          </div>
          <h2 style={{ margin: '16px 0 4px', fontSize: 20 }}>{title}</h2>
          {subtitle && <p style={{ fontSize: 13, color: 'var(--text-muted)' }}>{subtitle}</p>}
        </div>
        {children}
      </div>
    </div>
  );
}

function PrimaryButton({ busy, children }: { busy: boolean; children: ReactNode }) {
  return (
    <button type="submit" className="detail-go-btn" disabled={busy} style={{ marginTop: 4 }}>
      {busy ? 'Please wait…' : children}
    </button>
  );
}

function ErrorLine({ message }: { message: string | null }) {
  if (!message) return null;
  return (
    <div
      style={{
        background: 'var(--highlight-glow)',
        border: '1px solid var(--danger)',
        color: 'var(--danger)',
        borderRadius: 'var(--radius-sm)',
        padding: '10px 12px',
        fontSize: 13,
        marginBottom: 14,
      }}
    >
      {message}
    </div>
  );
}

const linkStyle: CSSProperties = {
  color: 'var(--accent)',
  cursor: 'pointer',
  background: 'none',
  border: 'none',
  fontSize: 13,
  fontFamily: '"DM Sans", sans-serif',
  padding: 0,
};

export function AuthScreens({ onAuthenticated }: { onAuthenticated: (u: User) => void }) {
  const start = initialMode();
  const [mode, setMode] = useState<Mode>(start.mode);
  const [token] = useState(start.token);
  const [displayName, setDisplayName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const go = (m: Mode) => {
    setMode(m);
    setError(null);
    setNotice(null);
    setPassword('');
  };

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      if (mode === 'login') {
        const res = await api<{ user: User }>('/auth/login', { method: 'POST', body: { email, password } });
        onAuthenticated(res.user);
      } else if (mode === 'register') {
        const res = await api<{ user: User }>('/auth/register', {
          method: 'POST',
          body: { display_name: displayName, email, password },
        });
        onAuthenticated(res.user);
      } else if (mode === 'forgot') {
        const res = await api<{ message: string }>('/auth/forgot', { method: 'POST', body: { email } });
        setNotice(res.message || 'If that email exists, a reset link is on its way.');
      } else if (mode === 'reset') {
        const res = await api<{ message: string }>('/auth/reset', { method: 'POST', body: { token, password } });
        setNotice(res.message || 'Password updated. You can sign in now.');
        setMode('login');
      }
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Something went wrong.');
    } finally {
      setBusy(false);
    }
  };

  if (mode === 'register') {
    return (
      <Shell title="Create your account" subtitle="Earn coins and redeem real rewards">
        <ErrorLine message={error} />
        <form onSubmit={submit}>
          <Field label="Name" value={displayName} onChange={setDisplayName} autoComplete="name" />
          <Field label="Email" type="email" value={email} onChange={setEmail} autoComplete="email" />
          <Field label="Password" type="password" value={password} onChange={setPassword} autoComplete="new-password" />
          <PrimaryButton busy={busy}>Create account</PrimaryButton>
        </form>
        <p style={{ marginTop: 16, textAlign: 'center' }}>
          <button style={linkStyle} onClick={() => go('login')}>
            Already have an account? Sign in
          </button>
        </p>
      </Shell>
    );
  }

  if (mode === 'forgot') {
    return (
      <Shell title="Reset your password">
        {notice ? (
          <p style={{ fontSize: 14, color: 'var(--success)' }}>{notice}</p>
        ) : (
          <form onSubmit={submit}>
            <ErrorLine message={error} />
            <Field label="Email" type="email" value={email} onChange={setEmail} autoComplete="email" />
            <PrimaryButton busy={busy}>Send reset link</PrimaryButton>
          </form>
        )}
        <p style={{ marginTop: 16, textAlign: 'center' }}>
          <button style={linkStyle} onClick={() => go('login')}>
            Back to sign in
          </button>
        </p>
      </Shell>
    );
  }

  if (mode === 'reset') {
    return (
      <Shell title="Choose a new password">
        <ErrorLine message={error} />
        {notice && <p style={{ fontSize: 14, color: 'var(--success)', marginBottom: 12 }}>{notice}</p>}
        <form onSubmit={submit}>
          <Field label="New password" type="password" value={password} onChange={setPassword} autoComplete="new-password" />
          <PrimaryButton busy={busy}>Update password</PrimaryButton>
        </form>
        <p style={{ marginTop: 16, textAlign: 'center' }}>
          <button style={linkStyle} onClick={() => go('login')}>
            Back to sign in
          </button>
        </p>
      </Shell>
    );
  }

  return (
    <Shell title="Sign in" subtitle="Welcome back to RewardVault">
      <ErrorLine message={error} />
      <form onSubmit={submit}>
        <Field label="Email" type="email" value={email} onChange={setEmail} autoComplete="email" />
        <Field label="Password" type="password" value={password} onChange={setPassword} autoComplete="current-password" />
        <PrimaryButton busy={busy}>Sign in</PrimaryButton>
      </form>
      <p style={{ marginTop: 16, textAlign: 'center', display: 'flex', justifyContent: 'space-between' }}>
        <button style={linkStyle} onClick={() => go('register')}>
          Create an account
        </button>
        <button style={linkStyle} onClick={() => go('forgot')}>
          Forgot password?
        </button>
      </p>
    </Shell>
  );
}
