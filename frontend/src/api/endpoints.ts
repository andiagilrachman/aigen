import { api } from './client';
import type {
  AppConfig,
  AuthPayload,
  CreditBalance,
  CreditTransaction,
  NavMenu,
  ScreenerOptions,
  ScreenerResult,
  StockDetail,
  StockSummary,
} from './types';

/**
 * Satu-satunya tempat path endpoint dituliskan.
 * Komponen memanggil fungsi di sini, bukan menyusun URL sendiri.
 */

export const configApi = {
  get: () => api.get<AppConfig>('/config'),
};

export const authApi = {
  login: (email: string, password: string) =>
    api.post<AuthPayload>('/auth/login', { email, password }),

  register: (input: {
    full_name: string;
    email: string;
    password: string;
    password_confirmation: string;
  }) => api.post<AuthPayload>('/auth/register', input),

  logout: () => api.post<{ message: string }>('/auth/logout'),

  me: () => api.get<AuthPayload>('/auth/me'),
};

export const navigationApi = {
  list: () => api.get<{ menus: NavMenu[] }>('/navigation'),
};

export type ScreenerParams = Record<string, string | number | boolean | undefined>;

export const screenerApi = {
  options: () => api.get<ScreenerOptions>('/screener/options'),
  run: (params: ScreenerParams) => api.post<ScreenerResult>('/screener/run', params),
};

export const stockApi = {
  search: (search: string, limit = 20) =>
    api.get<{ items: StockSummary[] }>('/stocks', { search, limit }),

  detail: (symbol: string) => api.get<StockDetail>(`/stocks/${encodeURIComponent(symbol)}`),
};

export const creditApi = {
  balance: () => api.get<CreditBalance>('/credits/balance'),
  history: (page = 1, limit = 25) =>
    api.get<{ items: CreditTransaction[] }>('/credits/history', { page, limit }),
};
