import { useState } from 'react';
import { TableCard } from '../components/TableCard';
import { Modal } from '../components/Modal';
import { StatusTag } from '../components/StatusTag';
import { Loading, EmptyState, ErrorState } from '../components/States';
import { useApiData } from '../hooks/useApiData';
import { api, ApiError } from '../lib/api';
import { fmtCoins, fmtMoney } from '../lib/format';
import { toast } from '../store/toast';
import { askConfirm } from '../store/confirm';
import type { Payout } from '../lib/types';

function PayoutModal({ onClose, onSaved }: { onClose: () => void; onSaved: () => void }) {
  const [name, setName] = useState('');
  const [coins, setCoins] = useState('1000');
  const [money, setMoney] = useState('500');
  const [currency, setCurrency] = useState('USD');
  const [stock, setStock] = useState('-1');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const save = async () => {
    setBusy(true);
    setError(null);
    try {
      await api('/admin/payouts', {
        method: 'POST',
        body: {
          name,
          value_coins: parseInt(coins, 10) || 0,
          value_money: parseInt(money, 10) || 0,
          currency,
          stock: parseInt(stock, 10),
        },
      });
      toast('Payout created.', 'success');
      onSaved();
      onClose();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Save failed.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <Modal
      title="New Payout"
      onClose={onClose}
      footer={
        <>
          <button className="btn btn-secondary" onClick={onClose}>
            Cancel
          </button>
          <button className="btn btn-primary" disabled={busy || !name} onClick={save}>
            {busy ? 'Saving…' : 'Create payout'}
          </button>
        </>
      }
    >
      {error && <div className="auth-error">{error}</div>}
      <div className="form-group">
        <label className="form-label">Name *</label>
        <input className="form-input" placeholder="e.g. Amazon Gift Card" value={name} onChange={(e) => setName(e.target.value)} />
      </div>
      <div className="form-row">
        <div className="form-group">
          <label className="form-label">Coin price *</label>
          <input className="form-input" type="number" value={coins} onChange={(e) => setCoins(e.target.value)} />
        </div>
        <div className="form-group">
          <label className="form-label">Value (minor units)</label>
          <input className="form-input" type="number" value={money} onChange={(e) => setMoney(e.target.value)} />
          <div className="form-hint">e.g. 500 = $5.00</div>
        </div>
      </div>
      <div className="form-row">
        <div className="form-group">
          <label className="form-label">Currency</label>
          <input className="form-input" value={currency} onChange={(e) => setCurrency(e.target.value)} />
        </div>
        <div className="form-group">
          <label className="form-label">Stock</label>
          <input className="form-input" type="number" value={stock} onChange={(e) => setStock(e.target.value)} />
          <div className="form-hint">-1 = unlimited</div>
        </div>
      </div>
    </Modal>
  );
}

export function PayoutsPage() {
  const { data, loading, error, refetch } = useApiData<{ payouts: Payout[] }>('/admin/payouts');
  const [showModal, setShowModal] = useState(false);
  const rows = data?.payouts ?? [];

  const remove = (p: Payout) =>
    askConfirm(`Delete payout "${p.name}"?`, async () => {
      try {
        await api(`/admin/payouts/${p.id}`, { method: 'DELETE' });
        toast('Payout deleted.', 'success');
        refetch();
      } catch (e) {
        toast(e instanceof ApiError ? e.message : 'Delete failed.', 'error');
      }
    });

  return (
    <>
      <TableCard
        title="Payout Catalog"
        count={rows.length}
        actions={
          <button className="btn btn-primary" onClick={() => setShowModal(true)}>
            <i className="fa-solid fa-plus" style={{ marginRight: 6 }} />
            Add Payout
          </button>
        }
      >
        {loading ? (
          <Loading />
        ) : error ? (
          <ErrorState message={error} />
        ) : rows.length === 0 ? (
          <EmptyState icon="fa-gifts" text="No payouts yet." />
        ) : (
          <table className="table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Coin price</th>
                <th>Value</th>
                <th>Stock</th>
                <th>Status</th>
                <th style={{ textAlign: 'right' }}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((p) => (
                <tr key={p.id}>
                  <td>{p.name}</td>
                  <td style={{ fontFamily: '"Space Grotesk", sans-serif', fontWeight: 700, color: 'var(--accent)' }}>
                    {fmtCoins(p.valueCoins)}
                  </td>
                  <td>{fmtMoney(p.valueMoney, p.currency)}</td>
                  <td>{p.stock < 0 ? '∞' : p.stock}</td>
                  <td>
                    <StatusTag status={p.status} />
                  </td>
                  <td style={{ textAlign: 'right' }}>
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
      {showModal && <PayoutModal onClose={() => setShowModal(false)} onSaved={refetch} />}
    </>
  );
}
