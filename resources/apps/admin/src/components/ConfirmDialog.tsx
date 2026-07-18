import { useConfirm, closeConfirm } from '../store/confirm';

export function ConfirmDialog() {
  const req = useConfirm();
  if (!req) return null;

  return (
    <div
      className="modal-overlay"
      onClick={(e) => {
        if (e.target === e.currentTarget) closeConfirm();
      }}
    >
      <div className="modal-box" style={{ maxWidth: 400 }}>
        <div className="confirm-body">
          <div
            className="confirm-icon"
            style={{ background: 'var(--highlight-glow)', color: 'var(--danger)' }}
          >
            <i className="fa-solid fa-triangle-exclamation" />
          </div>
          <div style={{ fontSize: 17, fontWeight: 700, fontFamily: '"Space Grotesk", sans-serif' }}>
            Are you sure?
          </div>
          <div className="confirm-text">{req.text}</div>
        </div>
        <div className="modal-footer" style={{ justifyContent: 'center', paddingTop: 8 }}>
          <button className="btn btn-secondary" onClick={closeConfirm}>
            Cancel
          </button>
          <button
            className={'btn ' + (req.danger ? 'btn-danger' : 'btn-primary')}
            onClick={() => {
              req.onConfirm();
              closeConfirm();
            }}
          >
            {req.confirmLabel}
          </button>
        </div>
      </div>
    </div>
  );
}
