import { useParams, useNavigate } from 'react-router-dom';
import { TableCard } from '../../components/TableCard';
import { StatusTag, RoleTag } from '../../components/StatusTag';
import { Loading, EmptyState, ErrorState } from '../../components/States';
import { useApiData } from '../../hooks/useApiData';
import { api, ApiError } from '../../lib/api';
import { fmtCoins, fmtDate } from '../../lib/format';
import { toast } from '../../store/toast';
import { askConfirm } from '../../store/confirm';

interface UserSummary {
  user: {
    id: number;
    email: string;
    type: string;
    status: string;
    displayName: string;
    createdAt: string;
    balance: number;
  };
  counts: { rewards: number; redemptions: number; tickets: number };
}
interface Click {
  id: number;
  kind: 'offer' | 'offerwall';
  offerName: string;
  providerName: string;
  providerOfferId: string;
  clickedAt: string;
}
interface Fingerprint {
  id: number;
  visitorId: string;
  ip: string;
  userAgent: string;
  platform: string;
  country: string;
  language: string;
  languages: string;
  timezone: string;
  screen: string;
  // Full fingerprinter-js result: { fingerprint, components (all collectors),
  // confidence, entropy, version, suspectAnalysis?: { riskLevel, ... } }.
  data: {
    components?: Record<string, unknown>;
    confidence?: number;
    entropy?: number;
    version?: string;
    suspectAnalysis?: { riskLevel?: string; score?: number };
    [k: string]: unknown;
  };
  createdAt: string;
}

const RISK_COLORS: Record<string, string> = {
  LOW: 'tag-green',
  MEDIUM: 'tag-amber',
  HIGH: 'tag-red',
};

function Summary({ id }: { id: string }) {
  const { data, loading, error } = useApiData<UserSummary>(`/admin/users/${id}`);
  if (loading) return <Loading />;
  if (error || !data) return <ErrorState message={error || 'User not found.'} />;
  const u = data.user;
  const cell = (label: string, value: React.ReactNode) => (
    <div>
      <div className="form-label">{label}</div>
      <div style={{ fontSize: 15 }}>{value}</div>
    </div>
  );
  return (
    <div style={{ display: 'flex', gap: 28, flexWrap: 'wrap', padding: '4px 4px 8px' }}>
      {cell('Name', u.displayName || '—')}
      {cell('Role', <RoleTag role={u.type} />)}
      {cell('Status', <StatusTag status={u.status} />)}
      {cell(
        'Balance',
        <span style={{ fontFamily: '"Space Grotesk", sans-serif', fontWeight: 700, color: 'var(--accent)' }}>
          {fmtCoins(u.balance)} coins
        </span>
      )}
      {cell('Rewards', data.counts.rewards)}
      {cell('Redemptions', data.counts.redemptions)}
      {cell('Tickets', data.counts.tickets)}
      {cell('Joined', fmtDate(u.createdAt))}
    </div>
  );
}

