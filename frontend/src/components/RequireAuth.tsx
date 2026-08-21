import { Navigate, useLocation } from 'react-router-dom';
import type { ReactNode } from 'react';
import { useAuth } from '../context/AuthContext';
import { Spinner } from './ui';

/**
 * Penjaga rute. Selama sesi masih diperiksa kita menahan render, supaya
 * pengguna yang sebenarnya sudah login tidak sempat terlempar ke /login.
 */
export function RequireAuth({ children }: { children: ReactNode }) {
  const { user, initializing } = useAuth();
  const location = useLocation();

  if (initializing) {
    return (
      <div className="grid h-screen place-items-center bg-canvas">
        <Spinner label="Memuat sesi…" />
      </div>
    );
  }

  if (!user) {
    // Simpan tujuan awal agar setelah login pengguna kembali ke sana.
    return <Navigate to="/login" replace state={{ from: location.pathname }} />;
  }

  return <>{children}</>;
}
