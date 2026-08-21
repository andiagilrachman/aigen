import { useState } from 'react';
import type { FormEvent } from 'react';
import { Navigate, useLocation, useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import { ApiError } from '../api/client';
import { useAuth } from '../context/AuthContext';
import { useConfig } from '../context/ConfigContext';
import { Button, Field } from '../components/ui';

/**
 * Login dan pendaftaran dalam satu halaman.
 *
 * Pendaftaran hanya ditampilkan bila setting `allow_registration` bernilai
 * true — keputusan itu milik database, bukan kode.
 */
export function LoginPage() {
  const { user, initializing, login, register } = useAuth();
  const { config, siteName } = useConfig();
  const navigate = useNavigate();
  const location = useLocation();

  const [mode, setMode] = useState<'login' | 'register'>('login');
  const [form, setForm] = useState({
    full_name: '',
    email: '',
    password: '',
    password_confirmation: '',
  });
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [message, setMessage] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const target = (location.state as { from?: string } | null)?.from ?? '/screener';

  if (!initializing && user) return <Navigate to={target} replace />;

  const update = (key: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement>) =>
    setForm((prev) => ({ ...prev, [key]: e.target.value }));

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    setMessage(null);
    setFieldErrors({});

    try {
      if (mode === 'login') {
        await login(form.email, form.password);
      } else {
        await register(form);
      }
      navigate(target, { replace: true });
    } catch (err) {
      if (err instanceof ApiError) {
        setFieldErrors(err.fields);
        setMessage(err.message);
      } else {
        setMessage('Terjadi kesalahan tak terduga.');
      }
    } finally {
      setSubmitting(false);
    }
  };

  const isRegister = mode === 'register';

  return (
    <div
      data-themed
      className="relative grid min-h-screen place-items-center overflow-hidden bg-canvas px-4"
    >
      {/* Aksen latar mengikuti warna tema aktif. */}
      <div className="pointer-events-none absolute -left-24 -top-24 size-96 rounded-full bg-primary/20 blur-3xl" />
      <div className="pointer-events-none absolute -bottom-32 -right-24 size-96 rounded-full bg-accent/20 blur-3xl" />

      <motion.div
        initial={{ opacity: 0, y: 16 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.4, ease: 'easeOut' }}
        className="relative w-full max-w-md rounded-card border border-border-soft bg-surface p-8 shadow-2xl"
      >
        <div className="mb-7 text-center">
          <div className="mx-auto mb-4 grid size-14 place-items-center rounded-card bg-primary/15 text-lg font-bold text-primary">
            {siteName.slice(0, 2).toUpperCase()}
          </div>
          <h1 className="text-xl font-semibold text-ink">
            {isRegister ? `Daftar ke ${siteName}` : `Masuk ke ${siteName}`}
          </h1>
          <p className="mt-1 text-sm text-ink-muted">
            {isRegister
              ? 'Dapatkan masa uji coba 7 hari beserta 100 kredit.'
              : (config?.branding.site_tagline ?? 'Analisis fundamental saham IHSG')}
          </p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          {isRegister && (
            <Field
              label="Nama lengkap"
              name="full_name"
              autoComplete="name"
              required
              value={form.full_name}
              onChange={update('full_name')}
              error={fieldErrors.full_name}
              placeholder="Nama Anda"
            />
          )}

          <Field
            label="Email"
            name="email"
            type="email"
            autoComplete="email"
            required
            value={form.email}
            onChange={update('email')}
            error={fieldErrors.email}
            placeholder="nama@email.com"
          />

          <Field
            label="Kata sandi"
            name="password"
            type="password"
            autoComplete={isRegister ? 'new-password' : 'current-password'}
            required
            value={form.password}
            onChange={update('password')}
            error={fieldErrors.password}
            placeholder="••••••••"
          />

          {isRegister && (
            <Field
              label="Ulangi kata sandi"
              name="password_confirmation"
              type="password"
              autoComplete="new-password"
              required
              value={form.password_confirmation}
              onChange={update('password_confirmation')}
              error={fieldErrors.password_confirmation}
              placeholder="••••••••"
            />
          )}

          {message && (
            <p className="rounded-card border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-300">
              {message}
            </p>
          )}

          <Button type="submit" loading={submitting} className="w-full">
            {isRegister ? 'Buat akun' : 'Masuk'}
          </Button>
        </form>

        {config?.auth.allow_registration !== false && (
          <p className="mt-6 text-center text-sm text-ink-muted">
            {isRegister ? 'Sudah punya akun?' : 'Belum punya akun?'}{' '}
            <button
              type="button"
              onClick={() => {
                setMode(isRegister ? 'login' : 'register');
                setFieldErrors({});
                setMessage(null);
              }}
              className="font-medium text-primary hover:underline"
            >
              {isRegister ? 'Masuk' : 'Daftar gratis'}
            </button>
          </p>
        )}
      </motion.div>
    </div>
  );
}
