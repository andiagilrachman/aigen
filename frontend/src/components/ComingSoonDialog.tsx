import { AnimatePresence, motion } from 'framer-motion';
import { Clock, X } from 'lucide-react';
import { DynamicIcon } from './DynamicIcon';
import type { NavMenu } from '../api/types';

/**
 * Panel untuk lima engine yang belum dirilis. Judul, deskripsi, progres, dan
 * perkiraan waktu semuanya berasal dari tabel `coming_soon_items`.
 */
export function ComingSoonDialog({
  menu,
  onClose,
}: {
  menu: NavMenu | null;
  onClose: () => void;
}) {
  const info = menu?.coming_soon;

  return (
    <AnimatePresence>
      {menu && (
        <>
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={onClose}
            className="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm"
          />
          <motion.div
            role="dialog"
            aria-label={menu.label}
            initial={{ opacity: 0, scale: 0.96, y: 12 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.96, y: 12 }}
            transition={{ type: 'spring', stiffness: 380, damping: 30 }}
            className="fixed left-1/2 top-1/2 z-50 w-[min(480px,92vw)] -translate-x-1/2 -translate-y-1/2 rounded-card border border-border-soft bg-surface p-6 shadow-2xl"
          >
            <button
              type="button"
              onClick={onClose}
              aria-label="Tutup"
              className="absolute right-4 top-4 rounded-card p-1.5 text-ink-muted transition hover:bg-ink-muted/10 hover:text-ink"
            >
              <X size={18} />
            </button>

            <div className="mb-4 grid size-12 place-items-center rounded-card bg-accent/15 text-accent">
              <DynamicIcon name={menu.icon} size={24} />
            </div>

            <h2 className="text-lg font-semibold text-ink">
              {info?.title ?? menu.label}
            </h2>
            <p className="mt-1.5 text-sm leading-relaxed text-ink-muted">
              {info?.description ??
                'Engine ini sedang dikembangkan dan akan hadir pada rilis berikutnya.'}
            </p>

            {info && (
              <div className="mt-5 space-y-2">
                <div className="flex items-center justify-between text-xs">
                  <span className="text-ink-muted">Progres pengembangan</span>
                  <span className="font-medium text-accent">
                    {info.progress_percent}%
                  </span>
                </div>
                <div className="h-2 overflow-hidden rounded-full bg-ink-muted/15">
                  <motion.div
                    initial={{ width: 0 }}
                    animate={{ width: `${info.progress_percent}%` }}
                    transition={{ duration: 0.7, ease: 'easeOut' }}
                    className="h-full rounded-full bg-accent"
                  />
                </div>
                {info.eta_label && (
                  <p className="flex items-center gap-1.5 pt-1 text-xs text-ink-muted">
                    <Clock size={13} />
                    Perkiraan rilis: {info.eta_label}
                  </p>
                )}
              </div>
            )}
          </motion.div>
        </>
      )}
    </AnimatePresence>
  );
}
