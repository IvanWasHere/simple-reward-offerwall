import { useMemo } from 'react';
import { useApiData } from './useApiData';
import { normalizeOffer } from '../lib/offers';
import type { ApiOffer, UiOffer } from '../lib/types';

export function useOffers(): {
  offers: UiOffer[];
  loading: boolean;
  error: string | null;
  refetch: () => void;
} {
  const { data, loading, error, refetch } = useApiData<{ offers: ApiOffer[] }>('/offers');
  const offers = useMemo(
    () => (data?.offers ?? []).map(normalizeOffer).sort((a, b) => b.coins - a.coins),
    [data]
  );
  return { offers, loading, error, refetch };
}
