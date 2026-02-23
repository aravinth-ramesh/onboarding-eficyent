import React, { useEffect } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { fetchOnboardingStatus, goToPreviousStep } from '../store/slices/onboardingSlice';
import AppLayout from '../components/layout/AppLayout';
import StepIndicator from '../components/common/StepIndicator';
import StepRenderer from '../components/onboarding/StepRenderer';
import appConfig from '../appConfig';

function HomePage() {
  const dispatch = useDispatch();
  const { steps, currentStep, status, loading, error } = useSelector(
    (state) => state.onboarding
  );

  useEffect(() => {
    dispatch(fetchOnboardingStatus());
  }, [dispatch]);

  const handleBack = () => {
    if (currentStep) {
      dispatch(goToPreviousStep(currentStep.id)).then(() => {
        dispatch(fetchOnboardingStatus());
      });
    }
  };

  const isFirstStep = currentStep && steps.length > 0 && steps[0].id === currentStep.id;

  if (loading && steps.length === 0) {
    return (
      <AppLayout pageTitle="Client Onboarding">
        <div className="spinner-corporate">
          <div className="spinner-border" role="status" />
          <p>Loading your onboarding...</p>
        </div>
      </AppLayout>
    );
  }

  if (status === 'completed') {
    return (
      <AppLayout pageTitle="Onboarding Complete">
        <div className="ob-card">
          <div className="ob-card-body">
            <div className="completion-screen">
              <div className="completion-icon">{'\u2713'}</div>
              <h2>{appConfig.onboardingComplete.heading}</h2>
              <p>{appConfig.onboardingComplete.message}</p>
            </div>
          </div>
        </div>
      </AppLayout>
    );
  }

  return (
    <AppLayout pageTitle="Client Onboarding">
      {error && (
        <div className="alert-corporate danger" style={{ marginBottom: 16 }}>{error}</div>
      )}

      {steps.length > 0 && (
        <StepIndicator steps={steps} currentStepId={currentStep?.id} />
      )}

      {currentStep ? (
        <StepRenderer step={currentStep} onBack={handleBack} isFirstStep={isFirstStep} />
      ) : (
        <div className="alert-corporate info">No active onboarding step found.</div>
      )}
    </AppLayout>
  );
}

export default HomePage;
