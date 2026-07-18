import { Link } from 'react-router-dom';
import { StatCard } from '../components/StatCard';
import { Loading, ErrorState } from '../components/States';
import { useApiData } from '../hooks/useApiData';
import { fmtCoins } from '../lib/format';

interface Stats {
  users: { total: number; user: number; support: number; admin: number; blocked: number };
  providers: { total: number; active: number };
  offers: { active: number };
  rewards: { pending: number; pendingCoins: number; approved: number };
  redemptions: { pending: number; pendingCoins: number };
  coins: { outstanding: number };
  callbacks: { total: number; last24h: number };
  supportTickets: { open: number; pending: number };
}

export function DashboardPage() {
  const { data, loading, error } = useApiData<{ stats: Stats }>('/admin/stats');

  if (loading) return <Loading label="Loading dashboard…" />;
  if (error || !data) return <ErrorState message={error || 'Failed to load stats.'} />;
  const s = data.stats;

  return (
    <>
      <div className="stats-grid">
        <StatCard label="Active Offers" value={s.offers.active} icon="fa-gift" iconBg="rgba(0,143,95,0.1)" iconColor="#008f5f" hint={`${s.providers.active} active providers`} />
        <StatCard label="Providers" value={`${s.providers.active}/${s.providers.total}`} icon="fa-server" iconBg="rgba(10,134,199,0.1)" iconColor="#0a86c7" hint="active / total" />
        <StatCard label="Total Users" value={s.users.total} icon="fa-users" iconBg="rgba(53,104,190,0.1)" iconColor="#3568be" hint={`${s.users.blocked} blocked`} />
        <StatCard label="Open Tickets" value={s.supportTickets.open} icon="fa-life-ring" iconBg="rgba(224,38,92,0.1)" iconColor="#e0265c" hint={`${s.supportTickets.pending} pending`} />
      </div>

      <div className="stats-grid" style={{ marginTop: 16 }}>
        <StatCard label="Pending Rewards" value={s.rewards.pending} icon="fa-award" iconBg="rgba(0,143,95,0.1)" iconColor="#008f5f" hint={`${fmtCoins(s.rewards.pendingCoins)} coins to approve`} />
        <StatCard label="Pending Redemptions" value={s.redemptions.pending} icon="fa-money-bill-transfer" iconBg="rgba(224,38,92,0.1)" iconColor="#e0265c" hint={`${fmtCoins(s.redemptions.pendingCoins)} coins reserved`} />
        <StatCard label="Coins Outstanding" value={fmtCoins(s.coins.outstanding)} icon="fa-coins" iconBg="rgba(10,134,199,0.1)" iconColor="#0a86c7" hint="net ledger balance" />
        <StatCard label="Callbacks (24h)" value={s.callbacks.last24h} icon="fa-arrow-right-arrow-left" iconBg="rgba(53,104,190,0.1)" iconColor="#3568be" hint={`${s.callbacks.total} total`} />
      </div>

      <div className="table-wrap" style={{ marginTop: 24 }}>
        <div className="table-header">
          <div className="table-title">Queues needing attention</div>
        </div>
        <div style={{ padding: '8px 20px 20px', display: 'flex', gap: 12, flexWrap: 'wrap' }}>
          <Link to="/rewards" className="btn btn-secondary">
            <i className="fa-solid fa-award" style={{ marginRight: 6 }} />
            {s.rewards.pending} rewards to review
          </Link>
          <Link to="/redemptions" className="btn btn-secondary">
            <i className="fa-solid fa-money-bill-transfer" style={{ marginRight: 6 }} />
            {s.redemptions.pending} redemptions to review
          </Link>
          <Link to="/support" className="btn btn-secondary">
            <i className="fa-solid fa-life-ring" style={{ marginRight: 6 }} />
            {s.supportTickets.open} open tickets
          </Link>
        </div>
      </div>
    </>
  );
}
