import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { ApiError } from '../api/client';
import { authApi } from '../api/endpoints';
import { clearNavigationCache } from '../hooks/useNavigation';
import type { User } from '../api/types';

/**
 * Status sesi pengguna.
 *
 * Sesi dipulihkan lewat panggilan /auth/me saat aplikasi dimuat, bukan dari
 * localStorage. Cookie sesi bersifat HttpOnly sehingga tidak bisa dibaca
 * skrip — konsekuensinya, satu-satunya sumber kebenaran adalah server.
 */

interface AuthContextValue {
  user: User | null;
  balance: number;
  /** Sedang memeriksa sesi yang sudah ada saat aplikasi pertama dimuat. */
  initializing: boolean;
  login: (email: string, password: string) => Promise<void>;
  register: (input: {
    full_name: string;
    email: string;
    password: string;
    password_confirmation: string;
  }) => Promise<void>;
  logout: () => Promise<void>;
  /** Perbarui saldo setelah aksi yang memotong kredit. */
  setBalance: (balance: number) => void;
  refresh: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [balance, setBalance] = useState(0);
  const [initializing, setInitializing] = useState(true);

  const refresh = useCallback(async () => {
    try {
      const { data } = await authApi.me();
      setUser(data.user);
      setBalance(data.wallet.balance);
    } catch (e) {
      // 401 di sini bukan kegagalan: artinya memang belum login.
      if (!(e instanceof ApiError && e.isUnauthenticated)) {
        console.error('Gagal memuat sesi:', e);
      }
      setUser(null);
      setBalance(0);
    }
  }, []);

  useEffect(() => {
    refresh().finally(() => setInitializing(false));
  }, [refresh]);

  const login = useCallback(async (email: string, password: string) => {
    const { data } = await authApi.login(email, password);
    setUser(data.user);
    setBalance(data.wallet.balance);
  }, []);

  const register = useCallback(
    async (input: {
      full_name: string;
      email: string;
      password: string;
      password_confirmation: string;
    }) => {
      const { data } = await authApi.register(input);
      setUser(data.user);
      setBalance(data.wallet.balance);
    },
    [],
  );

  const logout = useCallback(async () => {
    try {
      await authApi.logout();
    } finally {
      // Apa pun hasil panggilan server, sesi di sisi klien harus dibersihkan.
      // Cache menu ikut dibuang: pengguna berikutnya bisa punya hak akses
      // berbeda, dan menampilkan menu milik akun sebelumnya adalah kebocoran.
      setUser(null);
      setBalance(0);
      clearNavigationCache();
    }
  }, []);

  const value = useMemo<AuthContextValue>(
    () => ({ user, balance, initializing, login, register, logout, setBalance, refresh }),
    [user, balance, initializing, login, register, logout, refresh],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

// eslint-disable-next-line react-refresh/only-export-components
export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth harus dipakai di dalam AuthProvider');
  return ctx;
}
