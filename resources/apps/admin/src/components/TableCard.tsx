import type { ReactNode } from 'react';

/** A titled table/panel card (the prototype's .table-wrap + .table-header). */
export function TableCard({
  title,
  count,
  actions,
  children,
}: {
  title: string;
  count?: number | string;
  actions?: ReactNode;
  children: ReactNode;
}) {
  return (
    <div className="table-wrap">
      <div className="table-header">
        <div className="table-title">
          {title}
          {count !== undefined && (
            <span
              style={{ fontSize: 12, color: 'var(--text-muted)', fontWeight: 400, marginLeft: 8 }}
            >
              {count} total
            </span>
          )}
        </div>
        {actions && <div className="table-actions">{actions}</div>}
      </div>
      {children}
    </div>
  );
}
