import { useState } from 'react';
import { Outlet } from 'react-router-dom';
import { Sidebar } from './Sidebar';
import { ComingSoonDialog } from './ComingSoonDialog';
import { useConfig } from '../context/ConfigContext';
import type { NavMenu } from '../api/types';

/** Kerangka halaman untuk seluruh area yang memerlukan login. */
export function AppLayout() {
  const [comingSoon, setComingSoon] = useState<NavMenu | null>(null);
  const { config } = useConfig();

  return (
    <div data-themed className="flex h-screen overflow-hidden bg-canvas text-ink">
      <Sidebar onComingSoon={setComingSoon} />

      <div className="flex min-w-0 flex-1 flex-col">
        <main className="flex-1 overflow-y-auto">
          <div className="mx-auto w-full max-w-7xl px-6 py-6">
            <Outlet />
          </div>
        </main>

        {config?.legal.disclaimer && (
          <footer className="border-t border-border-soft px-6 py-3">
            <p className="mx-auto max-w-7xl text-[11px] leading-relaxed text-ink-muted">
              {config.legal.disclaimer}
            </p>
          </footer>
        )}
      </div>

      <ComingSoonDialog menu={comingSoon} onClose={() => setComingSoon(null)} />
    </div>
  );
}
