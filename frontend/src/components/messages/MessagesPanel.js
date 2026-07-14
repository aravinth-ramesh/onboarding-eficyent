import React, { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { getMessages, sendMessage, markMessagesRead } from '../../api/messages';

/**
 * Two-way thread between the client and the admin team. Opened from the
 * sidebar "Messages" card (or the ?messages=1 email deep link).
 */
function MessagesPanel({ onClose }) {
  const [messages, setMessages] = useState([]);
  const [loading, setLoading] = useState(true);
  const [draft, setDraft] = useState('');
  const [sending, setSending] = useState(false);
  const [error, setError] = useState(null);
  const bottomRef = useRef(null);

  const load = useCallback(async () => {
    try {
      const response = await getMessages();
      setMessages(response.data.data);
    } catch {
      setError('Could not load messages.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
    markMessagesRead().catch(() => {});
  }, [load]);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ block: 'end' });
  }, [messages]);

  const handleSend = async (e) => {
    e.preventDefault();
    const body = draft.trim();
    if (!body || sending) return;

    setSending(true);
    setError(null);
    try {
      await sendMessage(body);
      setDraft('');
      await load();
    } catch {
      setError('Message could not be sent. Please try again.');
    } finally {
      setSending(false);
    }
  };

  // Portal to <body> — same containing-block workaround as NotificationDetail.
  return createPortal(
    <div className="modal-overlay" onClick={onClose}>
      <div className="notification-detail-dialog messages-dialog" onClick={(e) => e.stopPropagation()}>
        <div className="modal-header">
          <h5>Messages</h5>
          <button className="modal-close" onClick={onClose}>{'✕'}</button>
        </div>

        <div className="messages-body">
          {loading ? (
            <div className="spinner-corporate" style={{ padding: '2rem' }}>
              <div className="spinner-border" role="status" />
            </div>
          ) : messages.length === 0 ? (
            <div className="messages-empty">
              Questions about your application? Send us a message — our team
              replies here and you'll also get an email.
            </div>
          ) : (
            messages.map((message) => (
              <div
                key={message.id}
                className={`message-row ${message.sender_type === 'client' ? 'mine' : 'theirs'}`}
              >
                <div className="message-bubble">
                  <div className="message-meta">
                    {message.sender_type === 'client' ? 'You' : (message.sender_name || 'Eficyent Team')}
                    {' · '}
                    {new Date(message.created_at).toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
                  </div>
                  {message.body}
                </div>
              </div>
            ))
          )}
          <div ref={bottomRef} />
        </div>

        {error && (
          <div className="alert-corporate danger" style={{ margin: '0 16px 8px' }}>{error}</div>
        )}

        <form className="messages-compose" onSubmit={handleSend}>
          <textarea
            value={draft}
            onChange={(e) => setDraft(e.target.value)}
            placeholder="Write a message to our team..."
            rows={2}
            required
          />
          <button type="submit" className="btn-primary-custom" disabled={sending || !draft.trim()}>
            {sending ? 'Sending…' : 'Send'}
          </button>
        </form>
      </div>
    </div>,
    document.body
  );
}

export default MessagesPanel;
