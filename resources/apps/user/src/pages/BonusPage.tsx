import { useState } from 'react';
import { BonusDropdown } from '../components/BonusDropdown';
import { LuckyWheel } from '../components/LuckyWheel';
import { Loading, EmptyState } from '../components/States';
import { useApiData } from '../hooks/useApiData';
import { useAuth } from '../hooks/useAuth';
import { api, ApiError } from '../lib/api';
import { formatCoins } from '../lib/format';
import { toast } from '../store/toast';

interface LeaderRow {
  rank: number;
  name: string;
  earned: number;
  avatar: string;
}
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
interface Referral {
  code: string;
  referredCount: number;
  coinsEarned: number;
  shareUrl: string;
}

function Leaderboard() {
  const { data, loading } = useApiData<{ leaderboard: LeaderRow[] }>('/leaderboard');
  if (loading) return <Loading />;
  const rows = data?.leaderboard ?? [];
  if (rows.length === 0) return <EmptyState icon="fa-ranking-star" message="No rankings yet." />;
  return (
    <div style={{ overflowX: 'auto' }}>
      <table className="lb-table">
        <thead>
          <tr>
            <th>#</th>
            <th>User</th>
            <th style={{ textAlign: 'right' }}>Earned</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((u) => {
            const rc = u.rank === 1 ? 'gold' : u.rank === 2 ? 'silver' : u.rank === 3 ? 'bronze' : '';
            return (
              <tr key={u.rank}>
                <td>
                  <span className={'lb-rank ' + rc}>{u.rank}</span>
                </td>
                <td>
                  <div className="lb-user">
                    <div className="lb-avatar">{u.avatar}</div>
                    {u.name}
                  </div>
                </td>
                <td style={{ textAlign: 'right' }}>
                  <span className="lb-amount">{formatCoins(u.earned)} coins</span>
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}

function Rewards() {
  const { refreshBalance } = useAuth();
  const { data, loading, refetch } = useApiData<{ bonuses: Bonus[] }>('/bonuses');
  const [busy, setBusy] = useState<string | null>(null);

  if (loading) return <Loading />;
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
  );
}

function ReferralCard() {
  const { data, loading } = useApiData<{ referral: Referral }>('/me/referral');
  if (loading) return <Loading />;
  const r = data?.referral;
  if (!r) return <EmptyState icon="fa-user-plus" message="Referral unavailable." />;

  const copy = () => {
    navigator.clipboard?.writeText(r.shareUrl).then(
      () => toast('Referral link copied!', 'success'),
      () => toast('Could not copy link.', 'error')
    );
  };

  return (
    <div>
      <div className="profile-stats" style={{ marginBottom: 16 }}>
        <div className="profile-stat">
          <div className="profile-stat-value">{r.referredCount}</div>
          <div className="profile-stat-label">Referred</div>
        </div>
        <div className="profile-stat">
          <div className="profile-stat-value">{formatCoins(r.coinsEarned)}</div>
          <div className="profile-stat-label">Coins earned</div>
        </div>
        <div className="profile-stat">
          <div className="profile-stat-value" style={{ fontSize: 16 }}>
            {r.code}
          </div>
          <div className="profile-stat-label">Your code</div>
        </div>
      </div>
      <div
        className="detail-instructions"
        style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12 }}
      >
        <span style={{ wordBreak: 'break-all' }}>{r.shareUrl}</span>
        <button className="claim-btn" style={{ marginTop: 0, flexShrink: 0 }} onClick={copy}>
          Copy
        </button>
      </div>
    </div>
  );
}

export function BonusPage() {
  return (
    <div className="page-enter">
      <div className="section-title">
        <i className="fa-solid fa-star" />
        Bonus
      </div>

      <BonusDropdown
        icon="fa-ranking-star"
        iconColor="#ffd700"
        iconBg="rgba(255,215,0,0.1)"
        title="Leaderboard"
        subtitle="Top earners"
        maxHeight={600}
      >
        <Leaderboard />
      </BonusDropdown>

      <BonusDropdown
        icon="fa-dharmachakra"
        iconColor="#ff2d6c"
        iconBg="rgba(255,45,108,0.1)"
        title="Lucky Wheel"
        subtitle="Spin daily for free coins"
        maxHeight={520}
      >
        <div style={{ display: 'flex', justifyContent: 'center', padding: '20px 0' }}>
          <LuckyWheel />
        </div>
      </BonusDropdown>

      <BonusDropdown
        icon="fa-award"
        iconColor="#00b67a"
        iconBg="rgba(0,182,122,0.1)"
        title="Rewards"
        subtitle="Complete goals for bonus coins"
        maxHeight={800}
      >
        <Rewards />
      </BonusDropdown>

      <BonusDropdown
        icon="fa-user-plus"
        iconColor="#4179d6"
        iconBg="rgba(65,121,214,0.1)"
        title="Refer a Friend"
        subtitle="Earn coins when friends join"
        maxHeight={400}
      >
        <ReferralCard />
      </BonusDropdown>
    </div>
  );
}
