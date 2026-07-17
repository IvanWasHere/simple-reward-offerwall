import { useEffect, type ReactNode } from 'react';

interface ModalProps {
  onClose: () => void;
  children: ReactNode;
  /** detail-modal-box (banner layout) vs modal-box (centered card). */
  variant?: 'box' | 'detail';
}

/**
 * Overlay modal with click-outside + Escape to close. Mirrors the prototype's
 * .modal-overlay / .modal-box / .detail-modal-box markup.
 */
export function Modal({ onClose, children, variant = 'box' }: ModalProps) {
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);

  return (
    <div
      className="modal-overlay"
      onClick={(e) => {
        if (e.target === e.currentTarget) onClose();
      }}
    >
      <div className={variant === 'detail' ? 'detail-modal-box' : 'modal-box'}>
        <button className="modal-close" onClick={onClose} aria-label="Close">
          <i className="fa-solid fa-xmark" />
        </button>
        {children}
      </div>
    </div>
  );
}
