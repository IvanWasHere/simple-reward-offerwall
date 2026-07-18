import { useEffect, useState } from 'react';
import { Modal } from '../../components/Modal';
import { api, ApiError } from '../../lib/api';
import { toast } from '../../store/toast';
import { askConfirm } from '../../store/confirm';
import type { Provider, ProviderCallback } from '../../lib/types';

export function ProviderCallbacksModal({
  provider,
  onClose,
  onChanged,
}: {
  provider: Provider;
  onClose: () => void;
  onChanged: () => void;
}) {
  const [callbacks, setCallbacks] = useState<ProviderCallback[]>([]);
  const [adding, setAdding] = useState(false);

  const reload = () =>
    api<{ callbacks: ProviderCallback[] }>(`/admin/providers/${provider.id}/callbacks`)
      .then((r) => setCallbacks(r.callbacks || []))
      .catch(() => setCallbacks([]));

  useEffect(() => {
    reload();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [provider.id]);

  const remove = (c: ProviderCallback) =>
    askConfirm(`Delete callback "${c.name}"?`, async () => {
      try {
        await api(`/admin/providers/${provider.id}/callbacks/${c.id}`, { method: 'DELETE' });
        toast('Callback deleted.', 'success');
        reload();
        onChanged();
      } catch (e) {
        toast(e instanceof ApiError ? e.message : 'Delete failed.', 'error');
      }
    });

  return (
    <Modal title={`Callbacks — ${provider.name}`} onClose={onClose} width={560}>
      {callbacks.length === 0 ? (
        <p style={{ color: 'var(--text-muted)', fontSize: 13, padding: '4px 0 12px' }}>
          No callbacks configured. Add one below.
        </p>
      ) : (
        <table className="table" style={{ marginBottom: 12 }}>
          <thead>
            <tr>
              <th>Name</th>
              <th>Algo</th>
              <th>Callback URL</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {callbacks.map((c) => (
              <tr key={c.id}>
                <td>{c.name}</td>
                <td>
                  <span className="tag tag-blue">{c.signatureAlgo}</span>
                </td>
                <td style={{ fontSize: 11 }}>
                  <code style={{ wordBreak: 'break-all' }}>{c.callbackUrl}</code>
                </td>
                <td style={{ textAlign: 'right' }}>
                  <button className="btn btn-ghost btn-icon btn-sm" style={{ color: 'var(--danger)' }} onClick={() => remove(c)} title="Delete">
                    <i className="fa-solid fa-trash-can" />
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {adding ? (
        <AddCallbackForm
          providerId={provider.id}
          onCancel={() => setAdding(false)}
          onCreated={() => {
            setAdding(false);
            reload();
            onChanged();
          }}
        />
      ) : (
        <button className="btn btn-secondary btn-sm" onClick={() => setAdding(true)}>
          <i className="fa-solid fa-plus" style={{ marginRight: 6 }} />
          Add callback
        </button>
      )}
    </Modal>
  );
}

function AddCallbackForm({
  providerId,
  onCancel,
  onCreated,
}: {
  providerId: number;
  onCancel: () => void;
  onCreated: () => void;
}) {
  const [name, setName] = useState('Postback');
  const [sigParam, setSigParam] = useState('sig');
  const [algo, setAlgo] = useState('hmac_sha256');
  const [secret, setSecret] = useState('');
  const [paramMap, setParamMap] = useState(
    '{\n  "transaction_id": "txn",\n  "user_id": "uid",\n  "amount": "payout"\n}'
  );
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const submit = async () => {
    setError(null);
    let mapObj: Record<string, string> = {};
    try {
      mapObj = paramMap.trim() ? JSON.parse(paramMap) : {};
    } catch {
      setError('Param map must be valid JSON.');
      return;
    }
    setBusy(true);
    try {
      await api(`/admin/providers/${providerId}/callbacks`, {
        method: 'POST',
        body: {
          name,
          signature_param: sigParam,
          signature_algo: algo,
          signature_source: 'ordered_params',
          secret,
          param_map: mapObj,
        },
      });
      toast('Callback created.', 'success');
      onCreated();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Save failed.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div style={{ borderTop: '1px solid var(--border)', paddingTop: 14, marginTop: 4 }}>
      {error && <div className="auth-error">{error}</div>}
      <div className="form-row">
        <div className="form-group">
          <label className="form-label">Name</label>
          <input className="form-input" value={name} onChange={(e) => setName(e.target.value)} />
        </div>
        <div className="form-group">
          <label className="form-label">Signature param</label>
          <input className="form-input" value={sigParam} onChange={(e) => setSigParam(e.target.value)} />
        </div>
      </div>
      <div className="form-row">
        <div className="form-group">
          <label className="form-label">Signature algo</label>
          <select className="form-select" value={algo} onChange={(e) => setAlgo(e.target.value)}>
            <option value="hmac_sha256">hmac_sha256</option>
            <option value="md5_concat">md5_concat</option>
            <option value="none">none (testing)</option>
          </select>
        </div>
        <div className="form-group">
          <label className="form-label">Secret</label>
          <input className="form-input" value={secret} onChange={(e) => setSecret(e.target.value)} />
        </div>
      </div>
      <div className="form-group">
        <label className="form-label">Param map (JSON)</label>
        <textarea className="form-input" rows={4} value={paramMap} onChange={(e) => setParamMap(e.target.value)} style={{ fontFamily: 'monospace', fontSize: 12 }} />
      </div>
      <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
        <button className="btn btn-secondary btn-sm" onClick={onCancel}>
          Cancel
        </button>
        <button className="btn btn-primary btn-sm" disabled={busy} onClick={submit}>
          {busy ? 'Saving…' : 'Create callback'}
        </button>
      </div>
    </div>
  );
}
