import { useMemo, useState } from 'react';
import { TableCard } from '../components/TableCard';
import { Loading, EmptyState, ErrorState } from '../components/States';
import { useApiData } from '../hooks/useApiData';
import { api, ApiError } from '../lib/api';
import { fmtCoins } from '../lib/format';
import { toast } from '../store/toast';
import type { OfferRow } from '../lib/types';

/** Ingested offers — admins can enable/disable (offers are ingested, not created). */
export function OffersPage() {
  const { data, loading, error, refetch } = useApiData<{ offers: OfferRow[] }>('/admin/offers');
  const [search, setSearch] = useState('');
  const [busy, setBusy] = useState<number | null>(null);
  const rows = data?.offers ?? [];

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return rows;
    return rows.filter((o) =>
      [o.name, o.providerName].some((f) => (f || '').toLowerCase().includes(q))
    );
  }, [rows, search]);

  const toggle = async (o: OfferRow) => {
    setBusy(o.id);
    try {
      await api(`/admin/offers/${o.id}`, { method: 'PUT', body: { enabled: !o.enabled } });
      toast(`Offer ${o.enabled ? 'disabled' : 'enabled'}.`, 'success');
      refetch();
    } catch (e) {
      toast(e instanceof ApiError ? e.message : 'Update failed.', 'error');
    } finally {
      setBusy(null);
    }
  };

  return (
    <TableCard
      title="Offers"
      count={filtered.length}
      actions={
        <div className="search-box">
          <i className="fa-solid fa-magnifying-glass" />
          <input
            className="form-input"
            placeholder="Search offers…"
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
        <EmptyState icon="fa-gift" text="No offers ingested yet. Run “Ingest now” on a static_api provider." />
      ) : (
        <table className="table">
          <thead>
            <tr>
              <th>Offer</th>
              <th>Provider</th>
              <th>Payout</th>
              <th>Available</th>
              <th>Visibility</th>
              <th style={{ textAlign: 'right' }}>Actions</th>
            </tr>
          </thead>
          <tbody>
            {filtered.map((o) => (
              <tr key={o.id} style={{ opacity: o.enabled ? 1 : 0.55 }}>
                <td>{o.name}</td>
                <td>{o.providerName}</td>
                <td>{fmtCoins(o.totalPayout)}</td>
                <td>
                  {o.available ? (
                    <span className="tag tag-green">Live</span>
                  ) : (
                    <span className="tag tag-gray">Stale</span>
                  )}
                </td>
                <td>
                  {o.enabled ? (
                    <span className="tag tag-green">Enabled</span>
                  ) : (
                    <span className="tag tag-gray">Disabled</span>
                  )}
                </td>
                <td style={{ textAlign: 'right' }}>
                  <button
                    className={'btn btn-sm ' + (o.enabled ? 'btn-secondary' : 'btn-primary')}
                    disabled={busy === o.id}
                    onClick={() => toggle(o)}
                  >
                    {o.enabled ? 'Disable' : 'Enable'}
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
