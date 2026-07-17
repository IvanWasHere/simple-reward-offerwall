import { formatCoins } from '../lib/format';

export function BalanceBar({
  balance,
  completed,
}: {
  balance: number;
  completed: number;
}) {
  return (
    <div className="balance-bar">
      <div className="balance-left">
        <div className="balance-icon">
          <i className="fa-solid fa-coins" />
        </div>
        <div>
          <div className="balance-label">Your Balance</div>
          <div className="balance-value">
            {formatCoins(balance)}
            <span className="balance-unit">coins</span>
          </div>
        </div>
      </div>
      <div className="completed-badge">
        <i className="fa-solid fa-check-circle" />
        {completed} reward{completed === 1 ? '' : 's'}
      </div>
    </div>
  );
}
