import { useEffect, useState } from 'react';
import { TableCard } from '../components/TableCard';
import { Loading, ErrorState } from '../components/States';
import { useApiData } from '../hooks/useApiData';
import { api, ApiError } from '../lib/api';
import { toast } from '../store/toast';

interface Settings {
  externalIdPrefix: string;
}

export function SettingsPage() {
  const { data, loading, error } = useApiData<{ settings: Settings }>('/admin/settings');
  const [prefix, setPrefix] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (data) setPrefix(data.settings.externalIdPrefix);
  }, [data]);

  const save = async () => {
    setBusy(true);
    try {
      const res = await api<{ settings: Settings }>('/admin/settings', {
        method: 'PUT',
        body: { external_id_prefix: prefix },
      });
      setPrefix(res.settings.externalIdPrefix);
      toast('Settings saved.', 'success');
    } catch (e) {
      toast(e instanceof ApiError ? e.message : 'Save failed.', 'error');
    } finally {
      setBusy(false);
    }
  };

  return (
    <TableCard title="Settings">
      <div style={{ padding: 20, maxWidth: 560 }}>
        {loading ? (
          <Loading />
        ) : error ? (
          <ErrorState message={error} />
        ) : (
          <>
            <div className="form-group">
              <label className="form-label">External ID prefix</label>
              <input
                className="form-input"
                value={prefix}
                onChange={(e) => setPrefix(e.target.value)}
                placeholder="e.g. myapp"
              />
              <div className="form-hint">
                Site-level label for the offerwall <code>{'{external_id}'}</code> macro →{' '}
                <code>
                  {(prefix || '<prefix>')}
                  -&lt;user_id&gt;-&lt;user_hash&gt;
                </code>
                . Letters, digits and underscores only.
              </div>
            </div>
            <button className="btn btn-primary" disabled={busy} onClick={save}>
              {busy ? 'Saving…' : 'Save settings'}
            </button>
          </>
        )}
      </div>
    </TableCard>
  );
}
