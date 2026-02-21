import React, { useEffect } from 'react';
import { Spinner, Alert } from 'react-bootstrap';
import { useDispatch, useSelector } from 'react-redux';
import { fetchOnboardingStatus } from '../store/slices/onboardingSlice';
import AppLayout from '../components/layout/AppLayout';
import StepIndicator from '../components/common/StepIndicator';
import StepRenderer from '../components/onboarding/StepRenderer';

function HomePage() {
  const dispatch = useDispatch();
  const { steps, currentStep, status, loading, error } = useSelector(
    (state) => state.onboarding
  );

  useEffect(() => {
    dispatch(fetchOnboardingStatus());
  }, [dispatch]);

  if (loading && steps.length === 0) {
    return (
      <AppLayout>
        <div className="text-center py-5">
          <Spinner animation="border" />
          <p className="mt-2">Loading your onboarding...</p>
        </div>
      </AppLayout>
    );
  }

  if (status === 'completed') {
    return (
      <AppLayout>
        <div className="text-center py-5">
          <h2 className="text-success">Onboarding Complete</h2>
          <p className="text-muted mt-2">
            Your application has been submitted and is under review.
          </p>
        </div>
      </AppLayout>
    );
  }

  return (
    <AppLayout>
      {error && <Alert variant="danger">{error}</Alert>}

      {steps.length > 0 && (
        <StepIndicator steps={steps} currentStepId={currentStep?.id} />
      )}

      {currentStep ? (
        <StepRenderer step={currentStep} />
      ) : (
        <Alert variant="info">No active onboarding step found.</Alert>
      )}
    </AppLayout>
  );
}

export default HomePage;
