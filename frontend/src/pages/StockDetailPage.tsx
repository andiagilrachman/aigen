import { useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  ArrowLeft,
  Building2,
  ExternalLink,
  FileText,
  PieChart,
  ShieldCheck,
} from 'lucide-react';
import { ApiError } from '../api/client';
import { stockApi } from '../api/endpoints';
import type { StockDetail } from '../api/types';
import { useAuth } from '../context/AuthContext';
import { Badge, Card, EmptyState, ErrorNotice, Spinner } from '../components/ui';
import { formatCompact, formatDate, formatMetric, formatNumber } from '../lib/format';
import { cn } from '../lib/cn';

type StatementType = 'IS' | 'BS' | 'CF';

const STATEMENT_LABELS: Record<StatementType, string> = {
  IS: 'Laba Rugi',
  BS: 'Neraca',
  CF: 'Arus Kas',
};

/**
 * Detail emiten — aksi berbayar 2 kredit.
 *
 * Kartu metrik dibangun dari `metrics_meta` yang dikirim backend, sehingga
 * label dan satuannya tidak pernah berbeda antara screener dan halaman ini.
 */
export function StockDetailPage() {
  const { symbol = '' } = useParams<{ symbol: string }>();
  const { setBalance } = useAuth();

  const [detail, setDetail] = useState<StockDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [charge, setCharge] = useState<string | null>(null);
  const [statement, setStatement] = useState<StatementType>('IS');

  useEffect(() => {
    let alive = true;
    setLoading(true);
    setError(null);
    setDetail(null);

    stockApi
      .detail(symbol)
      .then(({ data, meta }) => {
        if (!alive) return;
        setDetail(data);
        if (typeof meta.credit_balance === 'number') setBalance(meta.credit_balance);
        if (meta.charge_type === 'credit' && meta.credits_charged) {
          setCharge(`${meta.credits_charged} kredit terpakai`);
        }
      })
      .catch((e: unknown) => {
        if (!alive) return;
        setError(
          e instanceof ApiError && e.isInsufficientCredit
            ? `${e.message} Isi ulang kredit untuk membuka detail emiten.`
            : e instanceof Error
              ? e.message
              : 'Gagal memuat detail emiten.',
        );
      })
      .finally(() => alive && setLoading(false));

    return () => {
      alive = false;
    };
  }, [symbol, setBalance]);

  const metricCards = useMemo(() => {
    if (!detail?.snapshot) return [];

    return Object.entries(detail.metrics_meta).map(([key, meta]) => ({
      key,
      label: meta.label,
      unit: meta.unit,
      value: (detail.snapshot?.[key] ?? null) as number | null,
    }));
  }, [detail]);

  if (loading) return <Spinner label={`Memuat detail ${symbol}…`} />;

  if (error) {
    return (
      <div className="space-y-4">
        <BackLink />
        <ErrorNotice message={error} />
      </div>
    );
  }

  if (!detail) return null;

  const { stock, snapshot, financials, shareholders } = detail;
  const periods = Object.keys(financials[statement] ?? {});

  return (
    <div className="space-y-5">
      <BackLink />

      <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }}>
        <Card>
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div className="flex items-start gap-4">
              {stock.logo_url ? (
                <img
                  src={stock.logo_url}
                  alt=""
                  className="size-14 rounded-card border border-border-soft bg-canvas object-contain p-1.5"
                />
              ) : (
                <div className="grid size-14 place-items-center rounded-card bg-primary/15 text-primary">
                  <Building2 size={24} />
                </div>
              )}

              <div>
                <div className="flex flex-wrap items-center gap-2">
                  <h1 className="text-2xl font-semibold text-ink">{stock.symbol}</h1>
                  {stock.is_syariah && (
                    <Badge tone="good">
                      <ShieldCheck size={12} className="mr-1" />
                      Syariah
                    </Badge>
                  )}
                  {snapshot?.rating && (
                    <Badge tone="primary">{String(snapshot.rating)}</Badge>
                  )}
                </div>
                <p className="text-sm text-ink">{stock.company_name}</p>
                <p className="mt-0.5 text-xs text-ink-muted">
                  {[stock.sector_name, stock.sub_sector].filter(Boolean).join(' • ') ||
                    'Sektor belum tersedia'}
                </p>
              </div>
            </div>

            <div className="flex flex-col items-end gap-2">
              {charge && <Badge tone="accent">{charge}</Badge>}
              {stock.website && (
                <a
                  href={stock.website}
                  target="_blank"
                  rel="noreferrer noopener"
                  className="inline-flex items-center gap-1.5 text-xs text-primary hover:underline"
                >
                  Situs resmi <ExternalLink size={12} />
                </a>
              )}
            </div>
          </div>

          <dl className="mt-5 grid gap-4 border-t border-border-soft pt-4 sm:grid-cols-2 lg:grid-cols-4">
            <Fact label="Kapitalisasi pasar" value={formatCompact(stock.market_cap)} />
            <Fact label="Bursa" value={stock.exchange ?? '—'} />
            <Fact label="Tanggal IPO" value={formatDate(stock.listing_date)} />
            <Fact
              label="Data per"
              value={formatDate(snapshot?.snapshot_date as string | undefined)}
            />
          </dl>
        </Card>
      </motion.div>

      {snapshot ? (
        <section className="space-y-3">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-ink-muted">
            Metrik fundamental
          </h2>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {metricCards.map((metric, index) => (
              <motion.div
                key={metric.key}
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: Math.min(index * 0.02, 0.3) }}
              >
                <Card className="p-4">
                  <p className="truncate text-xs text-ink-muted" title={metric.label}>
                    {metric.label}
                  </p>
                  <p className="mt-1 text-lg font-semibold tabular-nums text-ink">
                    {formatMetric(metric.value, metric.unit)}
                  </p>
                </Card>
              </motion.div>
            ))}
          </div>
        </section>
      ) : (
        <Card>
          <EmptyState
            title="Snapshot fundamental belum tersedia"
            description="Data akan muncul setelah proses sinkronisasi vendor berikutnya."
          />
        </Card>
      )}

      <div className="grid gap-5 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 className="flex items-center gap-2 font-semibold text-ink">
              <FileText size={16} className="text-primary" />
              Laporan keuangan
            </h2>
            <div className="flex gap-1 rounded-card bg-canvas p-1">
              {(Object.keys(STATEMENT_LABELS) as StatementType[]).map((type) => (
                <button
                  key={type}
                  type="button"
                  onClick={() => setStatement(type)}
                  className={cn(
                    'rounded-card px-3 py-1.5 text-xs font-medium transition',
                    statement === type
                      ? 'bg-primary text-canvas'
                      : 'text-ink-muted hover:text-ink',
                  )}
                >
                  {STATEMENT_LABELS[type]}
                </button>
              ))}
            </div>
          </div>

          {periods.length === 0 ? (
            <EmptyState
              title={`Belum ada data ${STATEMENT_LABELS[statement].toLowerCase()}`}
              description="Riwayat laporan diisi oleh job sinkronisasi vendor."
            />
          ) : (
            <div className="max-h-[420px] space-y-5 overflow-y-auto pr-1">
              {periods.map((period) => (
                <div key={period}>
                  <p className="sticky top-0 bg-surface py-1 text-xs font-semibold uppercase tracking-wide text-primary">
                    {period}
                  </p>
                  <table className="w-full text-sm">
                    <tbody>
                      {financials[statement][period].map((line, i) => (
                        <tr key={`${period}-${i}`} className="border-b border-border-soft/50">
                          <td
                            className="py-2 pr-3 text-ink-muted"
                            style={{ paddingLeft: `${(line.account_level - 1) * 14}px` }}
                          >
                            {line.account_name}
                          </td>
                          <td className="py-2 text-right tabular-nums text-ink">
                            {formatCompact(line.amount)}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ))}
            </div>
          )}
        </Card>

        <Card>
          <h2 className="mb-4 flex items-center gap-2 font-semibold text-ink">
            <PieChart size={16} className="text-accent" />
            Komposisi pemegang saham
          </h2>

          {shareholders.length === 0 ? (
            <EmptyState title="Data pemegang saham belum tersedia" />
          ) : (
            <ul className="space-y-3">
              {shareholders.map((holder, i) => (
                <li key={`${holder.holder_name}-${i}`} className="space-y-1.5">
                  <div className="flex items-baseline justify-between gap-3">
                    <span className="min-w-0 flex-1 truncate text-sm text-ink">
                      {holder.holder_name}
                    </span>
                    <span className="shrink-0 text-sm font-medium tabular-nums text-ink">
                      {formatNumber(holder.percentage)}%
                    </span>
                  </div>
                  <div className="h-1.5 overflow-hidden rounded-full bg-ink-muted/15">
                    <motion.div
                      initial={{ width: 0 }}
                      animate={{ width: `${Math.min(holder.percentage ?? 0, 100)}%` }}
                      transition={{ duration: 0.6, delay: i * 0.05 }}
                      className="h-full rounded-full bg-accent"
                    />
                  </div>
                  {holder.badge && (
                    <span className="text-[11px] text-ink-muted">{holder.badge}</span>
                  )}
                </li>
              ))}
            </ul>
          )}
        </Card>
      </div>
    </div>
  );
}

function BackLink() {
  return (
    <Link
      to="/screener"
      className="inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-primary"
    >
      <ArrowLeft size={15} />
      Kembali ke screener
    </Link>
  );
}

function Fact({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <dt className="text-xs text-ink-muted">{label}</dt>
      <dd className="mt-0.5 text-sm font-medium text-ink">{value}</dd>
    </div>
  );
}
