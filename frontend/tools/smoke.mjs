/**
 * Smoke test alur utama, dijalankan di jsdom.
 *
 * Lingkungan ini tidak punya browser sungguhan, sedangkan `tsc` tidak bisa
 * menangkap layar putih akibat error runtime. Skrip ini menjalankan bundel
 * aplikasi yang sudah dikompilasi di dalam DOM tiruan, memakai fixture yang
 * DIREKAM DARI BACKEND SUNGGUHAN (tools/fixtures/*.json), lalu memeriksa
 * apakah tiap layar benar-benar menggambar isinya.
 *
 * Jalankan:  npm run smoke
 */

import { readFileSync, readdirSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { JSDOM } from 'jsdom';

const root = dirname(dirname(fileURLToPath(import.meta.url)));
const bundleDir = join(root, 'dist-smoke');
const fixtureDir = join(root, 'tools', 'fixtures');

if (!readdirSync(bundleDir).includes('smoke.js')) {
  console.error('Bundel smoke tidak ditemukan. Jalankan `npm run smoke`.');
  process.exit(1);
}

const fixture = (name) => JSON.parse(readFileSync(join(fixtureDir, `${name}.json`), 'utf8'));

const CONFIG = fixture('config');
const AUTH_ME = fixture('auth-me');
const NAVIGATION = fixture('navigation');
const SCREENER_OPTIONS = fixture('screener-options');
const SCREENER_RUN = fixture('screener-run');
const STOCK_BBCA = fixture('stock-bbca');

/**
 * @param {{ loggedIn?: boolean, path?: string }} opts
 */
async function boot({ loggedIn = true, path = '/screener' } = {}) {
  const dom = new JSDOM(
    '<!doctype html><html lang="id"><body><div id="root"></div></body></html>',
    {
      url: `http://localhost:5173${path}`,
      pretendToBeVisual: true,
      runScripts: 'outside-only',
    },
  );

  const { window } = dom;

  window.matchMedia = () => ({
    matches: false,
    addEventListener() {},
    removeEventListener() {},
    addListener() {},
    removeListener() {},
  });

  // jsdom tidak menyediakan fetch/Response; pakai implementasi global Node.
  window.Response = Response;
  window.Headers = Headers;
  window.Request = Request;
  window.scrollTo = () => {};

  const calls = [];
  const unstubbed = [];

  const routes = {
    '/api/config': [200, CONFIG],
    '/api/auth/me': loggedIn
      ? [200, AUTH_ME]
      : [401, { success: false, error: { message: 'Belum masuk', code: 'unauthenticated' } }],
    '/api/navigation': [200, NAVIGATION],
    '/api/screener/options': [200, SCREENER_OPTIONS],
    '/api/screener/run': [200, SCREENER_RUN],
    '/api/stocks/BBCA': [200, STOCK_BBCA],
  };

  window.fetch = async (input, init) => {
    const url = typeof input === 'string' ? input : input.url;
    const path = url.split('?')[0];
    calls.push(`${init?.method ?? 'GET'} ${path}`);

    const route = routes[path];
    if (!route) unstubbed.push(path);

    const [status, body] = route ?? [
      404,
      { success: false, error: { message: 'not stubbed: ' + path } },
    ];

    return new Response(JSON.stringify(body), {
      status,
      headers: { 'content-type': 'application/json' },
    });
  };

  const errors = [];
  window.addEventListener('error', (e) => errors.push(e.message ?? String(e.error)));
  window.console.error = (...args) => errors.push(args.map(String).join(' '));
  window.console.warn = () => {};

  window.eval(readFileSync(join(bundleDir, 'smoke.js'), 'utf8'));

  const settle = (ms = 400) => new Promise((r) => setTimeout(r, ms));
  await settle(1200);

  return {
    window,
    calls,
    unstubbed,
    errors,
    settle,
    text: () => window.document.getElementById('root').textContent ?? '',
    html: () => window.document.getElementById('root').innerHTML ?? '',
    /** Cari tombol/elemen berdasarkan teksnya, lalu klik. */
    click(selector, label) {
      const el = [...window.document.querySelectorAll(selector)].find((n) =>
        (n.textContent ?? '').toLowerCase().includes(label.toLowerCase()),
      );
      if (!el) throw new Error(`Elemen "${label}" (${selector}) tidak ditemukan`);
      el.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
      return el;
    },
  };
}

const results = [];
const check = (label, ok, detail = '') => {
  results.push({ label, ok, detail });
  console.log(`${ok ? '✓' : '✗'} ${label}${!ok && detail ? ` — ${detail}` : ''}`);
};

// ---------------------------------------------------------------------------
console.log('\n[1] Belum login: rute privat dialihkan ke halaman login');
// ---------------------------------------------------------------------------
{
  const app = await boot({ loggedIn: false, path: '/screener' });
  const text = app.text();

  check('halaman login tampil', /masuk ke/i.test(text), text.slice(0, 120));
  check('branding dari /config dipakai', text.includes(CONFIG.data.branding.site_name));
  check(
    'tema dari database diterapkan',
    app.window.document.documentElement.style.getPropertyValue('--aigen-primary') ===
      CONFIG.data.themes[0].primary_color,
  );
  check('screener tidak ikut dimuat tanpa sesi', !app.calls.includes('POST /api/screener/run'));
  check('tanpa error runtime', app.errors.length === 0, app.errors[0]);
}

// ---------------------------------------------------------------------------
console.log('\n[2] Sudah login: sidebar dibangun dari nav_menu');
// ---------------------------------------------------------------------------
{
  const app = await boot({ loggedIn: true, path: '/screener' });
  const text = app.text();
  const menus = NAVIGATION.data.menus;

  check('memanggil /navigation', app.calls.includes('GET /api/navigation'));

  const missing = menus.filter((m) => !text.includes(m.label)).map((m) => m.label);
  check(`${menus.length} menu dari database tampil`, missing.length === 0, `hilang: ${missing}`);

  check(
    'saldo kredit tampil di sidebar',
    text.includes(String(AUTH_ME.data.wallet.balance)),
    `saldo ${AUTH_ME.data.wallet.balance} tidak ditemukan`,
  );
  check('disclaimer legal tampil', text.includes('rekomendasi'));
  check('tanpa error runtime', app.errors.length === 0, app.errors[0]);
}

// ---------------------------------------------------------------------------
console.log('\n[3] Screener: form dari /screener/options, hasil dari /screener/run');
// ---------------------------------------------------------------------------
{
  const app = await boot({ loggedIn: true, path: '/screener' });

  check('memanggil /screener/options', app.calls.includes('GET /api/screener/options'));

  const sectors = SCREENER_OPTIONS.data.sectors;
  const optionCount = app.window.document.querySelectorAll('select option').length;
  check(
    'daftar sektor terisi dari backend',
    optionCount >= sectors.length,
    `hanya ${optionCount} opsi`,
  );

  const numeric = app.window.document.querySelectorAll('input[type="number"]');
  check('input rentang metrik dirender', numeric.length >= 12, `${numeric.length} input`);

  check(
    'screening TIDAK jalan otomatis (kredit tidak terpotong tanpa aksi)',
    !app.calls.includes('POST /api/screener/run'),
  );

  app.click('button', 'Jalankan screening');
  await app.settle(900);

  check('klik memicu POST /screener/run', app.calls.includes('POST /api/screener/run'));

  const text = app.text();
  const rows = SCREENER_RUN.data.items;
  const missingRows = rows.filter((r) => !text.includes(r.symbol)).map((r) => r.symbol);
  check(`${rows.length} emiten hasil tampil di tabel`, missingRows.length === 0, `hilang: ${missingRows}`);

  check('jumlah total ditampilkan', text.includes(String(SCREENER_RUN.data.total)));

  const meta = SCREENER_RUN.meta ?? {};
  if (meta.charge_type === 'quota') {
    check('sisa kuota diberitahukan ke pengguna', /kuota/i.test(text), text.slice(0, 200));
  } else {
    check('kredit terpakai diberitahukan', /kredit/i.test(text));
  }

  const links = [...app.window.document.querySelectorAll('a[href*="/saham/"]')];
  check('tiap baris menautkan ke halaman detail', links.length >= rows.length, `${links.length} tautan`);

  check('tanpa error runtime', app.errors.length === 0, app.errors[0]);
}

// ---------------------------------------------------------------------------
console.log('\n[4] Detail emiten: 2 kredit, metrik dari metrics_meta');
// ---------------------------------------------------------------------------
{
  const app = await boot({ loggedIn: true, path: '/saham/BBCA' });
  const text = app.text();
  const stock = STOCK_BBCA.data.stock;
  const snapshot = STOCK_BBCA.data.snapshot;
  const metaKeys = Object.keys(STOCK_BBCA.data.metrics_meta);

  check('memanggil /stocks/BBCA', app.calls.includes('GET /api/stocks/BBCA'));
  check('kode & nama emiten tampil', text.includes(stock.symbol) && text.includes(stock.company_name));
  check('rating dari snapshot tampil', text.includes(snapshot.rating));

  const labels = metaKeys.map((k) => STOCK_BBCA.data.metrics_meta[k].label);
  const missingLabels = labels.filter((l) => !text.includes(l));
  check(
    `${metaKeys.length} kartu metrik dirender dari metrics_meta`,
    missingLabels.length === 0,
    `hilang: ${missingLabels.slice(0, 4)}`,
  );

  const periods = Object.keys(STOCK_BBCA.data.financials.IS ?? {});
  check(
    'periode laporan keuangan tampil',
    periods.length === 0 || text.includes(periods[0]),
    `periode ${periods[0]}`,
  );

  const holders = STOCK_BBCA.data.shareholders;
  check(
    'pemegang saham tampil',
    holders.length === 0 || text.includes(holders[0].holder_name),
  );

  const charged = STOCK_BBCA.meta?.credits_charged;
  if (charged) {
    check(`biaya ${charged} kredit diberitahukan`, text.includes(String(charged)));
  }

  check('tanpa error runtime', app.errors.length === 0, app.errors[0]);
}

// ---------------------------------------------------------------------------
const failed = results.filter((r) => !r.ok);
console.log('\n' + '─'.repeat(60));
if (failed.length === 0) {
  console.log(`✓ Semua lolos — ${results.length} pemeriksaan`);
} else {
  console.log(`✗ ${failed.length} dari ${results.length} pemeriksaan gagal`);
  failed.forEach((f) => console.log(`  - ${f.label}${f.detail ? ` (${f.detail})` : ''}`));
}
process.exit(failed.length ? 1 : 0);
