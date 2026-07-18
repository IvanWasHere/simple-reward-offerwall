import { useState } from 'react';
import { Loading, EmptyState } from '../../components/States';
import { useApiData } from '../../hooks/useApiData';
import { formatCoins } from '../../lib/format';

interface LeaderRow {
  rank: number;
  name: string;
  earned: number;
  avatar: string;
}

const PERIODS = [
  { key: 'today', label: 'Today' },
  { key: 'week', label: 'This Week' },
  { key: 'month', label: 'This Month' },
] as const;

export function LeaderboardPage() {
  const [period, setPeriod] = useState<(typeof PERIODS)[number]['key']>('week');
  const { data, loading } = useApiData<{ leaderboard: LeaderRow[] }>(`/leaderboard?period=${period}`);
  const rows = data?.leaderboard ?? [];

  return (
    <div className="page-enter">
      <div className="section-title">
        <i className="fa-solid fa-ranking-star" />
        Leaderboard
      </div>

      <div className="filter-bar">
        {PERIODS.map((p) => (
          <button
            key={p.key}
            className={'filter-btn' + (period === p.key ? ' active' : '')}
            onClick={() => setPeriod(p.key)}
          >
            {p.label}
          </button>
        ))}
      </div>

      {loading ? (
        <Loading />
      ) : rows.length === 0 ? (
        <EmptyState icon="fa-ranking-star" message="No earners in this period yet." />
      ) : (
        <div className="wall-section" style={{ overflowX: 'auto', padding: '4px 20px' }}>
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
                const rc =
                  u.rank === 1 ? 'gold' : u.rank === 2 ? 'silver' : u.rank === 3 ? 'bronze' : '';
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
      )}
    </div>
  );
}
