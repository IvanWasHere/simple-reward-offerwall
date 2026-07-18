import { useSyncExternalStore } from 'react';

export interface ConfirmRequest {
  text: string;
  confirmLabel: string;
  danger: boolean;
  onConfirm: () => void;
}

let current: ConfirmRequest | null = null;
const listeners = new Set<() => void>();
const emit = () => listeners.forEach((l) => l());

/** Open a confirm dialog. Callable from anywhere. */
export function askConfirm(
  text: string,
  onConfirm: () => void,
  opts: { confirmLabel?: string; danger?: boolean } = {}
): void {
  current = {
    text,
    onConfirm,
    confirmLabel: opts.confirmLabel ?? 'Delete',
    danger: opts.danger ?? true,
  };
  emit();
}

export function closeConfirm(): void {
  current = null;
  emit();
}

export function useConfirm(): ConfirmRequest | null {
  return useSyncExternalStore(
    (cb) => {
      listeners.add(cb);
      return () => listeners.delete(cb);
    },
    () => current
  );
}
