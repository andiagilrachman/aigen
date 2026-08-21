import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { AnimatePresence, motion } from 'framer-motion';
import {
  ArrowDown,
  ArrowUp,
  Coins,
  Filter,
  RotateCcw,
  Search,
  SlidersHorizontal,
} from 'lucide-react';
import { ApiError } from '../api/client';
import { screenerApi } from '../api/endpoints';
import type { ScreenerParams } from '../api/endpoints';
import type { MetricMeta, ScreenerOptions, ScreenerResult } from '../api/types';
import { useAuth } from '../context/AuthContext';
import { Badge, Button, Card, EmptyState, ErrorNotice, Spinner } from '../components/ui';
import { formatCompact, formatMetric } from '../lib/format';
import { cn } from '../lib/cn';

/**
 * Screener fundamental — layar utama fase 1.
 *
 * Kolom tabel dan form filter dibangun dari metadata `/screener/options`,
 * sehingga menambah metrik di backend langsung muncul di sini tanpa mengubah
 * komponen ini.
 */

/** Metrik yang ditampilkan sebagai kolom tabel secara bawaan. */
const DEFAULT_COLUMNS = ['fundamental_score', 'roe', 'der', 'per', 'pbv', 'dividend_yield'];

type Bounds = Record<string, { min: string; max: string }>;

