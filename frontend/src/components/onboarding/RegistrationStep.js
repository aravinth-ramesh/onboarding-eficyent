import React, { useEffect, useMemo, useState } from 'react';
import { useDispatch } from 'react-redux';
import { completeOnboardingStep, fetchOnboardingStatus } from '../../store/slices/onboardingSlice';
import * as onboardingApi from '../../api/onboarding';
import HelpTip from '../common/HelpTip';

// Anchored pattern test mirroring the backend / utils/validation semantics.
const matchesPattern = (pattern, value) => {
  if (!pattern) return true;
  try {
    const anchored = pattern.startsWith('^') ? pattern : `^(?:${pattern})$`;
    return new RegExp(anchored, 'u').test(value);
  } catch {
    return true;
  }
};

const CATEGORY_LABEL = { fi: 'Financial Institution', corporate: 'Corporate' };

function RegistrationStep({ step, onBack, isFirstStep }) {
  const dispatch = useDispatch();
  const [catalog, setCatalog] = useState(null);
  const [country, setCountry] = useState('');
  const [values, setValues] = useState({});
  const [errors, setErrors] = useState({});
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState(null);
  const [submitError, setSubmitError] = useState(null);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    let active = true;
    (async () => {
      try {
        const res = await onboardingApi.getRegistrationCatalog();
        if (!active) return;
        const data = res.data.data;
        setCatalog(data);
        if (data.selected?.country_code) setCountry(data.selected.country_code);
        if (data.selected?.values) setValues(data.selected.values);
      } catch (e) {
        if (active) setLoadError(e.response?.data?.message || 'Failed to load registration details.');
      } finally {
        if (active) setLoading(false);
      }
    })();
    return () => { active = false; };
  }, []);

  // Resolve the field set for the chosen country (override or generic default).
  const fields = useMemo(() => {
    if (!catalog || !country) return [];
    return catalog.overrides?.[country] || catalog.default_fields || [];
  }, [catalog, country]);

  const handleCountryChange = (code) => {
    setCountry(code);
    setErrors({});
    setSubmitError(null);
  };

  const handleValueChange = (key, val) => {
    setValues((prev) => ({ ...prev, [key]: val }));
    setErrors((prev) => {
      if (!prev[key]) return prev;
      const next = { ...prev };
      delete next[key];
      return next;
    });
  };

  const validate = () => {
    const next = {};
    fields.forEach((f) => {
      const val = (values[f.key] || '').trim();
      if (f.required && val === '') {
        next[f.key] = `${f.label} is required.`;
        return;
      }
      if (val !== '' && f.pattern && !matchesPattern(f.pattern, val)) {
        next[f.key] = f.pattern_message || `${f.label} format is invalid.`;
      }
    });
    return next;
  };

  const handleContinue = async () => {
    setSubmitError(null);
    if (!country) {
      setSubmitError('Please select your country of incorporation.');
      return;
    }
    const found = validate();
    if (Object.keys(found).length > 0) {
      setErrors(found);
      return;
    }

    setSaving(true);
    try {
      await onboardingApi.saveRegistration(country, values);
      const result = await dispatch(completeOnboardingStep(step.id));
      if (!result.error) {
        dispatch(fetchOnboardingStatus());
      } else {
        setSubmitError('Saved, but could not advance. Please try again.');
      }
    } catch (e) {
      const apiErrors = e.response?.data?.errors;
      if (apiErrors) {
        // Map server-side "values.<key>" errors back onto fields.
        const mapped = {};
        Object.entries(apiErrors).forEach(([k, msgs]) => {
          mapped[k.replace(/^values\./, '')] = Array.isArray(msgs) ? msgs[0] : msgs;
        });
        setErrors(mapped);
      } else {
        setSubmitError(e.response?.data?.message || 'Failed to save. Please try again.');
      }
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="spinner-corporate">
        <div className="spinner-border" role="status" />
        <p>Loading registration details...</p>
      </div>
    );
  }

  const categoryLabel = CATEGORY_LABEL[catalog?.category] || 'your organization';
  const countryName = catalog?.countries?.find((c) => c.code === country)?.name;

  return (
    <div className="ob-card">
      <div className="ob-card-header">
        <h5>Registration Details</h5>
      </div>
      <div className="ob-card-body">
        {loadError && (
          <div className="alert-corporate danger" style={{ marginBottom: 16 }}>{loadError}</div>
        )}
        {submitError && (
          <div className="alert-corporate danger" style={{ marginBottom: 16 }}>{submitError}</div>
        )}

        <div className="alert-corporate info" style={{ marginBottom: 20 }}>
          Select your country of incorporation. We'll ask only for the registration
          identifiers that apply to {categoryLabel} entities there.
        </div>

        <div className="question-field">
          <label className="question-label">
            Country of Incorporation<span className="required">*</span>
            <HelpTip content="The country where your company is legally registered — this determines which registration numbers we require." label="About country of incorporation" />
          </label>
          <select
            className="form-select"
            value={country}
            onChange={(e) => handleCountryChange(e.target.value)}
          >
            <option value="">-- Select country --</option>
            {(catalog?.countries || []).map((c) => (
              <option key={c.code} value={c.code}>{c.name}</option>
            ))}
          </select>
        </div>

        {country && fields.length > 0 && (
          <>
            <p className="section-label" style={{ marginTop: 8 }}>
              Registration identifiers{countryName ? ` — ${countryName}` : ''}
            </p>
            {fields.map((f) => (
              <div key={f.key} className="question-field">
                <label className="question-label">
                  {f.label}
                  {f.required && <span className="required">*</span>}
                  <HelpTip content={f.help} label={`About ${f.label}`} />
                </label>
                <input
                  type="text"
                  className={`form-control${errors[f.key] ? ' is-invalid' : ''}`}
                  placeholder={f.placeholder || ''}
                  value={values[f.key] || ''}
                  onChange={(e) => handleValueChange(f.key, e.target.value)}
                />
                {errors[f.key] && <div className="question-error">{errors[f.key]}</div>}
              </div>
            ))}
          </>
        )}

        {country && fields.length === 0 && (
          <div className="alert-corporate info">
            No specific registration identifiers are configured for this country.
            You can continue.
          </div>
        )}
      </div>
      <div className="ob-card-footer">
        {!isFirstStep ? (
          <button className="btn-secondary-custom" onClick={onBack} disabled={saving}>
            &#8592; Back
          </button>
        ) : <div />}
        <button className="btn-primary-custom" onClick={handleContinue} disabled={saving || !country}>
          {saving ? 'Saving...' : 'Save & Continue →'}
        </button>
      </div>
    </div>
  );
}

export default RegistrationStep;
