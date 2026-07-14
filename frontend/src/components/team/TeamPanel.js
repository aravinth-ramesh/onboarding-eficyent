import React, { useCallback, useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { getTeam, inviteTeamMember, removeTeamMember } from '../../api/team';

/**
 * Team management for the application: the owner invites colleagues by
 * email; invitees work on the same application after OTP login.
 */
function TeamPanel({ onClose }) {
  const [team, setTeam] = useState(null);
  const [email, setEmail] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const [invited, setInvited] = useState(false);

  const load = useCallback(async () => {
    try {
      const response = await getTeam();
      setTeam(response.data.data);
    } catch {
      setError('Could not load your team.');
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const handleInvite = async (e) => {
    e.preventDefault();
    if (!email.trim() || busy) return;

    setBusy(true);
    setError(null);
    setInvited(false);
    try {
      await inviteTeamMember(email.trim());
      setEmail('');
      setInvited(true);
      await load();
    } catch (err) {
      setError(err.response?.data?.message || 'Invitation failed. Please try again.');
    } finally {
      setBusy(false);
    }
  };

  const handleRemove = async (member) => {
    if (!window.confirm(`Remove ${member.name || member.email} from the team?`)) return;
    try {
      await removeTeamMember(member.id);
      await load();
    } catch {
      setError('Could not remove this member.');
    }
  };

  return createPortal(
    <div className="modal-overlay" onClick={onClose}>
      <div className="notification-detail-dialog" onClick={(e) => e.stopPropagation()}>
        <div className="modal-header">
          <h5>Your Team</h5>
          <button className="modal-close" onClick={onClose}>{'✕'}</button>
        </div>

        <div className="team-body">
          {!team ? (
            <div className="spinner-corporate" style={{ padding: '2rem' }}>
              <div className="spinner-border" role="status" />
            </div>
          ) : (
            <>
              <div className="team-row">
                <div className="team-avatar owner">{(team.owner?.name || team.owner?.email || '?').charAt(0).toUpperCase()}</div>
                <div className="team-info">
                  <div className="team-name">{team.owner?.name || team.owner?.email}</div>
                  <div className="team-sub">{team.owner?.email} · Owner</div>
                </div>
              </div>

              {team.members.map((member) => (
                <div className="team-row" key={member.id}>
                  <div className="team-avatar">{(member.name || member.email).charAt(0).toUpperCase()}</div>
                  <div className="team-info">
                    <div className="team-name">{member.name || member.email}</div>
                    <div className="team-sub">
                      {member.email} · {member.joined ? 'Member' : 'Invited — not signed in yet'}
                    </div>
                  </div>
                  {team.is_owner && (
                    <button className="team-remove" title="Remove from team" onClick={() => handleRemove(member)}>
                      {'✕'}
                    </button>
                  )}
                </div>
              ))}

              {team.members.length === 0 && (
                <div className="team-empty">
                  Working on this with colleagues? Invite them — they'll see and
                  edit the same application.
                </div>
              )}

              {invited && (
                <div className="alert-corporate success" style={{ margin: '8px 0' }}>
                  Invitation sent — they can sign in with their email right away.
                </div>
              )}
              {error && (
                <div className="alert-corporate danger" style={{ margin: '8px 0' }}>{error}</div>
              )}

              {team.is_owner && (
                <form className="team-invite" onSubmit={handleInvite}>
                  <input
                    type="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    placeholder="colleague@company.com"
                    required
                  />
                  <button type="submit" className="btn-primary-custom" disabled={busy || !email.trim()}>
                    {busy ? 'Inviting…' : 'Invite'}
                  </button>
                </form>
              )}
            </>
          )}
        </div>
      </div>
    </div>,
    document.body
  );
}

export default TeamPanel;
