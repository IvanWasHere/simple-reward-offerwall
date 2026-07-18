/** Shared loading / empty / error rows for tables and cards. */

export function Loading({ label = 'Loading…' }: { label?: string }) {
  return (
    <div className="table-empty">
      <i className="fa-solid fa-circle-notch fa-spin" />
      <p>{label}</p>
    </div>
  );
}

export function EmptyState({ icon = 'fa-inbox', text }: { icon?: string; text: string }) {
  return (
    <div className="table-empty">
      <i className={'fa-solid ' + icon} />
      <p>{text}</p>
    </div>
  );
}

export function ErrorState({ message }: { message: string }) {
  return (
    <div className="table-empty">
      <i className="fa-solid fa-triangle-exclamation" style={{ color: 'var(--danger)' }} />
      <p>{message}</p>
    </div>
  );
}
