import { useEffect, useMemo, useState } from 'react';
import { TableCard } from '../../components/TableCard';
import { api, ApiError } from '../../lib/api';
import { toast } from '../../store/toast';
import { askConfirm } from '../../store/confirm';
import type { OfferSchema, Provider, ProviderCallback } from '../../lib/types';

function copy(text: string) {
  navigator.clipboard?.writeText(text).then(
    () => toast('Copied to clipboard.', 'success'),
    () => toast('Could not copy.', 'error')
  );
}

/**
 * Build the postback URL an admin pastes into the provider dashboard from the
 * callback's configured param map: each mapped field becomes `<key>={macro}`,
 * using the schema's macro token for that field (falling back to `{field}`).
 */
function buildPostbackUrl(callbackUrl: string, schema: OfferSchema | null, paramMap: Record<string, string>): string {
  const entries = Object.entries(paramMap || {}).filter(([, key]) => key);
  if (entries.length === 0) return callbackUrl;
  const macroByField: Record<string, string> = {};
  schema?.callbackFields.forEach((f) => (macroByField[f.field] = f.macro));
  const qs = entries.map(([field, key]) => `${key}=${macroByField[field] ?? `{${field}}`}`).join('&');
  return `${callbackUrl}?${qs}`;
}

/**
 * Inline (non-modal) management of a provider's S2S callbacks — the list, the
 * schema macro reference, and an add/edit form. Rendered on the provider detail
 * page.
 */
