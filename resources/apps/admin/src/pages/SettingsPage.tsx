import { useEffect, useState } from 'react';
import { TableCard } from '../components/TableCard';
import { Modal } from '../components/Modal';
import { Loading, EmptyState, ErrorState } from '../components/States';
import { useApiData } from '../hooks/useApiData';
import { api, ApiError } from '../lib/api';
import { toast } from '../store/toast';

interface Settings {
  externalIdPrefix: string;
  appName: string;
  appIconId: number;
  appIconUrl: string;
}
interface MediaItem {
  id: number;
  title: string;
  url: string;
  thumb: string;
  mime: string;
}

function MediaPicker({
  onClose,
  onPick,
}: {
  onClose: () => void;
  onPick: (m: MediaItem) => void;
}) {
  const [q, setQ] = useState('');
  const [query, setQuery] = useState('');
  const { data, loading, error } = useApiData<{ media: MediaItem[] }>(
    query ? `/admin/media?s=${encodeURIComponent(query)}` : '/admin/media'
  );
  const items = data?.media ?? [];

  return (
    <Modal
      title="Select app icon"
      onClose={onClose}
      width={640}
      footer={
        <button className="btn btn-secondary" onClick={onClose}>
          Cancel
        </button>
      }
    >
      <form
        className="search-box"
        style={{ marginBottom: 14 }}
        onSubmit={(e) => {
          e.preventDefault();
          setQuery(q);
        }}
      >
        <i className="fa-solid fa-magnifying-glass" />
        <input className="form-input" placeholder="Search media…" value={q} onChange={(e) => setQ(e.target.value)} />
      </form>
      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorState message={error} />
      ) : items.length === 0 ? (
        <EmptyState icon="fa-image" text="No images in the media library. Upload one in wp-admin › Media." />
      ) : (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(96px,1fr))', gap: 10, maxHeight: 420, overflowY: 'auto' }}>
          {items.map((m) => (
            <button
              key={m.id}
              onClick={() => onPick(m)}
              title={m.title}
              style={{
                border: '1px solid var(--border)',
                borderRadius: 'var(--radius-sm)',
                padding: 6,
                background: 'var(--bg-elevated)',
                cursor: 'pointer',
                aspectRatio: '1',
              }}
            >
              <img src={m.thumb || m.url} alt={m.title} style={{ width: '100%', height: '100%', objectFit: 'contain' }} />
            </button>
          ))}
        </div>
      )}
    </Modal>
  );
}

export function SettingsPage() {
  const { data, loading, error } = useApiData<{ settings: Settings }>('/admin/settings');
  const [prefix, setPrefix] = useState('');
  const [appName, setAppName] = useState('');
  const [iconId, setIconId] = useState(0);
  const [iconUrl, setIconUrl] = useState('');
  const [picker, setPicker] = useState(false);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (data) {
      setPrefix(data.settings.externalIdPrefix);
      setAppName(data.settings.appName);
      setIconId(data.settings.appIconId);
      setIconUrl(data.settings.appIconUrl);
    }
  }, [data]);

  const save = async () => {
    setBusy(true);
    try {
      const res = await api<{ settings: Settings }>('/admin/settings', {
        method: 'PUT',
        body: { external_id_prefix: prefix, app_name: appName, app_icon_id: iconId },
      });
      setPrefix(res.settings.externalIdPrefix);
      setAppName(res.settings.appName);
      setIconId(res.settings.appIconId);
      setIconUrl(res.settings.appIconUrl);
      toast('Settings saved.', 'success');
    } catch (e) {
      toast(e instanceof ApiError ? e.message : 'Save failed.', 'error');
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <TableCard title="Branding">
        <div style={{ padding: 20, maxWidth: 620 }}>
          {loading ? (
            <Loading />
          ) : error ? (
            <ErrorState message={error} />
          ) : (
            <>
              <div className="form-group">
                <label className="form-label">App name</label>
                <input className="form-input" value={appName} onChange={(e) => setAppName(e.target.value)} placeholder="RewardVault" />
                <div className="form-hint">Shown as the brand name across the user &amp; admin apps.</div>
              </div>

              <div className="form-group">
                <label className="form-label">App icon</label>
                <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
                  <div
                    style={{
                      width: 64,
                      height: 64,
                      borderRadius: 'var(--radius-sm)',
                      border: '1px solid var(--border)',
                      background: 'var(--bg-elevated)',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      overflow: 'hidden',
                    }}
                  >
                    {iconUrl ? (
                      <img src={iconUrl} alt="" style={{ width: '100%', height: '100%', objectFit: 'contain' }} />
                    ) : (
                      <i className="fa-solid fa-vault" style={{ fontSize: 26, color: 'var(--text-muted)' }} />
                    )}
                  </div>
                  <button className="btn btn-secondary btn-sm" onClick={() => setPicker(true)}>
                    <i className="fa-solid fa-image" style={{ marginRight: 6 }} />
                    Choose from media library
                  </button>
                  {iconId > 0 && (
                    <button
                      className="btn btn-ghost btn-sm"
                      style={{ color: 'var(--danger)' }}
                      onClick={() => {
                        setIconId(0);
                        setIconUrl('');
                      }}
                    >
                      Remove
                    </button>
                  )}
                </div>
                <div className="form-hint">Falls back to the default vault icon when none is set.</div>
              </div>

              <div className="form-group">
                <label className="form-label">External ID prefix</label>
                <input className="form-input" value={prefix} onChange={(e) => setPrefix(e.target.value)} placeholder="e.g. myapp" />
                <div className="form-hint">
                  Site-level label for the offerwall <code>{'{external_id}'}</code> macro.
                </div>
              </div>

              <button className="btn btn-primary" disabled={busy} onClick={save}>
                {busy ? 'Saving…' : 'Save settings'}
              </button>
            </>
          )}
        </div>
      </TableCard>

      {picker && (
        <MediaPicker
          onClose={() => setPicker(false)}
          onPick={(m) => {
            setIconId(m.id);
            setIconUrl(m.url);
            setPicker(false);
          }}
        />
      )}
    </>
  );
}
