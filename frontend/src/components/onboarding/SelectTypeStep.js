import React, { useEffect, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { fetchUserTypes, selectUserType, completeOnboardingStep, fetchOnboardingStatus } from '../../store/slices/onboardingSlice';

function SelectTypeStep({ step }) {
  const dispatch = useDispatch();
  const { userTypes, loading, userType, subcategory } = useSelector((state) => state.onboarding);

  const [selectedType, setSelectedType] = useState(null);
  const [selectedSubcategory, setSelectedSubcategory] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    dispatch(fetchUserTypes());
  }, [dispatch]);

  // Pre-select already chosen user type and subcategory
  useEffect(() => {
    if (userType && selectedType === null) {
      setSelectedType(userType.id);
    }
    if (subcategory && selectedSubcategory === null) {
      setSelectedSubcategory(subcategory.id);
    }
  }, [userType, subcategory, selectedType, selectedSubcategory]);

  const currentType = userTypes.find((t) => t.id === selectedType);

  const handleContinue = async () => {
    if (!selectedType) {
      setError('Please select an organization type to continue.');
      return;
    }

    if (currentType?.has_subcategories && currentType.subcategories.length > 0 && !selectedSubcategory) {
      setError('Please select a subcategory to continue.');
      return;
    }

    setError(null);

    const typeResult = await dispatch(selectUserType({
      userTypeId: selectedType,
      subcategoryId: selectedSubcategory,
    }));

    if (!typeResult.error) {
      await dispatch(completeOnboardingStep(step.id));
      dispatch(fetchOnboardingStatus());
    }
  };

  if (loading && userTypes.length === 0) {
    return (
      <div className="spinner-corporate">
        <div className="spinner-border" role="status" />
        <p>Loading organization types...</p>
      </div>
    );
  }

  return (
    <div className="ob-card">
      <div className="ob-card-header">
        <h5>Select Your Organization Type</h5>
      </div>
      <div className="ob-card-body">
        {error && (
          <div className="alert-corporate danger" style={{ marginBottom: 16 }}>{error}</div>
        )}

        <p className="section-label">Organization Type</p>
        <div style={{ display: 'grid', gap: 10, marginBottom: 24 }}>
          {userTypes.map((type) => (
            <div
              key={type.id}
              className={`type-card ${selectedType === type.id ? 'selected' : ''}`}
              onClick={() => { setSelectedType(type.id); setSelectedSubcategory(null); }}
            >
              <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                <div className="type-card-check">
                  {selectedType === type.id && '\u2713'}
                </div>
                <div>
                  <div className="type-card-title">{type.name}</div>
                  {type.description && <p className="type-card-desc">{type.description}</p>}
                </div>
              </div>
            </div>
          ))}
        </div>

        {currentType?.has_subcategories && currentType.subcategories.length > 0 && (
          <>
            <p className="section-label">Subcategory</p>
            <div style={{ display: 'grid', gap: 10, marginBottom: 24 }}>
              {currentType.subcategories.map((sub) => (
                <div
                  key={sub.id}
                  className={`type-card ${selectedSubcategory === sub.id ? 'selected' : ''}`}
                  onClick={() => setSelectedSubcategory(sub.id)}
                >
                  <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                    <div className="type-card-check">
                      {selectedSubcategory === sub.id && '\u2713'}
                    </div>
                    <div className="type-card-title">{sub.name}</div>
                  </div>
                </div>
              ))}
            </div>
          </>
        )}
      </div>
      <div className="ob-card-footer">
        <div />
        <button
          className="btn-primary-custom"
          onClick={handleContinue}
          disabled={!selectedType}
        >
          Continue &#8594;
        </button>
      </div>
    </div>
  );
}

export default SelectTypeStep;
