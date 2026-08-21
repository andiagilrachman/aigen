/**
 * Klien HTTP tunggal untuk seluruh aplikasi.
 *
 * Backend selalu membalas dengan amplop yang sama:
 *   sukses -> { success: true,  data, meta? }
 *   gagal  -> { success: false, error: { message, code, fields? } }
 *
 * Klien ini yang membuka amplop tersebut, sehingga komponen cukup menerima
 * `data` dan tidak perlu mengulang pengecekan `success` di mana-mana.
 */

/** Path relatif: dev server Vite yang meneruskan ke backend PHP. */
const API_BASE = '/api';

/** Ringkasan penagihan yang disertakan backend pada setiap aksi berbayar. */
export interface ApiMeta {
  charge_type?: 'free' | 'quota' | 'credit';
  credits_charged?: number;
  credit_balance?: number;
  quota_remaining?: number | null;
  total?: number;
  page?: number;
  limit?: number;
  total_pages?: number;
  [key: string]: unknown;
}

export interface ApiResult<T> {
  data: T;
  meta: ApiMeta;
}

/**
 * Error yang membawa serta kode dan status dari backend, supaya pemanggil bisa
 * membedakan "kredit habis" (402) dari "belum login" (401) tanpa mencocokkan
 * teks pesan — teks bisa berubah sewaktu-waktu, kode tidak.
 */
export class ApiError extends Error {
  readonly status: number;
  readonly code: string;
  readonly fields: Record<string, string>;

  constructor(
    message: string,
    status: number,
    code: string,
    fields: Record<string, string> = {},
  ) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.code = code;
    this.fields = fields;
  }

  get isUnauthenticated(): boolean {
    return this.status === 401;
  }

  get isInsufficientCredit(): boolean {
    return this.status === 402 || this.code === 'insufficient_credit';
  }
}

type Query = Record<string, string | number | boolean | null | undefined>;

function buildUrl(path: string, query?: Query): string {
  const url = `${API_BASE}${path}`;
  if (!query) return url;

  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(query)) {
    if (value === null || value === undefined || value === '') continue;
    params.append(key, String(value));
  }

  const qs = params.toString();
  return qs ? `${url}?${qs}` : url;
}

async function request<T>(
  path: string,
  options: RequestInit & { query?: Query } = {},
): Promise<ApiResult<T>> {
  const { query, ...init } = options;

  let response: Response;
  try {
    response = await fetch(buildUrl(path, query), {
      // Sesi memakai cookie HttpOnly, bukan token di localStorage yang bisa
      // dibaca skrip pihak ketiga.
      credentials: 'include',
      ...init,
      headers: {
        Accept: 'application/json',
        ...(init.body ? { 'Content-Type': 'application/json' } : {}),
        ...(init.headers ?? {}),
      },
    });
  } catch {
    throw new ApiError(
      'Tidak dapat terhubung ke server. Periksa koneksi Anda.',
      0,
      'network_error',
    );
  }

  const text = await response.text();
  let payload: unknown = null;
  if (text) {
    try {
      payload = JSON.parse(text);
    } catch {
      // Respons bukan JSON — biasanya halaman error PHP atau HTML dari proxy.
      throw new ApiError(
        `Server membalas dengan format tak terduga (HTTP ${response.status}).`,
        response.status,
        'invalid_response',
      );
    }
  }

  const body = (payload ?? {}) as {
    success?: boolean;
    data?: T;
    meta?: ApiMeta;
    error?: { message?: string; code?: string; fields?: Record<string, string> };
  };

  if (!response.ok || body.success === false) {
    throw new ApiError(
      body.error?.message ?? `Terjadi kesalahan (HTTP ${response.status}).`,
      response.status,
      body.error?.code ?? 'error',
      body.error?.fields ?? {},
    );
  }

  return { data: body.data as T, meta: body.meta ?? {} };
}

export const api = {
  get: <T>(path: string, query?: Query) => request<T>(path, { method: 'GET', query }),
  post: <T>(path: string, body?: unknown) =>
    request<T>(path, {
      method: 'POST',
      body: body === undefined ? undefined : JSON.stringify(body),
    }),
  delete: <T>(path: string) => request<T>(path, { method: 'DELETE' }),
};
