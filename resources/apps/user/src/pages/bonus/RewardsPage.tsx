import { useState } from 'react';
import { Loading, EmptyState } from '../../components/States';
import { useApiData } from '../../hooks/useApiData';
import { useAuth } from '../../hooks/useAuth';
import { api, ApiError } from '../../lib/api';
import { formatCoins } from '../../lib/format';
import { toast } from '../../store/toast';

interface Bonus {
  key: string;
  name: string;
  desc: string;
  coins: number;
  type: string;
  icon: string;
  color: string;
  claimed: boolean;
  canClaim: boolean;
  progress?: { current: number; req: number };
}

export function RewardsPage() {
  const { refreshBalance } = useAuth();
  const { data, loading, refetch } = useApiData<{ bonuses: Bonus[] }>('/bonuses');
  const [busy, setBusy] = useState<string | null>(null);
  const bonuses = data?.bonuses ?? [];

  const claim = async (b: Bonus) => {
    setBusy(b.key);
    try {
      await api(`/bonuses/${b.key}/claim`, { method: 'POST' });
      toast(`Claimed ${formatCoins(b.coins)} coins from ${b.name}!`, 'success');
      refreshBalance();
      refetch();
    } catch (e) {
      toast(e instanceof ApiError ? e.message : 'Claim failed.', 'error');
    } finally {
      setBusy(null);
    }
  };

  return (
    <div className="page-enter">
      <div className="section-title">
        <i className="fa-solid fa-award" />
        Rewards
      </div>
      {loading ? (
        <Loading />
      ) : bonuses.length === 0 ? (
        <EmptyState icon="fa-award" message="No rewards available yet." />
      ) : (
        <div className="rewards-list">
          {bonuses.map((b) => (
            <div className="reward-item" key={b.key}>
              <div className="reward-left">
                <div className="reward-icon" style={{ background: b.color + '22', color: b.color }}>
                  <i className={'fa-solid ' + b.icon} />
                </div>
                <div>
                  <div className="reward-name">{b.name}</div>
                  <div className="reward-desc">
                    {b.desc}
                    {b.progress ? ` (${b.progress.current}/${b.progress.req})` : ''}
                  </div>
                </div>
              </div>
              <div className="reward-right">
                <div className="reward-coins">+{formatCoins(b.coins)}</div>
                {b.claimed ? (
                  <div className="reward-status available">Claimed</div>
                ) : (
                  <div>
                    <div className={'reward-status ' + (b.canClaim ? 'available' : 'locked')}>
                      {b.canClaim ? 'Available' : 'Locked'}
                    </div>
                    <button
                      className="claim-btn"
                      disabled={!b.canClaim || busy === b.key}
                      onClick={() => claim(b)}
                    >
                      {busy === b.key ? '…' : 'Claim'}
                    </button>
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
