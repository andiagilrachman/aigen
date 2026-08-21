import { useState } from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import { AnimatePresence, motion } from 'framer-motion';
import { LogOut, Palette, PanelLeftClose, PanelLeftOpen, Wallet } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { useConfig } from '../context/ConfigContext';
import { useNavigation } from '../hooks/useNavigation';
import { DynamicIcon } from './DynamicIcon';
import { ThemePicker } from './ThemePicker';
import { cn } from '../lib/cn';
import type { NavMenu } from '../api/types';

/**
 * Sidebar dibangun sepenuhnya dari tabel `nav_menu`.
 *
 * Tidak ada daftar menu yang ditulis di sini. Menu berstatus `coming_soon`
 * tidak menjadi tautan, melainkan membuka panel ringkasan progres.
 */
export function Sidebar({
  onComingSoon,
}: {
  onComingSoon: (menu: NavMenu) => void;
}) {
  const { menus, loading } = useNavigation();
  const { user, balance, logout } = useAuth();
  const { siteName, config } = useConfig();
  const navigate = useNavigate();

  const [collapsed, setCollapsed] = useState(false);
  const [themeOpen, setThemeOpen] = useState(false);

  const handleLogout = async () => {
    await logout();
    navigate('/login', { replace: true });
  };

  return (
    <motion.aside
      data-themed
      animate={{ width: collapsed ? 76 : 264 }}
      transition={{ type: 'spring', stiffness: 320, damping: 32 }}
      className="relative z-20 flex h-full shrink-0 flex-col border-r border-border-soft bg-surface"
    >
      <div className="flex h-16 items-center gap-3 border-b border-border-soft px-4">
        <div className="grid size-9 shrink-0 place-items-center rounded-card bg-primary/15 text-sm font-bold text-primary">
          {siteName.slice(0, 2).toUpperCase()}
        </div>
        <AnimatePresence initial={false}>
          {!collapsed && (
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              className="min-w-0 flex-1"
            >
              <p className="truncate font-semibold text-ink">{siteName}</p>
              <p className="truncate text-xs text-ink-muted">
                {config?.branding.site_tagline}
              </p>
            </motion.div>
          )}
        </AnimatePresence>
      </div>

      <nav className="flex-1 space-y-1 overflow-y-auto p-3">
        {loading && (
          <div className="space-y-2">
            {Array.from({ length: 6 }).map((_, i) => (
              <div key={i} className="h-10 animate-pulse rounded-card bg-ink-muted/10" />
            ))}
          </div>
        )}

        {menus.map((menu) => {
          const isComingSoon = menu.status === 'coming_soon' || !menu.route;
          const content = (
            <>
              <DynamicIcon name={menu.icon} size={19} className="shrink-0" />
              {!collapsed && <span className="truncate">{menu.label}</span>}
              {!collapsed && isComingSoon && (
                <span className="ml-auto rounded-full bg-accent/15 px-2 py-0.5 text-[10px] font-medium text-accent">
                  Segera
                </span>
              )}
            </>
          );

          const base =
            'flex w-full items-center gap-3 rounded-card px-3 py-2.5 text-sm transition';

          if (isComingSoon) {
            return (
              <button
                key={menu.menu_key}
                type="button"
                title={collapsed ? menu.label : undefined}
                onClick={() => onComingSoon(menu)}
                className={cn(base, 'text-ink-muted hover:bg-accent/10 hover:text-accent')}
              >
                {content}
              </button>
            );
          }

          return (
            <NavLink
              key={menu.menu_key}
              to={menu.route as string}
              title={collapsed ? menu.label : undefined}
              className={({ isActive }) =>
                cn(
                  base,
                  isActive
                    ? 'bg-primary/15 font-medium text-primary'
                    : 'text-ink-muted hover:bg-primary/10 hover:text-ink',
                )
              }
            >
              {content}
            </NavLink>
          );
        })}
      </nav>

      <div className="space-y-1 border-t border-border-soft p-3">
        <div
          className={cn(
            'flex items-center gap-3 rounded-card bg-canvas px-3 py-2.5',
            collapsed && 'justify-center',
          )}
        >
          <Wallet size={18} className="shrink-0 text-primary" />
          {!collapsed && (
            <div className="min-w-0">
              <p className="text-xs text-ink-muted">Saldo kredit</p>
              <p className="font-semibold text-ink">{balance}</p>
            </div>
          )}
        </div>

        <button
          type="button"
          onClick={() => setThemeOpen(true)}
          title="Ganti tema"
          className="flex w-full items-center gap-3 rounded-card px-3 py-2.5 text-sm text-ink-muted transition hover:bg-primary/10 hover:text-ink"
        >
          <Palette size={18} className="shrink-0" />
          {!collapsed && <span>Tema</span>}
        </button>

        <button
          type="button"
          onClick={handleLogout}
          title="Keluar"
          className="flex w-full items-center gap-3 rounded-card px-3 py-2.5 text-sm text-ink-muted transition hover:bg-red-500/10 hover:text-red-400"
        >
          <LogOut size={18} className="shrink-0" />
          {!collapsed && <span className="truncate">Keluar</span>}
        </button>

        {!collapsed && user && (
          <p className="truncate px-3 pt-1 text-xs text-ink-muted">{user.email}</p>
        )}
      </div>

      <button
        type="button"
        onClick={() => setCollapsed((v) => !v)}
        aria-label={collapsed ? 'Lebarkan sidebar' : 'Perkecil sidebar'}
        className="absolute -right-3 top-20 grid size-6 place-items-center rounded-full border border-border-soft bg-surface text-ink-muted transition hover:text-primary"
      >
        {collapsed ? <PanelLeftOpen size={13} /> : <PanelLeftClose size={13} />}
      </button>

      <ThemePicker open={themeOpen} onClose={() => setThemeOpen(false)} />
    </motion.aside>
  );
}
