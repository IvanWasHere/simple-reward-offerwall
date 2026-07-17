import { useEffect, useState } from 'react';

export type Theme = 'dark' | 'light';

const STORAGE_KEY = 'rv_theme';

function apply(theme: Theme): void {
  if (theme === 'light') {
    document.documentElement.setAttribute('data-theme', 'light');
  } else {
    document.documentElement.removeAttribute('data-theme');
  }
  try {
    localStorage.setItem(STORAGE_KEY, theme);
  } catch {
    /* private mode / storage disabled — non-fatal */
  }
}

function initial(): Theme {
  try {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved === 'light' || saved === 'dark') return saved;
  } catch {
    /* ignore */
  }
  if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) {
    return 'light';
  }
  return 'dark';
}

/**
 * useTheme — dark/light theme with the same persistence rules as the prototype
 * (localStorage `rv_theme`, falling back to the OS preference).
 */
export function useTheme(): { theme: Theme; toggle: () => void } {
  const [theme, setTheme] = useState<Theme>(initial);

  useEffect(() => {
    apply(theme);
  }, [theme]);

  return {
    theme,
    toggle: () => setTheme((t) => (t === 'dark' ? 'light' : 'dark')),
  };
}
