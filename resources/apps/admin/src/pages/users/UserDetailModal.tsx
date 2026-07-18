import { Modal } from '../../components/Modal';
import { useApiData } from '../../hooks/useApiData';
import { Loading, EmptyState, ErrorState } from '../../components/States';
import { StatusTag, RoleTag } from '../../components/StatusTag';
import { fmtCoins, fmtDate } from '../../lib/format';
import type { UserRow } from '../../lib/types';

interface Click {
  id: number;
  kind: 'offer' | 'offerwall';
  offerName: string;
  providerName: string;
  providerOfferId: string;
  targetUrl: string;
  clickedAt: string;
}

export function UserDetailModal({ user, onClose }: { user: UserRow; onClose: () => void }) {
  const { data, loading, error } = useApiData<{ clicks: Click[] }>(`/admin/users/${user.id}/clicks`);
  const clicks = data?.clicks ?? [];

  return (
    <Modal title={user.email} onClose={onClose} width={680}>
      <div style={{ display: 'flex', gap: 20, flexWrap: 'wrap', marginBottom: 18 }}>
        <div>
          <div className="form-label">Role</div>
          <RoleTag role={user.type} />
        </div>
        <div>
          <div className="form-label">Status</div>
          <StatusTag status={user.status} />
        </div>
        <div>
          <div className="form-label">Balance</div>
          <div style={{ fontFamily: '"Space Grotesk", sans-serif', fontWeight: 700, color: 'var(--accent)' }}>
            {fmtCoins(user.balance)} coins
          </div>
        </div>
      </div>

      <div className="detail-section-label" style={{ fontSize: 12, fontWeight: 600, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.5px', marginBottom: 10 }}>
        Clicks {clicks.length > 0 && `(${clicks.length})`}
      </div>

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorState message={error} />
      ) : clicks.length === 0 ? (
        <EmptyState icon="fa-hand-pointer" text="This user hasn't clicked any offers." />
      ) : (
        <div style={{ maxHeight: 420, overflowY: 'auto' }}>
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
        </div>
      )}
    </Modal>
  );
}
