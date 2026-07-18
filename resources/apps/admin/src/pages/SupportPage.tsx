import { useState } from 'react';
import { TableCard } from '../components/TableCard';
import { Modal } from '../components/Modal';
import { StatusTag } from '../components/StatusTag';
import { Loading, EmptyState, ErrorState } from '../components/States';
import { useApiData } from '../hooks/useApiData';
import { api, ApiError } from '../lib/api';
import { fmtDate } from '../lib/format';
import { toast } from '../store/toast';

interface TicketRow {
  id: number;
  subject: string;
  status: string;
  user_email: string | null;
  assignee_email: string | null;
  last_message_at: string | null;
}

interface Message {
  id: number;
  authorType: string;
  body: string;
  createdAt: string;
}
interface Ticket {
  id: number;
  subject: string;
  status: string;
  messages: Message[];
}

const FILTERS = ['open', 'pending', 'closed', 'all'] as const;

export function SupportPage() {
  const [filter, setFilter] = useState<(typeof FILTERS)[number]>('open');
  const path = filter === 'all' ? '/support/queue' : `/support/queue?status=${filter}`;
  const { data, loading, error, refetch } = useApiData<{ tickets: TicketRow[] }>(path);
  const [openId, setOpenId] = useState<number | null>(null);
  const rows = data?.tickets ?? [];

  return (
    <>
      <TableCard
        title="Support Requests"
        count={rows.length}
        actions={
          <div style={{ display: 'flex', gap: 6 }}>
            {FILTERS.map((f) => (
              <button
                key={f}
                className={'btn btn-sm ' + (filter === f ? 'btn-primary' : 'btn-secondary')}
                onClick={() => setFilter(f)}
              >
                {f}
              </button>
            ))}
          </div>
        }
      >
        {loading ? (
          <Loading />
        ) : error ? (
          <ErrorState message={error} />
        ) : rows.length === 0 ? (
          <EmptyState icon="fa-life-ring" text="No tickets in this view." />
        ) : (
          <table className="table">
            <thead>
              <tr>
                <th>Subject</th>
                <th>User</th>
                <th>Assignee</th>
                <th>Status</th>
                <th>Last activity</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((t) => (
                <tr key={t.id} style={{ cursor: 'pointer' }} onClick={() => setOpenId(t.id)}>
                  <td>{t.subject}</td>
                  <td>{t.user_email || '—'}</td>
                  <td style={{ color: 'var(--text-muted)' }}>{t.assignee_email || 'Unassigned'}</td>
                  <td>
                    <StatusTag status={t.status} />
                  </td>
                  <td style={{ fontSize: 12, color: 'var(--text-muted)' }}>{fmtDate(t.last_message_at)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </TableCard>

      {openId !== null && (
        <TicketModal
          ticketId={openId}
          onClose={() => setOpenId(null)}
          onChanged={refetch}
        />
      )}
    </>
  );
}

function TicketModal({
  ticketId,
  onClose,
  onChanged,
}: {
  ticketId: number;
  onClose: () => void;
  onChanged: () => void;
}) {
  const { data, loading, error, refetch } = useApiData<{ ticket: Ticket }>(
    `/support/tickets/${ticketId}`
  );
  const [reply, setReply] = useState('');
  const [busy, setBusy] = useState(false);
  const ticket = data?.ticket;

  const send = async () => {
    if (!reply.trim()) return;
    setBusy(true);
    try {
      await api(`/support/tickets/${ticketId}/messages`, { method: 'POST', body: { message: reply } });
      setReply('');
      refetch();
      onChanged();
    } catch (e) {
      toast(e instanceof ApiError ? e.message : 'Send failed.', 'error');
    } finally {
      setBusy(false);
    }
  };

  const setStatus = async (status: string) => {
    try {
      await api(`/support/tickets/${ticketId}`, { method: 'PUT', body: { status } });
      toast(`Ticket ${status}.`, 'success');
      refetch();
      onChanged();
    } catch (e) {
      toast(e instanceof ApiError ? e.message : 'Update failed.', 'error');
    }
  };

  return (
    <Modal
      title={ticket ? ticket.subject : 'Ticket'}
      onClose={onClose}
      width={600}
      footer={
        <div style={{ display: 'flex', gap: 6, width: '100%', justifyContent: 'space-between' }}>
          <div style={{ display: 'flex', gap: 6 }}>
            {['open', 'pending', 'closed'].map((s) => (
              <button key={s} className={'btn btn-sm ' + (ticket?.status === s ? 'btn-primary' : 'btn-secondary')} onClick={() => setStatus(s)}>
                {s}
              </button>
            ))}
          </div>
        </div>
      }
    >
      {loading ? (
        <Loading />
      ) : error || !ticket ? (
        <ErrorState message={error || 'Ticket not found.'} />
      ) : (
        <>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 10, maxHeight: 340, overflowY: 'auto', marginBottom: 14 }}>
            {ticket.messages.map((m) => {
              const staff = m.authorType !== 'user';
              return (
                <div
                  key={m.id}
                  style={{
                    alignSelf: staff ? 'flex-end' : 'flex-start',
                    maxWidth: '80%',
                    background: staff ? 'var(--accent-glow)' : 'var(--bg-elevated)',
                    border: '1px solid var(--border)',
                    borderRadius: 'var(--radius-sm)',
                    padding: '10px 12px',
                  }}
                >
                  <div style={{ fontSize: 11, color: 'var(--text-muted)', marginBottom: 4 }}>
                    {staff ? 'Staff' : 'User'} · {fmtDate(m.createdAt)}
                  </div>
                  <div style={{ fontSize: 14 }}>{m.body}</div>
                </div>
              );
            })}
          </div>
          <div className="form-group" style={{ marginBottom: 8 }}>
            <textarea className="form-input" rows={3} placeholder="Write a reply…" value={reply} onChange={(e) => setReply(e.target.value)} />
          </div>
          <button className="btn btn-primary" disabled={busy || !reply.trim()} onClick={send}>
            {busy ? 'Sending…' : 'Send reply'}
          </button>
        </>
      )}
    </Modal>
  );
}
