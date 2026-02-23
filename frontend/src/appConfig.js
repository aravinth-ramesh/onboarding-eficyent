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
  logoUrl: null, // Set to a URL or import path, e.g. '/logo.png'
  faviconUrl: '/favicon.ico',
  supportEmail: 'support@eficyent.com',
  copyrightText: '\u00a9 2026 Eficyent. All rights reserved.',

  // ── Theme Colors ──────────────────────────────────────────
  // These map to CSS custom properties in theme.css
  theme: {
    primaryColor: '#1a3a5c',       // Deep navy blue
    primaryDark: '#0f2440',        // Darker navy
    primaryLight: '#2a5a8c',       // Lighter navy
    accentColor: '#2e86de',        // Bright blue accent
    accentHover: '#1b6dbf',        // Accent hover
    successColor: '#27ae60',
    warningColor: '#f39c12',
    dangerColor: '#e74c3c',
    textPrimary: '#2c3e50',
    textSecondary: '#6c757d',
    textMuted: '#95a5a6',
    bgBody: '#f0f2f5',
    bgCard: '#ffffff',
    bgSidebar: '#1a3a5c',
    borderColor: '#e1e5eb',
    borderLight: '#f0f2f5',
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
