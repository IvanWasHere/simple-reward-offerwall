import { useState } from 'react';
import { Modal } from '../components/Modal';
import { Loading, EmptyState, ErrorState } from '../components/States';
import { useApiData } from '../hooks/useApiData';
import { api, ApiError } from '../lib/api';
import { toast } from '../store/toast';

interface Click {
  clickId: number;
  providerName: string;
  providerOfferId: string;
  offerName: string;
  clickedAt: string;
}
interface TicketRow {
  id: number;
  subject: string;
  status: string;
  last_message_at: string | null;
  created_at: string;
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

function when(d: string | null): string {
  if (!d) return '—';
  const date = new Date(d.replace(' ', 'T') + 'Z');
  return isNaN(date.getTime()) ? '—' : date.toLocaleDateString();
}

function NewRequest({ onCreated }: { onCreated: () => void }) {
  const clicks = useApiData<{ clicks: Click[] }>('/me/clicks?days=30');
  const [selected, setSelected] = useState<Click | null>(null);
  const [message, setMessage] = useState('');
  const [busy, setBusy] = useState(false);
  const list = clicks.data?.clicks ?? [];

  const submit = async () => {
    if (!message.trim()) {
      toast('Please describe the issue.', 'error');
      return;
    }
    setBusy(true);
    const subject = selected ? `Issue with ${selected.offerName}` : 'Support request';
    const context = selected
      ? `Offer: ${selected.offerName} (${selected.providerName}, clicked ${when(selected.clickedAt)})\n\n`
      : '';
    try {
      await api('/support/tickets', {
        method: 'POST',
        body: { subject, message: context + message.trim() },
      });
      toast('Support ticket submitted.', 'success');
      setSelected(null);
      setMessage('');
      onCreated();
    } catch (e) {
      toast(e instanceof ApiError ? e.message : 'Could not submit.', 'error');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="section-block">
      <div className="section-title">
        <i className="fa-solid fa-circle-plus" />
        New Support Request
      </div>

      <div className="detail-section-label">Which offer is this about? (last 30 days)</div>
      {clicks.loading ? (
        <Loading label="Loading your recent offers…" />
      ) : clicks.error ? (
        <ErrorState message={clicks.error} />
      ) : list.length === 0 ? (
        <div className="reward-item" style={{ marginBottom: 12 }}>
          <div className="reward-desc">No offer clicks in the last 30 days — you can still send a general request below.</div>
        </div>
      ) : (
        <div className="rewards-list" style={{ marginBottom: 16 }}>
          {list.map((c) => {
            const isSel = selected?.clickId === c.clickId;
            return (
              <button
                key={c.clickId}
                className="reward-item"
                style={{
                  textAlign: 'left',
                  cursor: 'pointer',
                  width: '100%',
                  border: isSel ? '1px solid var(--accent)' : '1px solid var(--border)',
                  background: isSel ? 'var(--accent-glow)' : 'var(--bg-elevated)',
                }}
                onClick={() => setSelected(isSel ? null : c)}
              >
                <div className="reward-left">
                  <div className="reward-icon" style={{ background: 'var(--accent-glow)', color: 'var(--accent)' }}>
                    <i className={'fa-solid ' + (isSel ? 'fa-circle-check' : 'fa-gift')} />
                  </div>
                  <div>
                    <div className="reward-name">{c.offerName}</div>
                    <div className="reward-desc">
                      {c.providerName} · clicked {when(c.clickedAt)}
                    </div>
                  </div>
                </div>
              </button>
            );
          })}
        </div>
      )}

      <div className="detail-section-label">Describe the issue</div>
      <textarea
        className="search-input"
        style={{ width: '100%', minHeight: 120, padding: 14, resize: 'vertical' }}
        placeholder={selected ? `What went wrong with ${selected.offerName}?` : 'How can we help?'}
        value={message}
        onChange={(e) => setMessage(e.target.value)}
      />
      <button className="detail-go-btn" style={{ marginTop: 14 }} disabled={busy} onClick={submit}>
        {busy ? 'Submitting…' : 'Submit ticket'}
      </button>
    </div>
  );
}

function MyTickets({ reloadKey, onOpen }: { reloadKey: number; onOpen: (id: number) => void }) {
  const { data, loading, error } = useApiData<{ tickets: TicketRow[] }>(
    `/support/tickets?_=${reloadKey}`
  );
  const rows = data?.tickets ?? [];

  return (
    <div className="section-block">
      <div className="section-title">
        <i className="fa-solid fa-inbox" />
        My Tickets
      </div>
      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorState message={error} />
      ) : rows.length === 0 ? (
        <EmptyState icon="fa-inbox" message="You haven't opened any tickets yet." />
      ) : (
        <div className="rewards-list">
          {rows.map((t) => (
            <button key={t.id} className="reward-item" style={{ textAlign: 'left', cursor: 'pointer', width: '100%' }} onClick={() => onOpen(t.id)}>
              <div className="reward-left">
                <div className="reward-icon" style={{ background: 'var(--blue-glow)', color: 'var(--blue)' }}>
                  <i className="fa-solid fa-ticket" />
                </div>
                <div>
                  <div className="reward-name">{t.subject}</div>
                  <div className="reward-desc">Updated {when(t.last_message_at || t.created_at)}</div>
                </div>
              </div>
              <div className="reward-right">
                <div className={'reward-status ' + (t.status === 'closed' ? 'locked' : 'available')} style={{ textTransform: 'capitalize' }}>
                  {t.status}
                </div>
              </div>
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

function TicketModal({ ticketId, onClose, onChanged }: { ticketId: number; onClose: () => void; onChanged: () => void }) {
  const { data, loading, error, refetch } = useApiData<{ ticket: Ticket }>(`/support/tickets/${ticketId}`);
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

  return (
    <Modal onClose={onClose} variant="detail">
      <div className="detail-body">
        {loading ? (
          <Loading />
        ) : error || !ticket ? (
          <ErrorState message={error || 'Ticket not found.'} />
        ) : (
          <>
            <div className="detail-name" style={{ marginBottom: 4 }}>{ticket.subject}</div>
            <div className={'reward-status ' + (ticket.status === 'closed' ? 'locked' : 'available')} style={{ textTransform: 'capitalize', marginBottom: 16 }}>
              {ticket.status}
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10, maxHeight: 320, overflowY: 'auto', marginBottom: 14 }}>
              {ticket.messages.map((m) => {
                const mine = m.authorType === 'user';
                return (
                  <div
                    key={m.id}
                    style={{
                      alignSelf: mine ? 'flex-end' : 'flex-start',
                      maxWidth: '85%',
                      background: mine ? 'var(--accent-glow)' : 'var(--bg-elevated)',
                      border: '1px solid var(--border)',
                      borderRadius: 'var(--radius-sm)',
                      padding: '10px 12px',
                    }}
                  >
                    <div style={{ fontSize: 11, color: 'var(--text-muted)', marginBottom: 4 }}>
                      {mine ? 'You' : 'Support'} · {when(m.createdAt)}
                    </div>
                    <div style={{ fontSize: 14, whiteSpace: 'pre-wrap' }}>{m.body}</div>
                  </div>
                );
              })}
            </div>
            {ticket.status !== 'closed' && (
              <>
                <textarea
                  className="search-input"
                  style={{ width: '100%', minHeight: 80, padding: 12, resize: 'vertical' }}
                  placeholder="Write a reply…"
                  value={reply}
                  onChange={(e) => setReply(e.target.value)}
                />
                <button className="detail-go-btn" style={{ marginTop: 12 }} disabled={busy || !reply.trim()} onClick={send}>
                  {busy ? 'Sending…' : 'Send reply'}
                </button>
              </>
            )}
          </>
        )}
      </div>
    </Modal>
  );
}

export function SupportPage() {
  const [reloadKey, setReloadKey] = useState(0);
  const [openId, setOpenId] = useState<number | null>(null);
  const bump = () => setReloadKey((k) => k + 1);

  return (
    <div className="page-enter">
      <div className="section-title" style={{ fontSize: 22 }}>
        <i className="fa-solid fa-life-ring" />
        Support &amp; Help
      </div>
      <NewRequest onCreated={bump} />
      <MyTickets reloadKey={reloadKey} onOpen={setOpenId} />
      {openId !== null && <TicketModal ticketId={openId} onClose={() => setOpenId(null)} onChanged={bump} />}
    </div>
  );
}
