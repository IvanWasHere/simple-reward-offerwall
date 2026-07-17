import { Loading, EmptyState } from '../../components/States';
import { useApiData } from '../../hooks/useApiData';
import { formatCoins } from '../../lib/format';
import { toast } from '../../store/toast';

interface Referral {
  code: string;
  referredCount: number;
  coinsEarned: number;
  shareUrl: string;
}

export function ReferralPage() {
  const { data, loading } = useApiData<{ referral: Referral }>('/me/referral');
  const r = data?.referral;

  const copy = () => {
    if (!r) return;
    navigator.clipboard?.writeText(r.shareUrl).then(
      () => toast('Referral link copied!', 'success'),
      () => toast('Could not copy link.', 'error')
    );
  };

  return (
    <div className="page-enter">
      <div className="section-title">
        <i className="fa-solid fa-user-plus" />
        Refer a Friend
      </div>
      {loading ? (
        <Loading />
      ) : !r ? (
        <EmptyState icon="fa-user-plus" message="Referral unavailable." />
      ) : (
        <div className="wall-section" style={{ padding: 20 }}>
          <div className="profile-stats" style={{ marginBottom: 16 }}>
            <div className="profile-stat">
              <div className="profile-stat-value">{r.referredCount}</div>
              <div className="profile-stat-label">Referred</div>
            </div>
            <div className="profile-stat">
              <div className="profile-stat-value">{formatCoins(r.coinsEarned)}</div>
              <div className="profile-stat-label">Coins earned</div>
            </div>
            <div className="profile-stat">
              <div className="profile-stat-value" style={{ fontSize: 16 }}>
                {r.code}
              </div>
              <div className="profile-stat-label">Your code</div>
            </div>
          </div>
          <div
            className="detail-instructions"
            style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12 }}
          >
            <span style={{ wordBreak: 'break-all' }}>{r.shareUrl}</span>
            <button className="claim-btn" style={{ marginTop: 0, flexShrink: 0 }} onClick={copy}>
              Copy
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
