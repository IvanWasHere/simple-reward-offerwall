import { useEffect, useState } from 'react';
import { Modal } from '../../components/Modal';
import { api, ApiError } from '../../lib/api';
import { toast } from '../../store/toast';
import type { Provider } from '../../lib/types';

interface FormState {
  name: string;
  type: string;
  url: string;
  adslot_id: string;
  coin_rate: string;
  wall_placement: string;
  api_key: string;
  api_secret: string;
  status: string;
  macros: string;
  config: Record<string, unknown>;
}

const EMPTY: FormState = {
  name: '',
  type: 'iframe',
  url: '',
  adslot_id: '',
  coin_rate: '100',
  wall_placement: 'all',
  api_key: '',
  api_secret: '',
  status: 'active',
  macros: '{}',
  config: {},
};

export function ProviderModal({
  providerId,
  onClose,
  onSaved,
}: {
  providerId: number | null;
  onClose: () => void;
  onSaved: () => void;
}) {
  const [form, setForm] = useState<FormState>(EMPTY);
  const [loading, setLoading] = useState(providerId !== null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (providerId === null) return;
    api<{ provider: Provider }>(`/admin/providers/${providerId}`)
      .then(({ provider: p }) => {
        const config = (p.config && typeof p.config === 'object' ? p.config : {}) as Record<string, unknown>;
        setForm({
          name: p.name,
          type: p.type,
          url: p.url || '',
          adslot_id: p.adslotId || '',
          coin_rate: String(p.coinRate ?? '0'),
          wall_placement: p.wallPlacement || 'all',
          api_key: p.apiKey || '',
          api_secret: '',
          status: p.status || 'active',
          macros: JSON.stringify(p.macros && typeof p.macros === 'object' ? p.macros : {}, null, 2),
          config,
        });
      })
      .catch(() => setError('Could not load the provider.'))
      .finally(() => setLoading(false));
  }, [providerId]);

  const set = (k: keyof FormState, v: string) => setForm((f) => ({ ...f, [k]: v }));

  const save = async () => {
    setError(null);
    let macrosObj: Record<string, string> = {};
    try {
      macrosObj = form.macros.trim() ? JSON.parse(form.macros) : {};
    } catch {
      setError('Macros must be valid JSON.');
      return;
    }
    setBusy(true);
    const body: Record<string, unknown> = {
      name: form.name,
      type: form.type,
      url: form.url,
      adslot_id: form.adslot_id,
      coin_rate: parseFloat(form.coin_rate) || 0,
      wall_placement: form.wall_placement,
      api_key: form.api_key,
      status: form.status,
      macros: macrosObj,
      config: form.config, // preserved; controller overlays wall_placement
    };
    if (form.api_secret) body.api_secret = form.api_secret;

    try {
      if (providerId === null) {
        await api('/admin/providers', { method: 'POST', body });
        toast('Provider created.', 'success');
      } else {
        await api(`/admin/providers/${providerId}`, { method: 'PUT', body });
        toast('Provider updated.', 'success');
      }
      onSaved();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Save failed.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <Modal
      title={providerId === null ? 'New Provider' : 'Edit Provider'}
      onClose={onClose}
      width={560}
      footer={
        <>
          <button className="btn btn-secondary" onClick={onClose}>
            Cancel
          </button>
          <button className="btn btn-primary" disabled={busy || loading || !form.name} onClick={save}>
            {busy ? 'Saving…' : providerId === null ? 'Create provider' : 'Save changes'}
          </button>
        </>
      }
    >
      {loading ? (
        <p style={{ padding: 12 }}>Loading…</p>
      ) : (
        <>
          {error && <div className="auth-error">{error}</div>}
          <div className="form-group">
            <label className="form-label">Name *</label>
            <input className="form-input" value={form.name} onChange={(e) => set('name', e.target.value)} />
          </div>
          <div className="form-row">
            <div className="form-group">
              <label className="form-label">Type</label>
              <select className="form-select" value={form.type} onChange={(e) => set('type', e.target.value)}>
                <option value="iframe">iframe</option>
                <option value="offerwall_api">offerwall_api</option>
                <option value="static_api">static_api</option>
              </select>
            </div>
            <div className="form-group">
              <label className="form-label">Status</label>
              <select className="form-select" value={form.status} onChange={(e) => set('status', e.target.value)}>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>
          <div className="form-group">
            <label className="form-label">URL template</label>
            <input className="form-input" value={form.url} onChange={(e) => set('url', e.target.value)} placeholder="https://wall.example.com/?sid={external_id}" />
            <div className="form-hint">
              Inline macros: <code>{'{user_id}'}</code> <code>{'{user_hash}'}</code>{' '}
              <code>{'{session_id}'}</code> <code>{'{external_id}'}</code>
            </div>
          </div>
          <div className="form-row">
            <div className="form-group">
              <label className="form-label">Coin rate</label>
              <input className="form-input" type="number" value={form.coin_rate} onChange={(e) => set('coin_rate', e.target.value)} />
              <div className="form-hint">coins per 1.00 payout</div>
            </div>
            <div className="form-group">
              <label className="form-label">Wall placement</label>
              <select className="form-select" value={form.wall_placement} onChange={(e) => set('wall_placement', e.target.value)}>
                <option value="hot">Hot Walls</option>
                <option value="all">All Walls</option>
                <option value="none">Hidden</option>
              </select>
            </div>
          </div>
          <div className="form-row">
            <div className="form-group">
              <label className="form-label">Ad slot ID</label>
              <input className="form-input" autoComplete="off" value={form.adslot_id} onChange={(e) => set('adslot_id', e.target.value)} />
            </div>
            <div className="form-group">
              <label className="form-label">API key</label>
              <input className="form-input" autoComplete="off" value={form.api_key} onChange={(e) => set('api_key', e.target.value)} />
            </div>
          </div>
          <div className="form-group">
            <label className="form-label">API secret</label>
            <input className="form-input" type="password" autoComplete="new-password" value={form.api_secret} onChange={(e) => set('api_secret', e.target.value)} placeholder={providerId ? 'unchanged — leave blank to keep' : ''} />
          </div>
          <div className="form-group">
            <label className="form-label">Macros (JSON, optional)</label>
            <textarea className="form-input" rows={4} value={form.macros} onChange={(e) => set('macros', e.target.value)} style={{ fontFamily: 'monospace', fontSize: 12 }} />
          </div>
        </>
      )}
    </Modal>
  );
}
