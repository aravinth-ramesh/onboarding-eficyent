import { useEffect } from 'react';
import appConfig from '../../appConfig';

/**
 * Injects CSS custom properties from appConfig.theme into :root
 * so that theme.css picks them up. Also sets document title and favicon.
 */
function ThemeProvider({ children }) {
  useEffect(() => {
    const root = document.documentElement;
    const t = appConfig.theme;

    root.style.setProperty('--color-primary', t.primaryColor);
    root.style.setProperty('--color-primary-dark', t.primaryDark);
    root.style.setProperty('--color-primary-light', t.primaryLight);
    root.style.setProperty('--color-accent', t.accentColor);
    root.style.setProperty('--color-accent-hover', t.accentHover);
    root.style.setProperty('--color-success', t.successColor);
    root.style.setProperty('--color-warning', t.warningColor);
    root.style.setProperty('--color-danger', t.dangerColor);
    root.style.setProperty('--color-text-primary', t.textPrimary);
    root.style.setProperty('--color-text-secondary', t.textSecondary);
    root.style.setProperty('--color-text-muted', t.textMuted);
    root.style.setProperty('--color-bg-body', t.bgBody);
    root.style.setProperty('--color-bg-card', t.bgCard);
    root.style.setProperty('--color-bg-sidebar', t.bgSidebar);
    root.style.setProperty('--color-border', t.borderColor);
    root.style.setProperty('--color-border-light', t.borderLight);
    root.style.setProperty('--font-family', appConfig.fontFamily);
    root.style.setProperty('--font-family-heading', appConfig.fontFamilyHeading);

    // Set document title
    document.title = `${appConfig.siteName} - ${appConfig.siteTagline}`;

    // Set favicon
    const link = document.querySelector("link[rel~='icon']");
    if (link && appConfig.faviconUrl) {
      link.href = appConfig.faviconUrl;
    }
  }, []);

  return children;
}

export default ThemeProvider;
