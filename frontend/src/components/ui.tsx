import type { ButtonHTMLAttributes, InputHTMLAttributes, ReactNode } from 'react';
import { Loader2 } from 'lucide-react';
import { cn } from '../lib/cn';

/**
 * Kumpulan komponen dasar.
 *
 * Semua warna di sini memakai token Tailwind yang terhubung ke CSS variable
 * tema (primary, surface, ink, ...). Tidak ada satu pun kode heksadesimal,
 * sehingga mengganti preset di database langsung mengubah seluruh tampilan.
 */

export function Card({
  children,
  className,
}: {
  children: ReactNode;
  className?: string;
}) {
  return (
    <div
      data-themed
      className={cn(
        'rounded-card border border-border-soft bg-surface p-5 shadow-sm',
        className,
      )}
    >
      {children}
    </div>
  );
}

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: 'primary' | 'ghost' | 'outline';
  loading?: boolean;
};

export function Button({
  variant = 'primary',
  loading = false,
  className,
  children,
  disabled,
  ...props
}: ButtonProps) {
  const styles = {
    primary:
      'bg-primary text-canvas hover:opacity-90 disabled:opacity-50 font-semibold',
    outline:
      'border border-border-soft text-ink hover:border-primary hover:text-primary',
    ghost: 'text-ink-muted hover:bg-primary/10 hover:text-primary',
  }[variant];

  return (
    <button
      {...props}
      disabled={disabled || loading}
      className={cn(
        'inline-flex items-center justify-center gap-2 rounded-card px-4 py-2.5 text-sm',
        'transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/60',
        'disabled:cursor-not-allowed disabled:opacity-60',
        styles,
        className,
      )}
    >
      {loading && <Loader2 size={16} className="animate-spin" />}
      {children}
    </button>
  );
}

type FieldProps = InputHTMLAttributes<HTMLInputElement> & {
  label: string;
  error?: string;
};

export function Field({ label, error, className, id, ...props }: FieldProps) {
  const inputId = id ?? props.name ?? label;

  return (
    <div className="space-y-1.5">
      <label htmlFor={inputId} className="block text-sm font-medium text-ink">
        {label}
      </label>
      <input
        {...props}
        id={inputId}
        aria-invalid={Boolean(error)}
        className={cn(
          'w-full rounded-card border bg-canvas px-3.5 py-2.5 text-sm text-ink',
          'placeholder:text-ink-muted/60 focus:outline-none focus:ring-2',
          error
            ? 'border-red-500/60 focus:ring-red-500/40'
            : 'border-border-soft focus:border-primary focus:ring-primary/30',
          className,
        )}
      />
      {error && <p className="text-xs text-red-400">{error}</p>}
    </div>
  );
}

export function Badge({
  children,
  tone = 'neutral',
}: {
  children: ReactNode;
  tone?: 'neutral' | 'primary' | 'accent' | 'good' | 'bad';
}) {
  const tones = {
    neutral: 'bg-ink-muted/15 text-ink-muted',
    primary: 'bg-primary/15 text-primary',
    accent: 'bg-accent/15 text-accent',
    good: 'bg-emerald-500/15 text-emerald-400',
    bad: 'bg-red-500/15 text-red-400',
  }[tone];

  return (
    <span
      className={cn(
        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
        tones,
      )}
    >
      {children}
    </span>
  );
}

export function Spinner({ label }: { label?: string }) {
  return (
    <div className="flex items-center justify-center gap-3 py-12 text-ink-muted">
      <Loader2 size={20} className="animate-spin" />
      {label && <span className="text-sm">{label}</span>}
    </div>
  );
}

export function ErrorNotice({
  message,
  onRetry,
}: {
  message: string;
  onRetry?: () => void;
}) {
  return (
    <div className="rounded-card border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-300">
      <p>{message}</p>
      {onRetry && (
        <button
          type="button"
          onClick={onRetry}
          className="mt-2 font-medium text-red-200 underline underline-offset-2"
        >
          Coba lagi
        </button>
      )}
    </div>
  );
}

export function EmptyState({
  title,
  description,
  icon,
}: {
  title: string;
  description?: string;
  icon?: ReactNode;
}) {
  return (
    <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
      {icon && <div className="text-ink-muted/60">{icon}</div>}
      <p className="font-medium text-ink">{title}</p>
      {description && <p className="max-w-sm text-sm text-ink-muted">{description}</p>}
    </div>
  );
}
