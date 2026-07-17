import { useState, type ReactNode } from 'react';

/** Collapsible bonus section (the prototype's .bonus-dropdown accordion). */
export function BonusDropdown({
  icon,
  iconColor,
  iconBg,
  title,
  subtitle,
  maxHeight = 600,
  defaultOpen = false,
  children,
}: {
  icon: string;
  iconColor: string;
  iconBg: string;
  title: string;
  subtitle: string;
  maxHeight?: number;
  defaultOpen?: boolean;
  children: ReactNode;
}) {
  const [open, setOpen] = useState(defaultOpen);
  return (
    <div className="bonus-dropdown">
      <div className="bonus-dropdown-header" onClick={() => setOpen((o) => !o)}>
        <div className="bonus-dropdown-left">
          <div className="bonus-dropdown-icon" style={{ background: iconBg, color: iconColor }}>
            <i className={'fa-solid ' + icon} />
          </div>
          <div>
            <div className="bonus-dropdown-title">{title}</div>
            <div className="bonus-dropdown-sub">{subtitle}</div>
          </div>
        </div>
        <i className={'fa-solid fa-chevron-down wall-chevron' + (open ? ' open' : '')} />
      </div>
      <div
        className={'bonus-dropdown-body' + (open ? ' open' : '')}
        style={open ? { maxHeight } : undefined}
      >
        <div className="bonus-dropdown-inner">{children}</div>
      </div>
    </div>
  );
}
