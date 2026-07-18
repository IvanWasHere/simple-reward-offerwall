import { useState } from 'react';
import { TableCard } from '../components/TableCard';
import { Loading, EmptyState, ErrorState } from '../components/States';
import { useApiData } from '../hooks/useApiData';
import { api, ApiError } from '../lib/api';
import { fmtCoins } from '../lib/format';
import { toast } from '../store/toast';
import type { RedemptionRow } from '../lib/types';

export function RedemptionsPage() {
  const { data, loading, error, refetch } = useApiData<{ redemptions: RedemptionRow[] }>(
    '/admin/redemptions?status=pending'
  );
  const [busy, setBusy] = useState<number | null>(null);
  const rows = data?.redemptions ?? [];

  const act = async (id: number, action: 'approve' | 'reject') => {
    setBusy(id);
    try {
      await api(`/admin/redemptions/${id}/${action}`, { method: 'POST' });
      toast(`Redemption ${action}d.`, 'success');
      refetch();
    } catch (e) {
      toast(e instanceof ApiError ? e.message : 'Action failed.', 'error');
    } finally {
      setBusy(null);
    }
  };

  return (
    <TableCard title="Pending Redemptions" count={rows.length}>
      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorState message={error} />
      ) : rows.length === 0 ? (
        <EmptyState icon="fa-money-bill-transfer" text="No redemptions pending approval." />
      ) : (
        <table className="table">
          <thead>
            <tr>
              <th>User</th>
              <th>Reward</th>
              <th>Coins spent</th>
              <th style={{ textAlign: 'right' }}>Actions</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.id}>
                <td>{r.user_email || `#${r.id}`}</td>
                <td>{r.payout_name || '—'}</td>
                <td style={{ fontFamily: '"Space Grotesk", sans-serif', fontWeight: 700, color: 'var(--highlight)' }}>
                  {fmtCoins(r.coins_spent)}
                </td>
                <td style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
                  <button className="btn btn-primary btn-sm" disabled={busy === r.id} onClick={() => act(r.id, 'approve')}>
                    Approve
                  </button>{' '}
                  <button className="btn btn-danger btn-sm" disabled={busy === r.id} onClick={() => act(r.id, 'reject')}>
                    Reject
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </TableCard>
  );
}
