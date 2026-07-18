import { useToasts } from '../store/toast';

export function ToastHost() {
  const toasts = useToasts();
  return (
    <div className="toast-wrap">
      {toasts.map((t) => (
        <div className={'toast ' + t.type} key={t.id}>
          <i className={'fa-solid ' + t.icon} />
          <span>{t.message}</span>
        </div>
      ))}
    </div>
  );
}
