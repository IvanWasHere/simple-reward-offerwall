/**
 * A tiny custom hook used by the demo to show how to extract stateful logic
 * into a reusable unit. Intentionally trivial — replace with your own.
 */
import { useCallback, useState } from '@wordpress/element';

export interface CounterApi {
  count: number;
  increment: () => void;
  decrement: () => void;
  reset: () => void;
  set: (value: number) => void;
}

export function useCounter(initial: number = 0): CounterApi {
  const [count, setCount] = useState(initial);

  const increment = useCallback(() => setCount((c) => c + 1), []);
  const decrement = useCallback(() => setCount((c) => c - 1), []);
  const reset = useCallback(() => setCount(initial), [initial]);
  const set = useCallback((value: number) => setCount(value), []);

  return { count, increment, decrement, reset, set };
}
