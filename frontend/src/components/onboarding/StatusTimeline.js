import React, { useEffect, useState } from 'react';
import { getTimeline } from '../../api/onboarding';

const EVENT_STYLE = {
  submitted: { icon: '↑', tone: 'info' },
  resubmitted: { icon: '↻', tone: 'info' },
  under_review: { icon: '…', tone: 'progress' },
  approved: { icon: '✓', tone: 'ok' },
  rejected: { icon: '✕', tone: 'bad' },
  reopened: { icon: '✎', tone: 'muted' },
};

/**
 * Client-facing history of the application's review journey,
 * shown on the post-submission screens.
 */
function StatusTimeline() {
  const [events, setEvents] = useState(null);

  useEffect(() => {
    getTimeline()
      .then((response) => setEvents(response.data.data))
      .catch(() => setEvents([]));
  }, []);

  if (!events || events.length === 0) return null;

  return (
    <div className="status-timeline">
      <div className="status-timeline-title">Application progress</div>
      {events.map((event, index) => {
        const style = EVENT_STYLE[event.event] || { icon: '•', tone: 'muted' };
        return (
          <div key={index} className={`timeline-row ${event.current ? 'current' : ''}`}>
            <div className="timeline-rail">
              <div className={`timeline-dot ${style.tone}`}>{style.icon}</div>
              {index < events.length - 1 && <div className="timeline-line" />}
            </div>
            <div className="timeline-content">
              <div className="timeline-label">
                {event.label}
                {event.current && <span className="timeline-now">current</span>}
              </div>
              {event.date && (
                <div className="timeline-date">
                  {new Date(event.date).toLocaleString([], {
                    year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
                  })}
                </div>
              )}
              {event.comment && <div className="timeline-comment">"{event.comment}"</div>}
              {event.estimate && <div className="timeline-estimate">{event.estimate}</div>}
            </div>
          </div>
        );
      })}
    </div>
  );
}

export default StatusTimeline;
