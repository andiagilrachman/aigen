/**
 * Bentuk data yang dikirim backend.
 *
 * Sengaja dikumpulkan di satu berkas agar bila kolom di backend berubah,
 * TypeScript langsung menunjukkan setiap tempat yang perlu ikut disesuaikan.
 */

export interface User {
  id: number;
  full_name: string;
  email: string;
  role: 'super_admin' | 'admin' | 'support' | 'user';
  status: 'active' | 'suspended';
  photo_url: string | null;
  language: string | null;
  last_login_at: string | null;
  created_at: string;
}

export interface Wallet {
  balance: number;
}

export interface AuthPayload {
  user: User;
  wallet: Wallet;
  message?: string;
}

export interface ThemePreset {
  preset_key: string;
  name: string;
  primary_color: string;
  accent_color: string;
  background_color: string;
  card_color: string;
  background_mode: 'dark' | 'light';
  radius: 'sharp' | 'medium' | 'rounded' | string;
  is_default: boolean;
}

export interface AppConfig {
  branding: {
    site_name: string;
    site_tagline: string;
    logo_url: string;
    favicon_url: string;
    support_email: string;
  };
  legal: {
    disclaimer: string;
    terms_url: string;
    privacy_url: string;
  };
  auth: {
    allow_registration: boolean;
  };
  themes: ThemePreset[];
  features: Record<string, boolean>;
}

export interface ComingSoonInfo {
  id: number;
  title: string;
  description: string | null;
  progress_percent: number;
  eta_label: string | null;
}

export interface NavMenu {
  id: number;
  menu_key: string;
  label: string;
  icon: string | null;
  route: string | null;
  status: 'active' | 'coming_soon';
  sort_order: number;
  coming_soon: ComingSoonInfo | null;
}

export interface MetricMeta {
  key: string;
  label: string;
  unit: string;
  higher_is_better: boolean;
}

export interface Sector {
  id: number;
  name: string;
  sub_sector: string;
}

export interface ScreenerOptions {
  metrics: MetricMeta[];
  sectors: Sector[];
  default_limit: number;
  max_limit: number;
}

export interface ScreenerRow {
  id: number;
  symbol: string;
  company_name: string;
  company_name_short: string | null;
  logo_url: string | null;
  is_syariah: number;
  market_cap: number | null;
  sector_name: string | null;
  sub_sector: string | null;
  snapshot_date: string;
  fundamental_score: number | null;
  rating: string | null;
  roe: number | null;
  roa: number | null;
  der: number | null;
  per: number | null;
  pbv: number | null;
  eps: number | null;
  bvps: number | null;
  dividend_yield: number | null;
  revenue_growth_yoy: number | null;
  net_income_growth_yoy: number | null;
  net_profit_margin: number | null;
  gross_profit_margin: number | null;
  current_ratio: number | null;
  quick_ratio: number | null;
  altman_z_score: number | null;
  beneish_m_score: number | null;
  piotroski_f_score: number | null;
  graham_number: number | null;
}

export interface ScreenerResult {
  items: ScreenerRow[];
  total: number;
  page: number;
  limit: number;
  total_pages: number;
  sort: string;
  direction: 'ASC' | 'DESC';
  applied_filters: AppliedFilter[];
}

export interface AppliedFilter {
  metric: string;
  label: string;
  operator: string;
  value: number | string;
}

export interface StockSummary {
  id: number;
  symbol: string;
  company_name: string;
  logo_url: string | null;
  sector_name: string | null;
}

export interface StockDetailInfo {
  id: number;
  symbol: string;
  company_name: string;
  company_name_short: string | null;
  exchange: string | null;
  listing_date: string | null;
  website: string | null;
  logo_url: string | null;
  address: string | null;
  is_syariah: boolean;
  market_cap: number | null;
  sector_id: number | null;
  sector_name: string | null;
  sub_sector: string | null;
}

export type Snapshot = Record<string, number | string | null> & {
  snapshot_date?: string;
  rating?: string | null;
};

export interface FinancialLine {
  account_name: string;
  account_level: number;
  amount: number | null;
}

export interface Shareholder {
  holder_name: string;
  percentage: number | null;
  badge: string | null;
  snapshot_date: string;
}

export interface StockDetail {
  stock: StockDetailInfo;
  snapshot: Snapshot | null;
  metrics_meta: Record<string, { label: string; unit: string; higher_is_better: boolean }>;
  financials: {
    BS: Record<string, FinancialLine[]>;
    IS: Record<string, FinancialLine[]>;
    CF: Record<string, FinancialLine[]>;
  };
  shareholders: Shareholder[];
  in_watchlist: boolean;
}

export interface CreditTransaction {
  id: number;
  type: 'topup' | 'usage' | 'refund' | 'bonus' | 'trial';
  amount: number;
  balance_after: number;
  reference_type: string | null;
  note: string | null;
  created_at: string;
}

export interface QuotaSummary {
  tier_key: string | null;
  tier_name: string | null;
  quota: number | null;
  used: number;
  remaining: number | null;
  unlimited: boolean;
}

export interface CreditCostInfo {
  name: string;
  cost: number;
}

export interface CreditBalance {
  balance: number;
  costs: Record<string, CreditCostInfo>;
  subscription: QuotaSummary;
}
