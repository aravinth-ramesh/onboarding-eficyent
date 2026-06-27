/**
 * Shared onboarding configuration used by both the step components and the
 * sidebar companion so they stay in sync.
 */

// Documents required on the KYC step. Used by KycStep (the upload UI) and by
// the sidebar checklist that ticks them off as they are provided.
export const REQUIRED_KYC_DOCUMENTS = [
  { key: 'id_proof', label: 'Government ID Proof', hint: 'Passport, National ID, or Driver\'s License' },
  { key: 'address_proof', label: 'Address Proof', hint: 'Utility bill or bank statement (last 3 months)' },
  { key: 'incorporation_cert', label: 'Certificate of Incorporation', hint: 'Official registration document' },
];

// Rough per-step time estimates (minutes), keyed by component_key. Used to
// show an "~X min left" hint in the sidebar progress widget.
export const STEP_TIME_ESTIMATES = {
  select_type: 1,
  registration: 2,
  questions: 6,
  kyc: 4,
  review: 2,
};

export const DEFAULT_STEP_MINUTES = 3;
