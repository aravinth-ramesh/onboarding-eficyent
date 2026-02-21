import React, { useEffect, useState } from 'react';
import { Card, ListGroup, Button, Alert, Spinner } from 'react-bootstrap';
import { useDispatch, useSelector } from 'react-redux';
import { fetchUserTypes, selectUserType, completeOnboardingStep, fetchOnboardingStatus } from '../../store/slices/onboardingSlice';

function SelectTypeStep({ step }) {
  const dispatch = useDispatch();
  const { userTypes, loading } = useSelector((state) => state.onboarding);

  const [selectedType, setSelectedType] = useState(null);
  const [selectedSubcategory, setSelectedSubcategory] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    dispatch(fetchUserTypes());
  }, [dispatch]);

  const currentType = userTypes.find((t) => t.id === selectedType);

  const handleContinue = async () => {
    if (!selectedType) {
      setError('Please select a type.');
      return;
    }

    if (currentType?.has_subcategories && currentType.subcategories.length > 0 && !selectedSubcategory) {
      setError('Please select a subcategory.');
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

  return (
    <Card>
      <Card.Header>
        <h5 className="mb-0">Select Your Organization Type</h5>
      </Card.Header>
      <Card.Body>
        {error && <Alert variant="danger">{error}</Alert>}

        {loading ? (
          <div className="text-center py-4">
            <Spinner animation="border" />
          </div>
        ) : (
          <>
            <ListGroup className="mb-3">
              {userTypes.map((type) => (
                <ListGroup.Item
                  key={type.id}
                  active={selectedType === type.id}
                  action
                  onClick={() => {
                    setSelectedType(type.id);
                    setSelectedSubcategory(null);
                  }}
                >
                  <strong>{type.name}</strong>
                  {type.description && (
                    <div className="small text-muted">{type.description}</div>
                  )}
                </ListGroup.Item>
              ))}
            </ListGroup>

            {currentType?.has_subcategories && currentType.subcategories.length > 0 && (
              <>
                <h6>Select Subcategory</h6>
                <ListGroup className="mb-3">
                  {currentType.subcategories.map((sub) => (
                    <ListGroup.Item
                      key={sub.id}
                      active={selectedSubcategory === sub.id}
                      action
                      onClick={() => setSelectedSubcategory(sub.id)}
                    >
                      {sub.name}
                    </ListGroup.Item>
                  ))}
                </ListGroup>
              </>
            )}

            <Button variant="primary" onClick={handleContinue} disabled={!selectedType}>
              Continue
            </Button>
          </>
        )}
      </Card.Body>
    </Card>
  );
}

export default SelectTypeStep;
