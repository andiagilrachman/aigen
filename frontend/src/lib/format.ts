/** Pemformat angka gaya Indonesia yang dipakai bersama oleh tabel dan kartu. */

const decimal = new Intl.NumberFormat('id-ID', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

const integer = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });

export function formatNumber(value: number | null | undefined, digits = 2): string {
  if (value === null || value === undefined || Number.isNaN(value)) return '—';
  return digits === 0 ? integer.format(value) : decimal.format(value);
}

/** Nilai besar dipendekkan agar kolom tabel tidak melebar tak terkendali. */
export function formatCompact(value: number | null | undefined): string {
  if (value === null || value === undefined || Number.isNaN(value)) return '—';

  const abs = Math.abs(value);
  if (abs >= 1e12) return `${decimal.format(value / 1e12)} T`;
  if (abs >= 1e9) return `${decimal.format(value / 1e9)} M`;
  if (abs >= 1e6) return `${decimal.format(value / 1e6)} Jt`;
  return integer.format(value);
}

export function formatRupiah(value: number | null | undefined): string {
  if (value === null || value === undefined || Number.isNaN(value)) return '—';
  return `Rp ${integer.format(value)}`;
}

/** Terapkan satuan dari metadata metrik backend. */
export function formatMetric(value: number | null | undefined, unit: string): string {
  if (value === null || value === undefined || Number.isNaN(value)) return '—';

  switch (unit) {
    case '%':
      return `${decimal.format(value)}%`;
    case 'x':
      return `${decimal.format(value)}x`;
    case 'Rp':
      return formatRupiah(value);
    default:
      return decimal.format(value);
  }
}

export function formatDate(value: string | null | undefined): string {
  if (!value) return '—';
  const date = new Date(value.replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(date);
}
