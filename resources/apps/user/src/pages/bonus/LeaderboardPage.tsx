import { Loading, EmptyState } from '../../components/States';
import { useApiData } from '../../hooks/useApiData';
import { formatCoins } from '../../lib/format';

interface LeaderRow {
  rank: number;
  name: string;
  earned: number;
  avatar: string;
}

export function LeaderboardPage() {
  const { data, loading } = useApiData<{ leaderboard: LeaderRow[] }>('/leaderboard');
  const rows = data?.leaderboard ?? [];

  return (
    <div className="page-enter">
      <div className="section-title">
        <i className="fa-solid fa-ranking-star" />
        Leaderboard
      </div>
      {loading ? (
        <Loading />
      ) : rows.length === 0 ? (
        <EmptyState icon="fa-ranking-star" message="No rankings yet." />
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
