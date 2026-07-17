import { useMemo } from 'react';
import { Loading, EmptyState, ErrorState } from '../components/States';
import { useApiData } from '../hooks/useApiData';
import { normalizeOffer } from '../lib/offers';
import { api, ApiError } from '../lib/api';
import { formatCoins } from '../lib/format';
import { toast } from '../store/toast';
import type { ApiOffer, UiOffer } from '../lib/types';

const SURVEY_COLORS = ['#00b67a', '#13a0e8', '#005f7b', '#4179d6', '#ff2d6c', '#386cb5'];

async function startSurvey(s: UiOffer) {
  if (!s.providerId || !s.providerOfferId) {
    toast('This survey is not available right now.', 'error');
    return;
  }
  try {
    const res = await api<{ url: string }>('/clicks', {
      method: 'POST',
      body: { provider_id: s.providerId, provider_offer_id: s.providerOfferId },
    });
    window.open(res.url, '_blank', 'noopener,noreferrer');
    toast('Survey opened in a new tab.', 'success');
  } catch (e) {
    toast(e instanceof ApiError ? e.message : 'Could not open the survey.', 'error');
  }
}

export function SurveysPage() {
  const { data, loading, error } = useApiData<{ surveys: ApiOffer[] }>('/surveys');
  const surveys = useMemo(
    () => (data?.surveys ?? []).map(normalizeOffer).sort((a, b) => b.coins - a.coins),
    [data]
  );

  return (
    <div className="page-enter">
      <div className="section-title">
        <i className="fa-solid fa-clipboard-list" />
        Available Surveys
        {surveys.length > 0 && (
          <span style={{ fontSize: 14, color: 'var(--text-muted)', fontWeight: 400, marginLeft: 8 }}>
            {surveys.length} available
          </span>
        )}
      </div>

      {loading ? (
        <Loading label="Loading surveys…" />
      ) : error ? (
        <ErrorState message={error} />
      ) : surveys.length === 0 ? (
        <EmptyState icon="fa-clipboard-list" message="No surveys available right now." />
      ) : (
        <div className="survey-grid">
          {surveys.map((s, i) => {
            const color = SURVEY_COLORS[i % SURVEY_COLORS.length];
            return (
              <div className="survey-card" key={s.key} onClick={() => startSurvey(s)}>
                <div className="survey-card-top">
                  <div className="survey-icon" style={{ background: color + '22', color }}>
                    <i className="fa-solid fa-clipboard-question" />
                  </div>
                  <div>
                    <div className="survey-name">{s.name}</div>
                    <div className="survey-provider">
                      <i className="fa-solid fa-building" style={{ marginRight: 4, fontSize: 10 }} />
                      {s.providerName}
                    </div>
                  </div>
                </div>
                <div className="survey-card-bottom">
                  <div className="survey-eta">
                    <i className="fa-solid fa-list-check" />
                    {s.tasks.length} task{s.tasks.length === 1 ? '' : 's'}
                  </div>
                  <div className="survey-payout">+{formatCoins(s.coins)}</div>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
