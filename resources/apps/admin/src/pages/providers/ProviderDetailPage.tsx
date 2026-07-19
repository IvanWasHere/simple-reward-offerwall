import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { TableCard } from '../../components/TableCard';
import { Loading, ErrorState } from '../../components/States';
import { api, ApiError } from '../../lib/api';
import { toast } from '../../store/toast';
import { ProviderCallbacks } from './ProviderCallbacks';
import type { OfferSchema, Provider } from '../../lib/types';

interface FormState {
  name: string;
  type: string;
  url: string;
  adslot_id: string;
  coin_rate: string;
  offer_schema: string;
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
  offer_schema: '',
  wall_placement: 'all',
  api_key: '',
  api_secret: '',
  status: 'active',
  macros: '{}',
  config: {},
};

// Offer schemas apply only to server-fetched offer feeds.
const SCHEMA_TYPES = ['static_api', 'offerwall_api'];

export function ProviderDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  // Treat 'new' (or a missing id) as the create form — never try to load it.
  const isNew = id === 'new' || !id;
  const providerId = isNew ? null : Number(id);

  const [provider, setProvider] = useState<Provider | null>(null);
  const [form, setForm] = useState<FormState>(EMPTY);
  const [loading, setLoading] = useState(!isNew);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [schemas, setSchemas] = useState<OfferSchema[]>([]);

  useEffect(() => {
    api<{ schemas: OfferSchema[] }>('/admin/offer-schemas')
      .then((r) => setSchemas(r.schemas || []))
      .catch(() => setSchemas([]));
  }, []);

  const hydrate = (p: Provider) => {
    setProvider(p);
    const config = (p.config && typeof p.config === 'object' ? p.config : {}) as Record<string, unknown>;
    setForm({
      name: p.name,
      type: p.type,
      url: p.url || '',
      adslot_id: p.adslotId || '',
      coin_rate: String(p.coinRate ?? '0'),
      offer_schema: p.offerSchema || '',
      wall_placement: p.wallPlacement || 'all',
      api_key: p.apiKey || '',
      api_secret: '',
      status: p.status || 'active',
      macros: JSON.stringify(p.macros && typeof p.macros === 'object' ? p.macros : {}, null, 2),
      config,
    });
  };

  useEffect(() => {
    if (isNew || providerId === null || Number.isNaN(providerId)) return;
    setLoading(true);
    api<{ provider: Provider }>(`/admin/providers/${providerId}`)
      .then(({ provider: p }) => hydrate(p))
      .catch(() => setLoadError('Could not load the provider.'))
      .finally(() => setLoading(false));
  }, [isNew, providerId]);

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
      offer_schema: SCHEMA_TYPES.includes(form.type) ? form.offer_schema : '',
      wall_placement: form.wall_placement,
      api_key: form.api_key,
      status: form.status,
      macros: macrosObj,
      config: form.config, // preserved; controller overlays wall_placement
    };
    if (form.api_secret) body.api_secret = form.api_secret;

    try {
      if (isNew) {
        const { provider: created } = await api<{ provider: Provider }>('/admin/providers', { method: 'POST', body });
        toast('Provider created.', 'success');
        // Land on the saved provider so callbacks can be added.
        navigate(`/providers/${created.id}`);
      } else {
        const { provider: updated } = await api<{ provider: Provider }>(`/admin/providers/${providerId}`, {
          method: 'PUT',
          body,
        });
        hydrate(updated);
        toast('Provider updated.', 'success');
      }
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Save failed.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div>
      <button className="btn btn-secondary btn-sm" style={{ marginBottom: 16 }} onClick={() => navigate('/providers')}>
        <i className="fa-solid fa-arrow-left" style={{ marginRight: 6 }} />
        Back to providers
      </button>

      <TableCard title={isNew ? 'New Provider' : `Provider — ${provider?.name ?? ''}`}>
        {loading ? (
          <Loading />
        ) : loadError ? (
          <ErrorState message={loadError} />
        ) : (
          <div style={{ padding: 16, maxWidth: 620 }}>
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
                <select
                  className="form-select"
                  value={form.wall_placement}
                  disabled={form.type === 'static_api'}
                  onChange={(e) => set('wall_placement', e.target.value)}
                >
                  <option value="hot">Hot Walls</option>
                  <option value="all">All Walls</option>
                  <option value="none">Hidden</option>
                </select>
                {form.type === 'static_api' && (
                  <div className="form-hint">Not used — static offers show in the offers feed, not on a wall.</div>
                )}
              </div>
            </div>
            {SCHEMA_TYPES.includes(form.type) && (
              <div className="form-group">
                <label className="form-label">Offer schema</label>
                <select className="form-select" value={form.offer_schema} onChange={(e) => set('offer_schema', e.target.value)}>
                  <option value="">None (manual field map)</option>
                  {schemas.map((s) => (
                    <option key={s.key} value={s.key}>
                      {s.label}
                    </option>
                  ))}
                </select>
                <div className="form-hint">
                  {form.offer_schema
                    ? (() => {
                        const s = schemas.find((x) => x.key === form.offer_schema);
                        return s ? `Maps this provider's ${s.httpMethod} offer feed and callbacks.` : '';
                      })()
                    : 'Built-in mapping of the provider API response → offers + callbacks.'}
                </div>
              </div>
            )}
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
              <input className="form-input" type="password" autoComplete="new-password" value={form.api_secret} onChange={(e) => set('api_secret', e.target.value)} placeholder={!isNew ? 'unchanged — leave blank to keep' : ''} />
            </div>
            <div className="form-group">
              <label className="form-label">Macros (JSON, optional)</label>
              <textarea className="form-input" rows={4} value={form.macros} onChange={(e) => set('macros', e.target.value)} style={{ fontFamily: 'monospace', fontSize: 12 }} />
            </div>
            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
              <button className="btn btn-secondary" onClick={() => navigate('/providers')}>
                Cancel
              </button>
              <button className="btn btn-primary" disabled={busy || !form.name} onClick={save}>
                {busy ? 'Saving…' : isNew ? 'Create provider' : 'Save changes'}
              </button>
            </div>
          </div>
        )}
      </TableCard>

      {provider && !isNew && (
        <div style={{ marginTop: 20 }}>
          <ProviderCallbacks provider={provider} />
        </div>
      )}
    </div>
  );
}
