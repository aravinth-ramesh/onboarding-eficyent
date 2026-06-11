import React, { useState } from 'react';
import { useDispatch } from 'react-redux';
import { updateProfile } from '../../store/slices/authSlice';

/**
 * Collected once, before the onboarding steps are shown: the user's Name and
 * Position/Designation. These details cannot be edited later in the process.
 * On success the auth user is updated (profile_completed = true), which lets
 * HomePage move on to render the onboarding steps.
 */
function ProfileSetup() {
  const dispatch = useDispatch();
  const [name, setName] = useState('');
  const [position, setPosition] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);

  const canSubmit = name.trim() !== '' && position.trim() !== '';

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!canSubmit) return;
    setSubmitting(true);
    setError(null);
    const result = await dispatch(
      updateProfile({ name: name.trim(), position: position.trim() })
    );
    setSubmitting(false);
    if (result.error) {
      setError(result.payload || 'Failed to save your details. Please try again.');
    }
  };

  return (
    <div className="ob-card">
      <div className="ob-card-header">
        <h5>Tell us about yourself</h5>
      </div>
      <div className="ob-card-body">
        <div className="alert-corporate info" style={{ marginBottom: 20 }}>
          Please provide your details to begin. These cannot be changed once you continue.
        </div>

        {error && (
          <div className="alert-corporate danger" style={{ marginBottom: 16 }}>{error}</div>
        )}

        <form id="profile-setup-form" onSubmit={handleSubmit}>
          <div className="question-field">
            <label className="question-label">
              Name<span className="required">*</span>
            </label>
            <input
              type="text"
              className="form-control"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="Your full name"
              required
              autoFocus
            />
          </div>

          <div className="question-field">
            <label className="question-label">
              Position / Designation<span className="required">*</span>
            </label>
            <input
              type="text"
              className="form-control"
              value={position}
              onChange={(e) => setPosition(e.target.value)}
              placeholder="e.g. Managing Partner"
              required
            />
          </div>
        </form>
      </div>
      <div className="ob-card-footer">
        <div />
        <button
          type="submit"
          form="profile-setup-form"
          className="btn-primary-custom"
          disabled={submitting || !canSubmit}
        >
          {submitting ? 'Saving...' : 'Continue →'}
        </button>
      </div>
    </div>
  );
}

export default ProfileSetup;
