import { AnimatePresence, motion } from 'framer-motion';
import { Check, X } from 'lucide-react';
import { useConfig } from '../context/ConfigContext';
import { cn } from '../lib/cn';

/**
 * Pemilih tema. Daftarnya berasal dari tabel `theme_presets`, jadi menambah
 * preset baru cukup dilakukan lewat database tanpa menyentuh frontend.
 */
export function ThemePicker({ open, onClose }: { open: boolean; onClose: () => void }) {
  const { themes, activeTheme, setTheme } = useConfig();

  return (
    <AnimatePresence>
      {open && (
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
            aria-label="Pilih tema"
            initial={{ opacity: 0, scale: 0.96, y: 12 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.96, y: 12 }}
            transition={{ type: 'spring', stiffness: 380, damping: 30 }}
            className="fixed left-1/2 top-1/2 z-50 w-[min(560px,92vw)] -translate-x-1/2 -translate-y-1/2 rounded-card border border-border-soft bg-surface p-5 shadow-2xl"
          >
            <div className="mb-4 flex items-center justify-between">
              <div>
                <h2 className="font-semibold text-ink">Pilih tema</h2>
                <p className="text-sm text-ink-muted">
                  Preset diambil langsung dari database.
                </p>
              </div>
              <button
                type="button"
                onClick={onClose}
                aria-label="Tutup"
                className="rounded-card p-1.5 text-ink-muted transition hover:bg-ink-muted/10 hover:text-ink"
              >
                <X size={18} />
              </button>
            </div>

            <div className="grid max-h-[60vh] grid-cols-2 gap-3 overflow-y-auto sm:grid-cols-4">
              {themes.map((preset) => {
                const isActive = activeTheme?.preset_key === preset.preset_key;

                return (
                  <button
                    key={preset.preset_key}
                    type="button"
                    onClick={() => setTheme(preset.preset_key)}
                    className={cn(
                      'group relative overflow-hidden rounded-card border p-3 text-left transition',
                      isActive
                        ? 'border-primary ring-2 ring-primary/40'
                        : 'border-border-soft hover:border-primary/60',
                    )}
                  >
                    {/* Pratinjau memakai warna preset itu sendiri, bukan warna tema aktif. */}
                    <div
                      className="mb-2 flex h-12 items-end gap-1 rounded-md p-1.5"
                      style={{ backgroundColor: preset.background_color }}
                    >
                      <span
                        className="h-full w-1/2 rounded-sm"
                        style={{ backgroundColor: preset.card_color }}
                      />
                      <span
                        className="h-3 w-1/4 rounded-sm"
                        style={{ backgroundColor: preset.primary_color }}
                      />
                      <span
                        className="h-3 w-1/4 rounded-sm"
                        style={{ backgroundColor: preset.accent_color }}
                      />
                    </div>
                    <p className="truncate text-xs font-medium text-ink">{preset.name}</p>
                    <p className="text-[11px] text-ink-muted">
                      {preset.background_mode === 'dark' ? 'Gelap' : 'Terang'}
                    </p>

                    {isActive && (
                      <span className="absolute right-2 top-2 grid size-5 place-items-center rounded-full bg-primary text-canvas">
                        <Check size={12} strokeWidth={3} />
                      </span>
                    )}
                  </button>
                );
              })}
            </div>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  );
}