export function ScreenerPage() {
  const { setBalance } = useAuth();

  const [options, setOptions] = useState<ScreenerOptions | null>(null);
  const [optionsError, setOptionsError] = useState<string | null>(null);

  const [bounds, setBounds] = useState<Bounds>({});
  const [search, setSearch] = useState('');
  const [sectorId, setSectorId] = useState('');
  const [syariah, setSyariah] = useState('');
  const [sort, setSort] = useState('fundamental_score');
  const [direction, setDirection] = useState<'ASC' | 'DESC'>('DESC');
  const [page, setPage] = useState(1);

  const [result, setResult] = useState<ScreenerResult | null>(null);
  const [running, setRunning] = useState(false);
  const [runError, setRunError] = useState<string | null>(null);
  const [charge, setCharge] = useState<string | null>(null);
  const [showAllFilters, setShowAllFilters] = useState(false);

  useEffect(() => {
    screenerApi
      .options()
      .then(({ data }) => setOptions(data))
      .catch((e: Error) => setOptionsError(e.message));
  }, []);

  const columns = useMemo<MetricMeta[]>(() => {
    if (!options) return [];
    const byKey = new Map(options.metrics.map((m) => [m.key, m]));
    return DEFAULT_COLUMNS.map((key) => byKey.get(key)).filter(
      (m): m is MetricMeta => Boolean(m),
    );
  }, [options]);

  const run = useCallback(
    async (targetPage: number) => {
      if (!options) return;

      setRunning(true);
      setRunError(null);

      const params: ScreenerParams = {
        page: targetPage,
        limit: options.default_limit,
        sort,
        direction,
      };

      if (search.trim()) params.search = search.trim();
      if (sectorId) params.sector_id = sectorId;
      if (syariah) params.is_syariah = syariah;

      for (const [key, range] of Object.entries(bounds)) {
        if (range.min !== '') params[`${key}_min`] = range.min;
        if (range.max !== '') params[`${key}_max`] = range.max;
      }

      try {
        const { data, meta } = await screenerApi.run(params);
        setResult(data);
        setPage(data.page);

        // Saldo di sidebar ikut turun begitu kredit terpotong.
        if (typeof meta.credit_balance === 'number') setBalance(meta.credit_balance);

        if (meta.charge_type === 'credit') {
          setCharge(`${meta.credits_charged} kredit terpakai`);
        } else if (meta.charge_type === 'quota') {
          setCharge(
            meta.quota_remaining === null
              ? 'Kuota langganan (tanpa batas)'
              : `Sisa kuota harian: ${meta.quota_remaining}`,
          );
        } else {
          setCharge('Gratis');
        }
      } catch (e) {
        const message =
          e instanceof ApiError && e.isInsufficientCredit
            ? `${e.message} Silakan isi ulang kredit Anda.`
            : e instanceof Error
              ? e.message
              : 'Gagal menjalankan screening.';
        setRunError(message);
      } finally {
        setRunning(false);
      }
    },
    [options, sort, direction, search, sectorId, syariah, bounds, setBalance],
  );

  const setBound = (key: string, side: 'min' | 'max', value: string) =>
    setBounds((prev) => ({
      ...prev,
      [key]: { ...(prev[key] ?? { min: '', max: '' }), [side]: value },
    }));

  const resetFilters = () => {
    setBounds({});
    setSearch('');
    setSectorId('');
    setSyariah('');
  };

  const toggleSort = (key: string) => {
    if (sort === key) {
      setDirection((d) => (d === 'DESC' ? 'ASC' : 'DESC'));
    } else {
      setSort(key);
      setDirection('DESC');
    }
  };

  const activeFilterCount = useMemo(
    () =>
      Object.values(bounds).filter((b) => b.min !== '' || b.max !== '').length +
      (search.trim() ? 1 : 0) +
      (sectorId ? 1 : 0) +
      (syariah ? 1 : 0),
    [bounds, search, sectorId, syariah],
  );

  if (optionsError) return <ErrorNotice message={optionsError} />;
  if (!options) return <Spinner label="Menyiapkan screener…" />;

  const visibleMetrics = showAllFilters ? options.metrics : options.metrics.slice(0, 6);

  return (
    <div className="space-y-5">
      <header className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold text-ink">Fundamental Screener</h1>
          <p className="text-sm text-ink-muted">
            Saring emiten IHSG berdasarkan metrik fundamental terbaru.
          </p>
        </div>
        <span className="flex items-center gap-1.5 text-xs text-ink-muted">
          <Coins size={14} className="text-primary" />1 kredit per screening
        </span>
      </header>

      <Card>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div className="space-y-1.5">
            <label htmlFor="search" className="block text-sm font-medium text-ink">
              Kode / nama emiten
            </label>
            <div className="relative">
              <Search
                size={16}
                className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted"
              />
              <input
                id="search"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Misalnya BBCA"
                className="w-full rounded-card border border-border-soft bg-canvas py-2.5 pl-9 pr-3 text-sm text-ink placeholder:text-ink-muted/60 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
              />
            </div>
          </div>

          <div className="space-y-1.5">
            <label htmlFor="sector" className="block text-sm font-medium text-ink">
              Sektor
            </label>
            <select
              id="sector"
              value={sectorId}
              onChange={(e) => setSectorId(e.target.value)}
              className="w-full rounded-card border border-border-soft bg-canvas px-3 py-2.5 text-sm text-ink focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
            >
              <option value="">Semua sektor</option>
              {options.sectors.map((sector) => (
                <option key={sector.id} value={sector.id}>
                  {sector.name}
                  {sector.sub_sector ? ` — ${sector.sub_sector}` : ''}
                </option>
              ))}
            </select>
          </div>

          <div className="space-y-1.5">
            <label htmlFor="syariah" className="block text-sm font-medium text-ink">
              Kepatuhan syariah
            </label>
            <select
              id="syariah"
              value={syariah}
              onChange={(e) => setSyariah(e.target.value)}
              className="w-full rounded-card border border-border-soft bg-canvas px-3 py-2.5 text-sm text-ink focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
            >
              <option value="">Semua emiten</option>
              <option value="1">Hanya syariah</option>
              <option value="0">Non-syariah</option>
            </select>
          </div>
        </div>

        <div className="mt-5 border-t border-border-soft pt-4">
          <div className="mb-3 flex items-center justify-between">
            <p className="flex items-center gap-2 text-sm font-medium text-ink">
              <SlidersHorizontal size={15} className="text-primary" />
              Rentang metrik
            </p>
            <button
              type="button"
              onClick={() => setShowAllFilters((v) => !v)}
              className="text-xs font-medium text-primary hover:underline"
            >
              {showAllFilters
                ? 'Tampilkan lebih sedikit'
                : `Tampilkan semua (${options.metrics.length})`}
            </button>
          </div>

          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {visibleMetrics.map((metric) => {
              const range = bounds[metric.key] ?? { min: '', max: '' };

              return (
                <div key={metric.key} className="space-y-1.5">
                  <p className="text-xs text-ink-muted">
                    {metric.label}
                    <span className="ml-1 opacity-70">({metric.unit})</span>
                  </p>
                  <div className="flex items-center gap-2">
                    <input
                      type="number"
                      step="any"
                      inputMode="decimal"
                      aria-label={`${metric.label} minimum`}
                      placeholder="min"
                      value={range.min}
                      onChange={(e) => setBound(metric.key, 'min', e.target.value)}
                      className="w-full rounded-card border border-border-soft bg-canvas px-2.5 py-2 text-sm text-ink placeholder:text-ink-muted/60 focus:border-primary focus:outline-none"
                    />
                    <span className="text-ink-muted">–</span>
                    <input
                      type="number"
                      step="any"
                      inputMode="decimal"
                      aria-label={`${metric.label} maksimum`}
                      placeholder="maks"
                      value={range.max}
                      onChange={(e) => setBound(metric.key, 'max', e.target.value)}
                      className="w-full rounded-card border border-border-soft bg-canvas px-2.5 py-2 text-sm text-ink placeholder:text-ink-muted/60 focus:border-primary focus:outline-none"
                    />
                  </div>
                </div>
              );
            })}
          </div>
        </div>

        <div className="mt-5 flex flex-wrap items-center gap-3 border-t border-border-soft pt-4">
          <Button onClick={() => run(1)} loading={running}>
            <Filter size={16} />
            Jalankan screening
          </Button>
          <Button variant="outline" onClick={resetFilters} disabled={running}>
            <RotateCcw size={15} />
            Reset
          </Button>
          {activeFilterCount > 0 && (
            <Badge tone="primary">{activeFilterCount} filter aktif</Badge>
          )}
          {charge && !running && <Badge tone="accent">{charge}</Badge>}
        </div>
      </Card>

      {runError && <ErrorNotice message={runError} onRetry={() => run(page)} />}

      {running && <Spinner label="Menyaring emiten…" />}

      <AnimatePresence mode="wait">
        {!running && result && (
          <motion.div
            key={`${result.page}-${result.sort}-${result.direction}`}
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.2 }}
          >
            <Card className="p-0">
              <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border-soft px-5 py-3.5">
                <p className="text-sm text-ink">
                  <span className="font-semibold">{result.total}</span> emiten cocok
                </p>
                <p className="text-xs text-ink-muted">
                  Halaman {result.page} dari {Math.max(result.total_pages, 1)}
                </p>
              </div>

              {result.items.length === 0 ? (
                <EmptyState
                  icon={<Filter size={28} />}
                  title="Tidak ada emiten yang cocok"
                  description="Coba longgarkan rentang metrik atau hapus sebagian filter."
                />
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full min-w-[840px] text-sm">
                    <thead>
                      <tr className="border-b border-border-soft text-left text-xs uppercase tracking-wide text-ink-muted">
                        <SortableHeader
                          label="Emiten"
                          sortKey="symbol"
                          activeSort={result.sort}
                          direction={result.direction}
                          onSort={toggleSort}
                          className="sticky left-0 bg-surface"
                        />
                        <th className="px-4 py-3 font-medium">Sektor</th>
                        <SortableHeader
                          label="Kap. Pasar"
                          sortKey="market_cap"
                          activeSort={result.sort}
                          direction={result.direction}
                          onSort={toggleSort}
                          align="right"
                        />
                        {columns.map((metric) => (
                          <SortableHeader
                            key={metric.key}
                            label={metric.label}
                            sortKey={metric.key}
                            activeSort={result.sort}
                            direction={result.direction}
                            onSort={toggleSort}
                            align="right"
                          />
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {result.items.map((row) => (
                        <tr
                          key={row.id}
                          className="border-b border-border-soft/60 transition last:border-0 hover:bg-primary/5"
                        >
                          <td className="sticky left-0 bg-surface px-4 py-3">
                            <Link
                              to={`/saham/${row.symbol}`}
                              className="font-semibold text-primary hover:underline"
                            >
                              {row.symbol}
                            </Link>
                            <p className="max-w-[220px] truncate text-xs text-ink-muted">
                              {row.company_name_short ?? row.company_name}
                            </p>
                          </td>
                          <td className="px-4 py-3 text-xs text-ink-muted">
                            {row.sector_name ?? '—'}
                          </td>
                          <td className="px-4 py-3 text-right tabular-nums text-ink">
                            {formatCompact(row.market_cap)}
                          </td>
                          {columns.map((metric) => (
                            <td
                              key={metric.key}
                              className="px-4 py-3 text-right tabular-nums text-ink"
                            >
                              {formatMetric(
                                row[metric.key as keyof typeof row] as number | null,
                                metric.unit,
                              )}
                            </td>
                          ))}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}

              {result.total_pages > 1 && (
                <div className="flex items-center justify-between gap-3 border-t border-border-soft px-5 py-3">
                  <Button
                    variant="outline"
                    disabled={result.page <= 1 || running}
                    onClick={() => run(result.page - 1)}
                  >
                    Sebelumnya
                  </Button>
                  <span className="text-xs text-ink-muted">
                    Berpindah halaman memerlukan 1 kredit lagi
                  </span>
                  <Button
                    variant="outline"
                    disabled={result.page >= result.total_pages || running}
                    onClick={() => run(result.page + 1)}
                  >
                    Berikutnya
                  </Button>
                </div>
              )}
            </Card>
          </motion.div>
        )}
      </AnimatePresence>

      {!running && !result && !runError && (
        <Card>
          <EmptyState
            icon={<SlidersHorizontal size={28} />}
            title="Atur filter, lalu jalankan screening"
            description="Setiap kali dijalankan, screening memotong 1 kredit dari saldo Anda."
          />
        </Card>
      )}
    </div>
  );
}

function SortableHeader({
  label,
  sortKey,
  activeSort,
  direction,
  onSort,
  align = 'left',
  className,
}: {
  label: string;
  sortKey: string;
  activeSort: string;
  direction: 'ASC' | 'DESC';
  onSort: (key: string) => void;
  align?: 'left' | 'right';
  className?: string;
}) {
  const isActive = activeSort === sortKey;

  return (
    <th className={cn('px-4 py-3 font-medium', className)}>
      <button
        type="button"
        onClick={() => onSort(sortKey)}
        className={cn(
          'inline-flex items-center gap-1 transition hover:text-primary',
          isActive && 'text-primary',
          align === 'right' && 'w-full justify-end',
        )}
      >
        {label}
        {isActive &&
          (direction === 'DESC' ? <ArrowDown size={12} /> : <ArrowUp size={12} />)}
      </button>
    </th>
  );
}
