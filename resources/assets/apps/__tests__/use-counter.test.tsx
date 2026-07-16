import { act, renderHook } from '@testing-library/react';

import { useCounter } from '../use-counter';

describe('useCounter', () => {
  it('starts at 0 by default', () => {
    const { result } = renderHook(() => useCounter());
    expect(result.current.count).toBe(0);
  });

  it('starts at the provided initial value', () => {
    const { result } = renderHook(() => useCounter(7));
    expect(result.current.count).toBe(7);
  });

  it('increments and decrements', () => {
    const { result } = renderHook(() => useCounter(5));
    act(() => result.current.increment());
    expect(result.current.count).toBe(6);
    act(() => result.current.decrement());
    expect(result.current.count).toBe(5);
  });

  it('resets to the initial value', () => {
    const { result } = renderHook(() => useCounter(3));
    act(() => {
      result.current.increment();
      result.current.increment();
    });
    expect(result.current.count).toBe(5);
    act(() => result.current.reset());
    expect(result.current.count).toBe(3);
  });

  it('supports absolute set', () => {
    const { result } = renderHook(() => useCounter());
    act(() => result.current.set(42));
    expect(result.current.count).toBe(42);
  });
});