export function ProviderCallbacks({
  provider,
  onChanged,
}: {
  provider: Provider;
  onChanged?: () => void;
}) {
  const [callbacks, setCallbacks] = useState<ProviderCallback[]>([]);
  const [schemas, setSchemas] = useState<OfferSchema[]>([]);
  // null = closed, 'new' = add form, number = editing that callback id.
  const [editing, setEditing] = useState<number | 'new' | null>(null);

  const schema = useMemo(
    () => (provider.offerSchema ? schemas.find((s) => s.key === provider.offerSchema) ?? null : null),
    [schemas, provider.offerSchema]
  );

  const reload = () =>
    api<{ callbacks: ProviderCallback[] }>(`/admin/providers/${provider.id}/callbacks`)
      .then((r) => setCallbacks(r.callbacks || []))
      .catch(() => setCallbacks([]));

  useEffect(() => {
    reload();
    api<{ schemas: OfferSchema[] }>('/admin/offer-schemas')
      .then((r) => setSchemas(r.schemas || []))
      .catch(() => setSchemas([]));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [provider.id]);

  const remove = (c: ProviderCallback) =>
    askConfirm(`Delete callback "${c.name}"?`, async () => {
      try {
        await api(`/admin/providers/${provider.id}/callbacks/${c.id}`, { method: 'DELETE' });
        toast('Callback deleted.', 'success');
        reload();
        onChanged?.();
      } catch (e) {
        toast(e instanceof ApiError ? e.message : 'Delete failed.', 'error');
      }
    });

  const editTarget = typeof editing === 'number' ? callbacks.find((c) => c.id === editing) ?? null : null;

  return (
    <TableCard
      title="Callbacks"
      count={callbacks.length}
      actions={
        editing === null ? (
          <button className="btn btn-primary btn-sm" onClick={() => setEditing('new')}>
            <i className="fa-solid fa-plus" style={{ marginRight: 6 }} />
            Add callback
          </button>
        ) : undefined
      }
    >
      <div style={{ padding: 16 }}>
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
                <th>Postback URL</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {callbacks.map((c) => {
                const full = buildPostbackUrl(c.callbackUrl, schema, c.paramMap);
                return (
                  <tr key={c.id}>
                    <td>{c.name}</td>
                    <td>
                      <span className="tag tag-blue">{c.signatureAlgo}</span>
                    </td>
                    <td style={{ fontSize: 11 }}>
                      <code style={{ wordBreak: 'break-all' }}>{full}</code>
                      <button
                        className="btn btn-ghost btn-icon btn-sm"
                        onClick={() => copy(full)}
                        title="Copy postback URL"
                        style={{ marginLeft: 4 }}
                      >
                        <i className="fa-solid fa-copy" />
                      </button>
                    </td>
                    <td style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
                      <button className="btn btn-ghost btn-icon btn-sm" onClick={() => setEditing(c.id)} title="Edit">
                        <i className="fa-solid fa-pen-to-square" />
                      </button>{' '}
                      <button className="btn btn-ghost btn-icon btn-sm" style={{ color: 'var(--danger)' }} onClick={() => remove(c)} title="Delete">
                        <i className="fa-solid fa-trash-can" />
                      </button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}

        {schema && <SchemaMacros schema={schema} />}

        {editing !== null && (
          <CallbackForm
            providerId={provider.id}
            schema={schema}
            callback={editTarget}
            onCancel={() => setEditing(null)}
            onDone={() => {
              setEditing(null);
              reload();
              onChanged?.();
            }}
          />
        )}
      </div>
    </TableCard>
  );
}

function SchemaMacros({ schema }: { schema: OfferSchema }) {
  const [open, setOpen] = useState(false);
  return (
    <div style={{ borderTop: '1px solid var(--border)', paddingTop: 12, marginBottom: 12 }}>
      <button className="btn btn-ghost btn-sm" onClick={() => setOpen((o) => !o)}>
        <i className={`fa-solid ${open ? 'fa-chevron-down' : 'fa-chevron-right'}`} style={{ marginRight: 6 }} />
        All {schema.label} postback macros ({schema.callbackMacros.length})
      </button>
      {open && (
        <table className="table" style={{ marginTop: 8, fontSize: 12 }}>
          <thead>
            <tr>
              <th>Macro</th>
              <th>Description</th>
            </tr>
          </thead>
          <tbody>
            {schema.callbackMacros.map((m) => (
              <tr key={m.token}>
                <td>
                  <code>{m.token}</code>
                </td>
                <td style={{ color: 'var(--text-muted)' }}>{m.description}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}

function CallbackForm({
  providerId,
  schema,
  callback,
  onCancel,
  onDone,
}: {
  providerId: number;
  schema: OfferSchema | null;
  callback: ProviderCallback | null;
  onCancel: () => void;
  onDone: () => void;
}) {
  const isEdit = callback !== null;

  const [name, setName] = useState(callback?.name ?? 'Postback');
  const [sigParam, setSigParam] = useState(callback?.signatureParam ?? (schema ? schema.signatureParam : 'sig'));
  const [algo, setAlgo] = useState(callback?.signatureAlgo ?? (schema ? schema.signatureAlgo : 'hmac_sha256'));
  const [sigSource] = useState(callback?.signatureSource ?? (schema ? schema.signatureSource : 'ordered_params'));
  const [secret, setSecret] = useState('');
  const [ipAllowlist, setIpAllowlist] = useState(callback?.ipAllowlist ?? '');
  const [active, setActive] = useState(callback?.active ?? true);

  // Schema providers: a per-field param-key map (the macro editor). Non-schema:
  // a raw JSON textarea (generic providers have no known field set).
  const initialMap: Record<string, string> = { ...(schema?.defaultParamMap ?? {}), ...(callback?.paramMap ?? {}) };
  const [mapping, setMapping] = useState<Record<string, string>>(initialMap);
  const [paramMapJson, setParamMapJson] = useState(
    JSON.stringify(callback?.paramMap ?? { transaction_id: 'txn', user_id: 'uid', amount: 'payout' }, null, 2)
  );

  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Extra keys present on the callback but not described by the schema.
  const extraKeys = schema
    ? Object.keys(mapping).filter((k) => !schema.callbackFields.some((f) => f.field === k))
    : [];

  const setKey = (field: string, key: string) => setMapping((m) => ({ ...m, [field]: key }));

  const submit = async () => {
    setError(null);

    let paramMap: Record<string, string> = {};
    if (schema) {
      Object.entries(mapping).forEach(([field, key]) => {
        if (key.trim()) paramMap[field] = key.trim();
      });
    } else {
      try {
        paramMap = paramMapJson.trim() ? JSON.parse(paramMapJson) : {};
      } catch {
        setError('Param map must be valid JSON.');
        return;
      }
    }

    setBusy(true);
    const body: Record<string, unknown> = {
      name,
      signature_param: sigParam,
      signature_algo: algo,
      signature_source: sigSource,
      ip_allowlist: ipAllowlist,
      active,
      param_map: paramMap,
    };
    // On edit a blank secret keeps the stored one; on create it's sent as-is.
    if (secret || !isEdit) body.secret = secret;

    try {
      if (isEdit) {
        await api(`/admin/providers/${providerId}/callbacks/${callback!.id}`, { method: 'PUT', body });
        toast('Callback updated.', 'success');
      } else {
        await api(`/admin/providers/${providerId}/callbacks`, { method: 'POST', body });
        toast('Callback created.', 'success');
      }
      onDone();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Save failed.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div style={{ borderTop: '1px solid var(--border)', paddingTop: 14, marginTop: 4 }}>
      <h4 style={{ margin: '0 0 12px' }}>{isEdit ? `Edit callback — ${callback!.name}` : 'Add callback'}</h4>
      {error && <div className="auth-error">{error}</div>}
      {schema && algo === 'none' && schema.allowsUnsigned && (
        <p style={{ fontSize: 12, color: 'var(--text-muted)', marginTop: 0 }}>
          {schema.label} authenticates callbacks by the verified <code>external_identifier</code>, so a signature is
          optional.
        </p>
      )}
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
            <option value="sha256_concat">sha256_concat</option>
            <option value="md5_concat">md5_concat</option>
            <option value="none">none{schema && schema.allowsUnsigned ? '' : ' (testing)'}</option>
          </select>
        </div>
        <div className="form-group">
          <label className="form-label">Secret{isEdit && callback?.hasSecret ? ' (set — leave blank to keep)' : ''}</label>
          <input className="form-input" value={secret} onChange={(e) => setSecret(e.target.value)} placeholder={isEdit && callback?.hasSecret ? 'unchanged' : ''} />
        </div>
      </div>
      <div className="form-row">
        <div className="form-group">
          <label className="form-label">IP allowlist (optional)</label>
          <input className="form-input" value={ipAllowlist} onChange={(e) => setIpAllowlist(e.target.value)} placeholder="comma-separated IPs" />
        </div>
        <div className="form-group">
          <label className="form-label">Active</label>
          <select className="form-select" value={active ? '1' : '0'} onChange={(e) => setActive(e.target.value === '1')}>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
        </div>
      </div>

      {schema ? (
        <div className="form-group">
          <label className="form-label">Parameter mapping</label>
          <div className="form-hint" style={{ marginBottom: 8 }}>
            Every macro this provider can send. The <strong>macro</strong> goes in the provider's postback URL under the{' '}
            <strong>request parameter</strong> name. Rows tagged <span className="tag tag-gray">raw</span> are captured in
            the callback payload for reference; the rest drive rewards and user matching. Clear a row to omit that macro.
          </div>
          <table className="table" style={{ fontSize: 12 }}>
            <thead>
              <tr>
                <th style={{ width: '32%' }}>Field</th>
                <th style={{ width: '30%' }}>Request parameter</th>
                <th>Macro</th>
              </tr>
            </thead>
            <tbody>
              {schema.callbackFields.map((f) => (
                <tr key={f.field}>
                  <td>
                    <div style={{ fontWeight: 600 }}>
                      {f.label}
                      {f.required && <span style={{ color: 'var(--danger)', marginLeft: 4 }}>*</span>}
                      {!f.mapped && <span className="tag tag-gray" style={{ marginLeft: 6 }}>raw</span>}
                    </div>
                    <div style={{ color: 'var(--text-muted)' }}>{f.description}</div>
                  </td>
                  <td>
                    <input
                      className="form-input"
                      style={{ fontFamily: 'monospace', fontSize: 12 }}
                      value={mapping[f.field] ?? ''}
                      placeholder={f.key}
                      onChange={(e) => setKey(f.field, e.target.value)}
                    />
                  </td>
                  <td>
                    <code>{f.macro}</code>
                  </td>
                </tr>
              ))}
              {extraKeys.map((k) => (
                <tr key={k}>
                  <td>
                    <div style={{ fontWeight: 600 }}>{k}</div>
                    <div style={{ color: 'var(--text-muted)' }}>Custom mapping</div>
                  </td>
                  <td>
                    <input
                      className="form-input"
                      style={{ fontFamily: 'monospace', fontSize: 12 }}
                      value={mapping[k] ?? ''}
                      onChange={(e) => setKey(k, e.target.value)}
                    />
                  </td>
                  <td>
                    <code>{`{${k}}`}</code>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <div className="form-group">
          <label className="form-label">Param map (JSON)</label>
          <textarea className="form-input" rows={4} value={paramMapJson} onChange={(e) => setParamMapJson(e.target.value)} style={{ fontFamily: 'monospace', fontSize: 12 }} />
        </div>
      )}

      <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
        <button className="btn btn-secondary btn-sm" onClick={onCancel}>
          Cancel
        </button>
        <button className="btn btn-primary btn-sm" disabled={busy} onClick={submit}>
          {busy ? 'Saving…' : isEdit ? 'Save changes' : 'Create callback'}
        </button>
      </div>
    </div>
  );
}
