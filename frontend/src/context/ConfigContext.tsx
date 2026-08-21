import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { configApi } from '../api/endpoints';
import type { AppConfig, ThemePreset } from '../api/types';

/**
 * Branding, tema, dan feature flag — semuanya dari database.
 *
 * Ini inti prinsip NO HARDCODE di sisi frontend: nama aplikasi, palet warna,
 * dan fitur mana yang aktif tidak pernah ditulis di dalam komponen.
 */

const RADIUS_MAP: Record<string, string> = {
  sharp: '0.25rem',
  medium: '0.75rem',
  rounded: '1.25rem',
};

/** Kunci penyimpanan pilihan tema pengguna di perangkat ini. */
const THEME_STORAGE_KEY = 'aigen.theme';

interface ConfigContextValue {
  config: AppConfig | null;
  loading: boolean;
  error: string | null;
  themes: ThemePreset[];
  activeTheme: ThemePreset | null;
  setTheme: (presetKey: string) => void;
  isFeatureActive: (key: string) => boolean;
  siteName: string;
  reload: () => void;
}

const ConfigContext = createContext<ConfigContextValue | undefined>(undefined);

/** Terapkan preset ke CSS variable yang dibaca Tailwind. */
function applyTheme(preset: ThemePreset): void {
  const root = document.documentElement;

  root.style.setProperty('--aigen-primary', preset.primary_color);
  root.style.setProperty('--aigen-accent', preset.accent_color);
  root.style.setProperty('--aigen-bg', preset.background_color);
  root.style.setProperty('--aigen-card', preset.card_color);
  root.style.setProperty('--aigen-radius', RADIUS_MAP[preset.radius] ?? RADIUS_MAP.medium);

  const isLight = preset.background_mode === 'light';
  root.dataset.mode = preset.background_mode;
  root.classList.toggle('dark', !isLight);

  // Teks dan garis batas ikut mode terang/gelap agar kontras tetap terbaca
  // berapa pun warna yang dipilih admin.
  root.style.setProperty('--aigen-text', isLight ? '#12141c' : '#e8eaed');
  root.style.setProperty('--aigen-text-muted', isLight ? '#5b6478' : '#8b93a7');
  root.style.setProperty(
    '--aigen-border',
    isLight ? 'rgb(15 23 42 / 0.10)' : 'rgb(255 255 255 / 0.08)',
  );
}

export function ConfigProvider({ children }: { children: ReactNode }) {
  const [config, setConfig] = useState<AppConfig | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [themeKey, setThemeKey] = useState<string | null>(() =>
    localStorage.getItem(THEME_STORAGE_KEY),
  );

  const load = useCallback(() => {
    setLoading(true);
    setError(null);

    configApi
      .get()
      .then(({ data }) => setConfig(data))
      .catch((e: Error) => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  useEffect(load, [load]);

  const themes = useMemo(() => config?.themes ?? [], [config]);

  const activeTheme = useMemo(() => {
    if (themes.length === 0) return null;
    return (
      themes.find((t) => t.preset_key === themeKey) ??
      themes.find((t) => t.is_default) ??
      themes[0]
    );
  }, [themes, themeKey]);

  useEffect(() => {
    if (activeTheme) applyTheme(activeTheme);
  }, [activeTheme]);

  useEffect(() => {
    const name = config?.branding.site_name;
    if (name) document.title = name;
  }, [config]);

  const setTheme = useCallback((presetKey: string) => {
    setThemeKey(presetKey);
    localStorage.setItem(THEME_STORAGE_KEY, presetKey);
  }, []);

  const isFeatureActive = useCallback(
    (key: string) => config?.features?.[key] ?? false,
    [config],
  );

  const value = useMemo<ConfigContextValue>(
    () => ({
      config,
      loading,
      error,
      themes,
      activeTheme,
      setTheme,
      isFeatureActive,
      siteName: config?.branding.site_name ?? 'AIGen',
      reload: load,
    }),
    [config, loading, error, themes, activeTheme, setTheme, isFeatureActive, load],
  );

  return <ConfigContext.Provider value={value}>{children}</ConfigContext.Provider>;
}

// eslint-disable-next-line react-refresh/only-export-components
export function useConfig(): ConfigContextValue {
  const ctx = useContext(ConfigContext);
  if (!ctx) throw new Error('useConfig harus dipakai di dalam ConfigProvider');
  return ctx;
}
