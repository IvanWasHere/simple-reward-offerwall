export function StatCard({
  label,
  value,
  icon,
  iconBg,
  iconColor,
  hint,
}: {
  label: string;
  value: string | number;
  icon: string;
  iconBg: string;
  iconColor: string;
  hint?: string;
}) {
  return (
    <div className="stat-card">
      <div className="stat-card-header">
        <span className="stat-card-label">{label}</span>
        <div className="stat-card-icon" style={{ background: iconBg, color: iconColor }}>
          <i className={'fa-solid ' + icon} />
        </div>
      </div>
      <div className="stat-card-value">{value}</div>
      {hint && (
        <div className="stat-card-change up">
          <i className="fa-solid fa-circle-info" style={{ fontSize: 10 }} />
          {hint}
        </div>
      )}
    </div>
  );
}
