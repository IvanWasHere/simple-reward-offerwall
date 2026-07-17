/** Shared loading / empty / error placeholders. */

export function Loading({ label = 'Loading…' }: { label?: string }) {
  return (
    <div className="empty-state">
      <i className="fa-solid fa-circle-notch fa-spin" />
      <p>{label}</p>
    </div>
  );
}

export function EmptyState({ icon = 'fa-inbox', message }: { icon?: string; message: string }) {
  return (
    <div className="empty-state">
      <i className={'fa-solid ' + icon} />
      <p>{message}</p>
    </div>
  );
}

export function ErrorState({ message }: { message: string }) {
  return (
    <div className="empty-state">
      <i className="fa-solid fa-triangle-exclamation" style={{ color: 'var(--danger)' }} />
      <p>{message}</p>
    </div>
  );
}
