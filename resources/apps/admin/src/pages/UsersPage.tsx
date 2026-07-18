import { useState } from 'react';
import { TableCard } from '../components/TableCard';
import { StatusTag } from '../components/StatusTag';
import { Loading, EmptyState, ErrorState } from '../components/States';
import { useApiData } from '../hooks/useApiData';
import { api, ApiError } from '../lib/api';
import { fmtCoins } from '../lib/format';
import { toast } from '../store/toast';
import { askConfirm } from '../store/confirm';
import { UserDetailModal } from './users/UserDetailModal';
import type { UserRow } from '../lib/types';

export function UsersPage() {
  const [q, setQ] = useState('');
  const [query, setQuery] = useState('');
  const [detail, setDetail] = useState<UserRow | null>(null);
  const path = query ? `/admin/users?q=${encodeURIComponent(query)}` : '/admin/users';
  const { data, loading, error, refetch } = useApiData<{ users: UserRow[] }>(path);
  const rows = data?.users ?? [];

  const changeType = async (u: UserRow, type: string) => {
    if (type === u.type) return;
    try {
      await api(`/admin/users/${u.id}`, { method: 'PUT', body: { type } });
      toast(`${u.email} is now ${type}.`, 'success');
      refetch();
    } catch (e) {
      toast(e instanceof ApiError ? e.message : 'Update failed.', 'error');
    }
  };

  const toggleBlock = (u: UserRow) => {
    const blocking = u.status !== 'blocked';
    askConfirm(
      `${blocking ? 'Block' : 'Unblock'} ${u.email}?`,
      async () => {
        try {
          await api(`/admin/users/${u.id}`, {
            method: 'PUT',
            body: { status: blocking ? 'blocked' : 'active' },
          });
          toast(`${u.email} ${blocking ? 'blocked' : 'unblocked'}.`, 'success');
          refetch();
        } catch (e) {
          toast(e instanceof ApiError ? e.message : 'Update failed.', 'error');
        }
      },
      { confirmLabel: blocking ? 'Block' : 'Unblock', danger: blocking }
    );
  };

  return (
    <>
    <TableCard
      title="Users"
      count={rows.length}
      actions={
        <form
          className="search-box"
          onSubmit={(e) => {
            e.preventDefault();
            setQuery(q);
          }}
        >
          <i className="fa-solid fa-magnifying-glass" />
          <input
            className="form-input"
            placeholder="Search email…"
            value={q}
            onChange={(e) => setQ(e.target.value)}
          />
        </form>
      }
    >
      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorState message={error} />
      ) : rows.length === 0 ? (
        <EmptyState icon="fa-users" text="No users found." />
      ) : (
        <table className="table">
          <thead>
            <tr>
              <th>Email</th>
              <th>Type</th>
              <th>Status</th>
              <th>Balance</th>
              <th style={{ textAlign: 'right' }}>Actions</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((u) => (
              <tr key={u.id}>
                <td>{u.email}</td>
                <td>
                  <select
                    className="form-select"
                    style={{ minWidth: 120 }}
                    value={u.type}
                    onChange={(e) => changeType(u, e.target.value)}
                  >
                    <option value="user">user</option>
                    <option value="support">support</option>
                    <option value="admin">admin</option>
                  </select>
                </td>
                <td>
                  <StatusTag status={u.status} />
                </td>
                <td style={{ fontFamily: '"Space Grotesk", sans-serif', fontWeight: 700, color: 'var(--accent)' }}>
                  {fmtCoins(u.balance)}
                </td>
                <td style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
                  <button className="btn btn-ghost btn-icon btn-sm" onClick={() => setDetail(u)} title="View details & clicks">
                    <i className="fa-solid fa-magnifying-glass" />
                  </button>{' '}
                  <button
                    className={'btn btn-sm ' + (u.status === 'blocked' ? 'btn-primary' : 'btn-danger')}
                    onClick={() => toggleBlock(u)}
                  >
                    {u.status === 'blocked' ? 'Unblock' : 'Block'}
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </TableCard>
    {detail && <UserDetailModal user={detail} onClose={() => setDetail(null)} />}
    </>
  );
}
