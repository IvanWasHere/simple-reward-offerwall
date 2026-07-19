import { useMemo, useState } from 'react';
import { TableCard } from '../components/TableCard';
import { StatusTag } from '../components/StatusTag';
import { Loading, EmptyState, ErrorState } from '../components/States';
import { useApiData } from '../hooks/useApiData';
import { fmtDate } from '../lib/format';
import type { CallbackAuditRow } from '../lib/types';

/** Read-only server-to-server callback audit log (GET /admin/callbacks). */
export function CallbacksPage() {
  const { data, loading, error } = useApiData<{ callbacks: CallbackAuditRow[] }>('/admin/callbacks');
  const [search, setSearch] = useState('');
  const rows = data?.callbacks ?? [];

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return rows;
    return rows.filter((c) =>
      [c.provider_name, c.user_email, c.transaction_id, c.callback_type].some((f) =>
        (f || '').toString().toLowerCase().includes(q)
      )
    );
  }, [rows, search]);

  return (
    <TableCard
      title="Callback Audit"
      count={filtered.length}
      actions={
        <div className="search-box">
          <i className="fa-solid fa-magnifying-glass" />
          <input
            className="form-input"
            placeholder="Search provider, user, txn…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>
      }
    >
      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorState message={error} />
      ) : filtered.length === 0 ? (
        <EmptyState icon="fa-arrow-right-arrow-left" text="No callbacks received yet." />
      ) : (
        <table className="table">
          <thead>
            <tr>
              <th>Provider</th>
              <th>User</th>
              <th>Transaction</th>
              <th>Type</th>
              <th>Amount</th>
              <th>Signature</th>
              <th>Status</th>
              <th>When</th>
            </tr>
          </thead>
          <tbody>
            {filtered.map((c) => (
              <tr key={c.id}>
                <td>{c.provider_name || '—'}</td>
                <td>{c.user_email || '—'}</td>
                <td style={{ fontSize: 12 }}>
                  <code>{c.transaction_id}</code>
                </td>
                <td>{c.callback_type ? <span className="tag tag-blue">{c.callback_type}</span> : '—'}</td>
                <td>{c.amount}</td>
                <td>
                  {String(c.signature_ok) === '1' ? (
                    <span className="tag tag-green">Valid</span>
                  ) : (
                    <span className="tag tag-red">Invalid</span>
                  )}
                </td>
                <td>
                  <StatusTag status={c.status} />
                </td>
                <td style={{ fontSize: 12, color: 'var(--text-muted)' }}>{fmtDate(c.created_at)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </TableCard>
  );
}
