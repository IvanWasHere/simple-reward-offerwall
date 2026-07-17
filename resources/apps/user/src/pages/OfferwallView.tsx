import { useParams, useNavigate, useLocation } from 'react-router-dom';
import { useApiData } from '../hooks/useApiData';
import { Loading, ErrorState } from '../components/States';

/**
 * Full-page iframe host for a provider offerwall. Fetches the per-user URL
 * (GET /offerwalls/:id/url — resolved + click-recorded server-side) and embeds
 * it in a sandboxed <iframe>.
 */
export function OfferwallView() {
  const { id } = useParams();
  const navigate = useNavigate();
  const location = useLocation();
  const name = (location.state as { name?: string } | null)?.name ?? 'Offerwall';

  const { data, loading, error } = useApiData<{ url: string }>(`/offerwalls/${id}/url`);

  return (
    <div className="page-enter">
      <div
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: 12,
          marginBottom: 16,
        }}
      >
        <button className="filter-btn" onClick={() => navigate(-1)}>
          <i className="fa-solid fa-arrow-left" style={{ marginRight: 6 }} />
          Back
        </button>
        <div className="section-title" style={{ marginBottom: 0 }}>
          <i className="fa-solid fa-window-maximize" />
          {name}
        </div>
      </div>

      {loading ? (
        <Loading label="Opening offerwall…" />
      ) : error || !data?.url ? (
        <ErrorState message={error || 'This offerwall could not be opened.'} />
      ) : (
        <iframe
          title={name}
          src={data.url}
          sandbox="allow-scripts allow-forms allow-popups allow-same-origin allow-popups-to-escape-sandbox"
          referrerPolicy="no-referrer-when-downgrade"
          style={{
            width: '100%',
            height: 'calc(100vh - 220px)',
            minHeight: 480,
            border: '1px solid var(--border)',
            borderRadius: 'var(--radius)',
            background: 'var(--bg-card)',
          }}
        />
      )}
    </div>
  );
}
