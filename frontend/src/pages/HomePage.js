import React, { useEffect, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { fetchOnboardingStatus, goToPreviousStep, reopenOnboarding } from '../store/slices/onboardingSlice';
import AppLayout from '../components/layout/AppLayout';
import StepIndicator from '../components/common/StepIndicator';
import StepRenderer from '../components/onboarding/StepRenderer';
import SubmittedAnswersView from '../components/onboarding/SubmittedAnswersView';
import ProfileSetup from '../components/onboarding/ProfileSetup';
import appConfig from '../appConfig';

function HomePage() {
  const dispatch = useDispatch();
  const { steps, currentStep, status, loading, error, decisionComment } = useSelector(
    (state) => state.onboarding
  );
  const user = useSelector((state) => state.auth.user);
  const profileCompleted = !!(user && user.profile_completed);
  const [viewingAnswers, setViewingAnswers] = useState(false);

  useEffect(() => {
    // Only start/fetch onboarding once the user's name and position are on
    // file. Until then we show the one-time profile setup screen.
    if (profileCompleted) {
      dispatch(fetchOnboardingStatus());
    }
  }, [dispatch, profileCompleted]);

  const handleBack = () => {
    if (currentStep) {
      dispatch(goToPreviousStep(currentStep.id)).then(() => {
        dispatch(fetchOnboardingStatus());
      });
    }
  };

  const isFirstStep = currentStep && steps.length > 0 && steps[0].id === currentStep.id;

  // Gate: collect the user's name and position once, before any onboarding step.
  if (!profileCompleted) {
    return (
      <AppLayout pageTitle="Client Onboarding">
        <ProfileSetup />
      </AppLayout>
    );
  }

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

  if (status === 'completed' || status === 'approved' || status === 'rejected') {
    if (viewingAnswers) {
      return (
        <AppLayout pageTitle="Submitted Answers">
          <SubmittedAnswersView onBack={() => setViewingAnswers(false)} />
        </AppLayout>
      );
    }

    const decision = {
      completed: {
        icon: '\u2713',
        iconClass: '',
        heading: appConfig.onboardingComplete.heading,
        message: appConfig.onboardingComplete.message,
      },
      approved: {
        icon: '\u2713',
        iconClass: 'approved',
        heading: 'Your application has been approved!',
        message: 'Welcome aboard \u2014 our team has reviewed and approved your onboarding. We will be in touch with the next steps.',
      },
      rejected: {
        icon: '\u2715',
        iconClass: 'rejected',
        heading: 'Your application was not approved',
        message: 'We have completed the review of your application and unfortunately it was not approved at this time.',
      },
    }[status];

    return (
      <AppLayout pageTitle="Client Onboarding">
        <div className="ob-card">
          <div className="ob-card-body">
            <div className="completion-screen">
              <div className={`completion-icon ${decision.iconClass}`}>{decision.icon}</div>
              <h2>{decision.heading}</h2>
              <p>{decision.message}</p>
              {status === 'rejected' && decisionComment && (
                <div className="decision-comment">
                  <div className="decision-comment-label">Reviewer note</div>
                  <div>{decisionComment}</div>
                </div>
              )}
              {status === 'rejected' && (
                <p style={{ fontSize: '0.9rem' }}>
                  You can update your answers and resubmit the application, or contact us at{' '}
                  <a href="mailto:support@eficyent.com">support@eficyent.com</a>.
                </p>
              )}
              <div style={{ display: 'flex', gap: 12, justifyContent: 'center', marginTop: 16 }}>
                {status === 'rejected' && (
                  <button
                    className="btn-primary-custom"
                    onClick={() => dispatch(reopenOnboarding())}
                  >
                    Edit &amp; Resubmit Application
                  </button>
                )}
                <button
                  className={status === 'rejected' ? 'btn-secondary-custom' : 'btn-primary-custom'}
                  onClick={() => setViewingAnswers(true)}
                >
                  View Submitted Answers
                </button>
              </div>
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
