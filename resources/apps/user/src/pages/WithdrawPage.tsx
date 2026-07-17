import { useState } from 'react';
import { Loading, EmptyState, ErrorState } from '../components/States';
import { useApiData } from '../hooks/useApiData';
import { useAuth } from '../hooks/useAuth';
import { api, ApiError } from '../lib/api';
import { formatCoins } from '../lib/format';
import { toast } from '../store/toast';
import type { Payout, Redemption } from '../lib/types';

function money(minor: number, currency: string): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency || 'USD',
  }).format((minor || 0) / 100);
}

export function WithdrawPage() {
  const { balance, refreshBalance } = useAuth();
  const catalog = useApiData<{ payouts: Payout[]; balance: number }>('/payouts');
  const history = useApiData<{ redemptions: Redemption[] }>('/me/redemptions');
  const [busyId, setBusyId] = useState<number | null>(null);

  const redeem = async (p: Payout) => {
    if (balance < p.valueCoins) {
      toast(`Need ${formatCoins(p.valueCoins - balance)} more coins for ${p.name}`, 'error');
      return;
    }
    setBusyId(p.id);
    try {
      await api('/redemptions', { method: 'POST', body: { payout_id: p.id } });
      toast(`Redemption for ${p.name} submitted!`, 'success');
      refreshBalance();
      catalog.refetch();
      history.refetch();
    } catch (e) {
      toast(e instanceof ApiError ? e.message : 'Redemption failed.', 'error');
    } finally {
      setBusyId(null);
    }
  };

  return (
    <div className="page-enter">
      <div
        style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          marginBottom: 20,
          flexWrap: 'wrap',
          gap: 12,
        }}
      >
        <div className="section-title" style={{ marginBottom: 0 }}>
          <i className="fa-solid fa-wallet" />
          Withdraw
        </div>
        <div
          style={{
            background: 'var(--bg-card)',
            border: '1px solid var(--border)',
            borderRadius: 'var(--radius-sm)',
            padding: '10px 18px',
          }}
        >
          <span style={{ fontSize: 13, color: 'var(--text-muted)', marginRight: 8 }}>
            Balance:
          </span>
          <span
            style={{
              fontFamily: '"Space Grotesk", sans-serif',
              fontWeight: 700,
              color: 'var(--accent)',
              fontSize: 18,
            }}
          >
            {formatCoins(balance)} coins
          </span>
        </div>
      </div>

      {catalog.loading ? (
        <Loading label="Loading rewards…" />
      ) : catalog.error ? (
        <ErrorState message={catalog.error} />
      ) : (catalog.data?.payouts.length ?? 0) === 0 ? (
        <EmptyState icon="fa-wallet" message="No payout options available yet." />
      ) : (
        <div className="withdraw-grid">
          {catalog.data!.payouts.map((p) => {
            const canAfford = balance >= p.valueCoins;
            const icon = p.smallIcon || p.midsizeIcon || p.largeIcon;
            return (
              <div
                key={p.id}
                className="gc-card"
                style={{ opacity: canAfford ? 1 : 0.45 }}
                onClick={() => busyId === null && redeem(p)}
              >
                {icon ? (
                  <img
                    src={icon}
                    alt={p.name}
                    className="gc-logo"
                    style={{ objectFit: 'contain' }}
                  />
                ) : (
                  <div
                    className="gc-logo"
                    style={{ background: 'var(--accent-glow)', color: 'var(--accent)' }}
                  >
                    {p.name.charAt(0)}
                  </div>
                )}
                <div className="gc-name">{p.name}</div>
                <div className="gc-min">{money(p.valueMoney, p.currency)}</div>
                <div className="gc-min">{formatCoins(p.valueCoins)} coins</div>
              </div>
            );
          })}
        </div>
      )}

      <div className="section-block" style={{ marginTop: 32 }}>
        <div className="section-title">
          <i className="fa-solid fa-clock-rotate-left" />
          Redemption History
        </div>
        {history.loading ? (
          <Loading />
        ) : (history.data?.redemptions.length ?? 0) === 0 ? (
          <EmptyState icon="fa-clock-rotate-left" message="No redemptions yet." />
        ) : (
          <div className="rewards-list">
            {history.data!.redemptions.map((r) => (
              <div className="reward-item" key={r.id}>
                <div className="reward-left">
                  <div
                    className="reward-icon"
                    style={{ background: 'var(--accent-glow)', color: 'var(--accent)' }}
                  >
                    <i className="fa-solid fa-gift" />
                  </div>
                  <div>
                    <div className="reward-name">{r.payout_name || 'Payout'}</div>
                    <div className="reward-desc">
                      {new Date(r.created_at + 'Z').toLocaleDateString()}
                    </div>
                  </div>
                </div>
                <div className="reward-right">
                  <div className="reward-coins">-{formatCoins(r.coins_spent)}</div>
                  <div
                    className={
                      'reward-status ' + (r.status === 'rejected' ? 'locked' : 'available')
                    }
                    style={{ textTransform: 'capitalize' }}
                  >
                    {r.status}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
