import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useState,
  type ReactNode,
} from 'react';
import { api } from '../lib/api';
import type { User } from '../lib/types';

interface AuthState {
  user: User | null;
  loading: boolean;
  setUser: (u: User | null) => void;
  refreshBalance: () => void;
  balance: number;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthState | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const [balance, setBalance] = useState(0);

  const refreshBalance = useCallback(() => {
    api<{ balance: number }>('/me/balance')
      .then((r) => setBalance(r.balance))
      .catch(() => setBalance(0));
  }, []);

  useEffect(() => {
    api<{ user: User | null }>('/auth/me')
      .then((r) => setUser(r.user))
      .catch(() => setUser(null))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    if (user) refreshBalance();
    else setBalance(0);
  }, [user, refreshBalance]);

  const logout = useCallback(async () => {
    try {
      await api('/auth/session', { method: 'DELETE' });
    } catch {
      /* even if it fails, drop the local session */
    }
    setUser(null);
  }, []);

  return (
    <AuthContext.Provider
      value={{ user, loading, setUser, refreshBalance, balance, logout }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}
