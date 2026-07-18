import { useState } from 'react';
import { TableCard } from '../components/TableCard';
import { ProviderTypeTag, StatusTag } from '../components/StatusTag';
import { Loading, EmptyState, ErrorState } from '../components/States';
import { useApiData } from '../hooks/useApiData';
import { api, ApiError } from '../lib/api';
import { toast } from '../store/toast';
import { askConfirm } from '../store/confirm';
import { ProviderModal } from './providers/ProviderModal';
import { ProviderCallbacksModal } from './providers/ProviderCallbacksModal';
import type { Provider } from '../lib/types';

export function ProvidersPage() {
  const { data, loading, error, refetch } = useApiData<{ providers: Provider[] }>('/admin/providers');
  const [editId, setEditId] = useState<number | 'new' | null>(null);
  const [callbacksFor, setCallbacksFor] = useState<Provider | null>(null);
  const [busy, setBusy] = useState<number | null>(null);
  const rows = data?.providers ?? [];

  const ingest = async (p: Provider) => {
    setBusy(p.id);
    try {
      const r = await api<{ ingested: number }>(`/admin/providers/${p.id}/ingest`, { method: 'POST' });
      toast(`${p.name}: ${r.ingested} offers ingested.`, 'success');
      refetch();
    } catch (e) {
      toast(e instanceof ApiError ? e.message : 'Ingest failed.', 'error');
    } finally {
      setBusy(null);
    }
  };

  const remove = (p: Provider) =>
    askConfirm(`Delete provider "${p.name}"? Its offers and callbacks go too.`, async () => {
      try {
        await api(`/admin/providers/${p.id}`, { method: 'DELETE' });
        toast('Provider deleted.', 'success');
        refetch();
      } catch (e) {
        toast(e instanceof ApiError ? e.message : 'Delete failed.', 'error');
      }
    });

  return (
    <>
      <TableCard
        title="Providers"
        count={rows.length}
        actions={
          <button className="btn btn-primary" onClick={() => setEditId('new')}>
            <i className="fa-solid fa-plus" style={{ marginRight: 6 }} />
            Add Provider
          </button>
        }
      >
        {loading ? (
          <Loading />
        ) : error ? (
          <ErrorState message={error} />
        ) : rows.length === 0 ? (
          <EmptyState icon="fa-server" text="No providers yet. Add one to start." />
        ) : (
          <table className="table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Type</th>
                <th>Coin rate</th>
                <th>Wall</th>
                <th>Callbacks</th>
                <th>Status</th>
                <th style={{ textAlign: 'right' }}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((p) => (
                <tr key={p.id}>
                  <td>{p.name}</td>
                  <td>
                    <ProviderTypeTag type={p.type} />
                  </td>
                  <td>{p.coinRate}</td>
                  <td style={{ fontSize: 12, color: 'var(--text-muted)' }}>{p.wallPlacement ?? '—'}</td>
                  <td>{p.callbackCount ?? 0}</td>
                  <td>
                    <StatusTag status={p.status} />
                  </td>
                  <td style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
                    {p.type === 'static_api' && (
                      <button className="btn btn-secondary btn-sm" disabled={busy === p.id} onClick={() => ingest(p)} title="Ingest offers now">
                        <i className="fa-solid fa-rotate" />
                      </button>
                    )}{' '}
                    <button className="btn btn-ghost btn-icon btn-sm" onClick={() => setCallbacksFor(p)} title="Callbacks">
                      <i className="fa-solid fa-arrow-right-arrow-left" />
                    </button>{' '}
                    <button className="btn btn-ghost btn-icon btn-sm" onClick={() => setEditId(p.id)} title="Edit">
                      <i className="fa-solid fa-pen-to-square" />
                    </button>{' '}
                    <button className="btn btn-ghost btn-icon btn-sm" style={{ color: 'var(--danger)' }} onClick={() => remove(p)} title="Delete">
                      <i className="fa-solid fa-trash-can" />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </TableCard>

      {editId !== null && (
        <ProviderModal
          providerId={editId === 'new' ? null : editId}
          onClose={() => setEditId(null)}
          onSaved={() => {
            setEditId(null);
            refetch();
          }}
        />
      )}
      {callbacksFor && (
        <ProviderCallbacksModal
          provider={callbacksFor}
          onClose={() => setCallbacksFor(null)}
          onChanged={refetch}
        />
      )}
    </>
  );
}
