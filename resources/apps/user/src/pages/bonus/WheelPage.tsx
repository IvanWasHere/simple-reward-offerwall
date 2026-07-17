import { LuckyWheel } from '../../components/LuckyWheel';

export function WheelPage() {
  return (
    <div className="page-enter">
      <div className="section-title">
        <i className="fa-solid fa-dharmachakra" />
        Lucky Wheel
      </div>
      <div className="wall-section" style={{ display: 'flex', justifyContent: 'center', padding: '32px 20px' }}>
        <LuckyWheel />
      </div>
    </div>
  );
}
