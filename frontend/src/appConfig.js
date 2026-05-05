/**
 * Application Configuration
 *
 * All branding, theming, and site-wide settings are configured here.
 * Change values below to customize the application for your organization.
 */
const appConfig = {
  // ── Branding ──────────────────────────────────────────────
  siteName: 'Eficyent',
  siteTagline: 'Client Onboarding Portal',
  logoUrl: "/favicon.png", // Set to a URL or import path, e.g. '/logo.png'
  faviconUrl: '/favicon.png',
  supportEmail: 'support@eficyent.com',
  copyrightText: '\u00a9 2026 Eficyent. All rights reserved.',

  // ── Theme Colors (Aurora — Modern SaaS) ───────────────────
  // These map to CSS custom properties in theme.css
  theme: {
    primaryColor: '#6366F1',       // Indigo (gradient start)
    primaryDark: '#4F46E5',        // Deeper indigo
    primaryLight: '#8B5CF6',       // Violet (gradient end)
    accentColor: '#6366F1',        // Solid fallback for gradients
    accentHover: '#5B5BD6',        // Accent hover
    successColor: '#10B981',
    warningColor: '#F59E0B',
    dangerColor: '#EF4444',
    textPrimary: '#0F172A',
    textSecondary: '#64748B',
    textMuted: '#94A3B8',
    bgBody: '#EEF2FF',
    bgCard: '#FFFFFF',
    bgSidebar: '#FFFFFF',
    borderColor: '#E2E8F0',
    borderLight: '#F1F5F9',
  },

  // ── Typography ────────────────────────────────────────────
  fontFamily: "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
  fontFamilyHeading: "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",

  // ── Login Page ────────────────────────────────────────────
  login: {
    heading: 'Welcome Back',
    subheading: 'Sign in to continue your onboarding',
    otpHeading: 'Verify Your Identity',
  },

  // ── Onboarding Completed ──────────────────────────────────
  onboardingComplete: {
    heading: 'Application Submitted',
    message: 'Your onboarding application has been submitted successfully. Our team will review your information and reach out to you shortly.',
  },
};

export default appConfig;
