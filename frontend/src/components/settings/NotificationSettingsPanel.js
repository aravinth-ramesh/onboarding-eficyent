import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import client from '../../api/client';

/**
 * Per-category email notification toggles. Login codes and team invitations
 * always send and are not listed.
 */
function NotificationSettingsPanel({ onClose }) {
  const [prefs, setPrefs] = useState(null);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    client.get('/onboarding/notification-preferences')
      .then((response) => setPrefs(response.data.data))
      .catch(() => setError('Could not load your settings.'));
  }, []);

  const toggle = (key) => {
    setSaved(false);
    setPrefs((current) =>
      current.map((p) => (p.key === key ? { ...p, enabled: !p.enabled } : p))
    );
  };

  const handleSave = async () => {
    setSaving(true);
    setError(null);
    try {
      await client.put('/onboarding/notification-preferences', {
        preferences: prefs.map(({ key, enabled }) => ({ key, enabled })),
      });
      setSaved(true);
    } catch {
      setError('Saving failed. Please try again.');
    } finally {
      setSaving(false);
    }
  };

  return createPortal(
    <div className="modal-overlay" onClick={onClose}>
      <div className="notification-detail-dialog" onClick={(e) => e.stopPropagation()}>
        <div className="modal-header">
          <h5>Email Notifications</h5>
          <button className="modal-close" onClick={onClose}>{'✕'}</button>
        </div>

        <div className="settings-body">
          {!prefs ? (
            <div className="spinner-corporate" style={{ padding: '2rem' }}>
              <div className="spinner-border" role="status" />
            </div>
          ) : (
            <>
              <p className="settings-hint">
                Choose which emails you receive. Login codes and team
                invitations are always sent.
              </p>

              {prefs.map((pref) => (
                <label className="settings-row" key={pref.key}>
                  <input
                    type="checkbox"
                    checked={pref.enabled}
                    onChange={() => toggle(pref.key)}
                  />
                  <span>
                    <span className="settings-label">{pref.label}</span>
                    <span className="settings-desc">{pref.description}</span>
                  </span>
                </label>
              ))}

              {saved && (
                <div className="alert-corporate success" style={{ margin: '10px 0 0' }}>
                  Settings saved.
                </div>
              )}
              {error && (
                <div className="alert-corporate danger" style={{ margin: '10px 0 0' }}>{error}</div>
              )}

              <button
                className="btn-primary-custom"
                style={{ marginTop: 14, width: '100%', justifyContent: 'center' }}
                onClick={handleSave}
                disabled={saving}
              >
                {saving ? 'Saving…' : 'Save Settings'}
              </button>
            </>
          )}
        </div>
      </div>
    </div>,
    document.body
  );
}

export default NotificationSettingsPanel;
