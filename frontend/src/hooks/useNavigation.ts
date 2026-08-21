import { useEffect, useState } from 'react';
import { navigationApi } from '../api/endpoints';
import type { NavMenu } from '../api/types';

/**
 * Muat menu sidebar dari database sekali per sesi aplikasi.
 * Hasilnya di-cache di modul agar berpindah halaman tidak memanggil ulang.
 */
let cache: NavMenu[] | null = null;

export function useNavigation() {
  const [menus, setMenus] = useState<NavMenu[]>(cache ?? []);
  const [loading, setLoading] = useState(cache === null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (cache !== null) return;

    let alive = true;
    navigationApi
      .list()
      .then(({ data }) => {
        cache = data.menus;
        if (alive) setMenus(data.menus);
      })
      .catch((e: Error) => alive && setError(e.message))
      .finally(() => alive && setLoading(false));

    return () => {
      alive = false;
    };
  }, []);

  return { menus, loading, error };
}

/** Dipanggil saat logout agar pengguna berikutnya memuat menu yang segar. */
export function clearNavigationCache(): void {
  cache = null;
}
