import { useCallback, useEffect, useState } from 'react';
import { api, ApiError } from '../lib/api';

interface State<T> {
  data: T | null;
  loading: boolean;
  error: string | null;
}

/**
 * Minimal GET-with-refetch hook. Re-fetches whenever `path` changes; call
 * `refetch()` after a mutation to refresh.
 */
export function useApiData<T>(path: string): State<T> & { refetch: () => void } {
  const [state, setState] = useState<State<T>>({ data: null, loading: true, error: null });
  const [tick, setTick] = useState(0);

  const refetch = useCallback(() => setTick((t) => t + 1), []);

  useEffect(() => {
    let alive = true;
    setState((s) => ({ ...s, loading: true, error: null }));
    api<T>(path)
      .then((data) => {
        if (alive) setState({ data, loading: false, error: null });
      })
      .catch((e: unknown) => {
        if (alive) {
          setState({
            data: null,
            loading: false,
            error: e instanceof ApiError ? e.message : 'Failed to load.',
          });
        }
      });
    return () => {
      alive = false;
    };
  }, [path, tick]);

  return { ...state, refetch };
}