function Clicks({ id }: { id: string }) {
  const { data, loading, error } = useApiData<{ clicks: Click[] }>(`/admin/users/${id}/clicks`);
  const clicks = data?.clicks ?? [];
  return (
    <TableCard title="Clicks" count={clicks.length}>
      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorState message={error} />
      ) : clicks.length === 0 ? (
        <EmptyState icon="fa-hand-pointer" text="No clicks recorded." />
      ) : (
        <table className="table">
          <thead>
            <tr>
              <th>Offer</th>
              <th>Provider</th>
              <th>Type</th>
              <th>When</th>
            </tr>
          </thead>
          <tbody>
            {clicks.map((c) => (
              <tr key={c.id}>
                <td>
                  {c.offerName}
                  {c.providerOfferId && (
                    <span style={{ color: 'var(--text-muted)', fontSize: 11, marginLeft: 6 }}>#{c.providerOfferId}</span>
                  )}
                </td>
                <td>{c.providerName}</td>
                <td>
                  {c.kind === 'offer' ? (
                    <span className="tag tag-green">Offer</span>
                  ) : (
                    <span className="tag tag-gray">Offerwall</span>
                  )}
                </td>
                <td style={{ fontSize: 12, color: 'var(--text-muted)', whiteSpace: 'nowrap' }}>{fmtDate(c.clickedAt)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </TableCard>
  );
}

function Fingerprints({ id }: { id: string }) {
  const { data, loading, error, refetch } = useApiData<{ fingerprints: Fingerprint[] }>(
    `/admin/users/${id}/fingerprints`
  );
  const rows = data?.fingerprints ?? [];

  const remove = (fp: Fingerprint) =>
    askConfirm('Delete this device fingerprint?', async () => {
      try {
        await api(`/admin/users/${id}/fingerprints/${fp.id}`, { method: 'DELETE' });
        toast('Fingerprint deleted.', 'success');
        refetch();
      } catch (e) {
        toast(e instanceof ApiError ? e.message : 'Delete failed.', 'error');
      }
    });

  return (
    <TableCard title="Device Fingerprints" count={rows.length}>
      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorState message={error} />
      ) : rows.length === 0 ? (
        <EmptyState icon="fa-fingerprint" text="No fingerprints captured yet (recorded on the user's next login)." />
      ) : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 12, padding: 16 }}>
          {rows.map((fp) => (
            <div
              key={fp.id}
              style={{
                border: '1px solid var(--border)',
                borderRadius: 'var(--radius-sm)',
                padding: 14,
                background: 'var(--bg-elevated)',
              }}
            >
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
                <div style={{ fontFamily: 'monospace', fontSize: 12, color: 'var(--text-muted)' }}>
                  {fp.visitorId.slice(0, 16)}…
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                  <span style={{ fontSize: 12, color: 'var(--text-muted)' }}>{fmtDate(fp.createdAt)}</span>
                  <button className="btn btn-ghost btn-icon btn-sm" style={{ color: 'var(--danger)' }} onClick={() => remove(fp)} title="Delete fingerprint">
                    <i className="fa-solid fa-trash-can" />
                  </button>
                </div>
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(180px,1fr))', gap: '6px 20px', fontSize: 13 }}>
                <Field label="IP" value={fp.ip} />
                <Field label="Country" value={fp.country || '—'} />
                <Field label="Platform" value={fp.platform} />
                <Field label="Screen" value={fp.screen} />
                <Field label="Timezone" value={fp.timezone} />
                <Field label="Languages" value={fp.languages || fp.language || '—'} />
                <Field label="Confidence" value={fp.data.confidence != null ? `${fp.data.confidence}%` : '—'} />
                <Field label="Entropy" value={fp.data.entropy != null ? `${fp.data.entropy} bits` : '—'} />
                <div>
                  <span style={{ color: 'var(--text-muted)' }}>Bot risk: </span>
                  {fp.data.suspectAnalysis?.riskLevel ? (
                    <span className={`tag ${RISK_COLORS[fp.data.suspectAnalysis.riskLevel] ?? 'tag-gray'}`}>
                      {fp.data.suspectAnalysis.riskLevel}
                    </span>
                  ) : (
                    '—'
                  )}
                </div>
              </div>
              <div style={{ marginTop: 8, fontSize: 12, color: 'var(--text-muted)', wordBreak: 'break-word' }}>
                {fp.userAgent}
              </div>
              <details style={{ marginTop: 8 }}>
                <summary style={{ cursor: 'pointer', fontSize: 12, color: 'var(--accent)' }}>
                  All collectors{fp.data.components ? ` (${Object.keys(fp.data.components).length})` : ''}
                  {fp.data.version ? ` · fingerprinter-js v${fp.data.version}` : ''}
                </summary>
                <pre
                  style={{
                    marginTop: 6,
                    maxHeight: 280,
                    overflow: 'auto',
                    background: 'var(--bg)',
                    border: '1px solid var(--border)',
                    borderRadius: 'var(--radius-sm)',
                    padding: 10,
                    fontSize: 11,
                    lineHeight: 1.5,
                  }}
                >
                  {JSON.stringify(fp.data.components ?? fp.data, null, 2)}
                </pre>
              </details>
            </div>
          ))}
        </div>
      )}
    </TableCard>
  );
}

function Field({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <span style={{ color: 'var(--text-muted)' }}>{label}: </span>
      <span>{value || '—'}</span>
    </div>
  );
}

export function UserDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  if (!id) return null;

  return (
    <div>
      <button className="btn btn-secondary btn-sm" style={{ marginBottom: 16 }} onClick={() => navigate('/users')}>
        <i className="fa-solid fa-arrow-left" style={{ marginRight: 6 }} />
        Back to users
      </button>

      <TableCard title="User">
        <Summary id={id} />
      </TableCard>

      <div style={{ marginTop: 20 }}>
        <Fingerprints id={id} />
      </div>
      <div style={{ marginTop: 20 }}>
        <Clicks id={id} />
      </div>
    </div>
  );
}
