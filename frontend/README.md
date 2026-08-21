# AIGen Frontend

Antarmuka AIGen: React 19 + Vite 6 + TypeScript + Tailwind 4 + Framer Motion.

Rilis pertama mencakup satu alur utuh: **Login → Sidebar → Screener → Detail emiten**.
Menu lain muncul sebagai "Coming Soon" langsung dari isi tabel `nav_menu`.

## Menjalankan

```bash
npm install
cp .env.example .env      # opsional, hanya bila backend tidak di port 8080
npm run dev               # http://localhost:5173
```

Backend harus hidup lebih dulu. Lihat `../backend/README.md`.

## Prinsip NO HARDCODE

Aturan proyek: apa pun yang bisa berubah tanpa deploy harus datang dari database.
Di frontend itu berarti:

| Yang tampil            | Sumbernya                                            |
| ---------------------- | ---------------------------------------------------- |
| Nama situs, tagline    | `GET /config` → `branding` (tabel `system_settings`) |
| Disclaimer, tautan legal | `GET /config` → `legal`                            |
| Palet warna & radius   | `GET /config` → `themes` (tabel `theme_presets`)     |
| Menu sidebar + ikonnya | `GET /navigation` (tabel `nav_menu`)                 |
| Isi dialog Coming Soon | `GET /navigation` → `coming_soon`                    |
| Kolom & filter screener| `GET /screener/options` (daftar putih backend)       |
| Label & satuan metrik  | `metrics_meta` pada respons detail                   |
| Biaya kredit tiap aksi | `meta` pada respons, tabel `credit_costs`            |

Tidak ada satu pun nilai di atas yang ditulis di dalam komponen. Menambah menu
atau metrik baru cukup lewat database — tanpa menyentuh kode ini.

### Warna

Tailwind tidak pernah menerima kode warna langsung. `src/index.css` memetakan
token Tailwind ke CSS variable:

```
--color-primary  →  var(--aigen-primary)
```

`ConfigContext.applyTheme()` mengisi `--aigen-*` saat runtime dari preset yang
dipilih. Karena itu ganti tema = ganti nilai variabel, tanpa reload.

**Satu-satunya pengecualian** ada di `ThemePicker`: kartu pratinjau memakai
`style` inline berisi warna preset yang sedang dipratinjau — memang bukan warna
tema aktif, jadi tidak mungkin lewat variabel global.

## Struktur

```
src/
  api/         client.ts (amplop + ApiError), endpoints.ts (satu-satunya tempat path URL), types.ts
  context/     ConfigContext (branding + tema), AuthContext (sesi + saldo)
  hooks/       useNavigation (menu, dengan cache tingkat modul)
  components/  Sidebar, AppLayout, RequireAuth, ThemePicker, ComingSoonDialog, DynamicIcon, ui
  pages/       LoginPage, ScreenerPage, StockDetailPage, NotFoundPage
  lib/         format (angka/tanggal ala Indonesia), cn
```

Aturan yang dijaga:

- Komponen **tidak pernah** menyusun URL sendiri — selalu lewat `api/endpoints.ts`.
- `client.ts` membuka amplop `{success, data, meta}` dan melempar `ApiError`,
  sehingga komponen hanya berurusan dengan `{data, meta}`.
- Ikon menu berupa string di database, dirender lewat `DynamicIcon` dengan
  dynamic import. Mengimpor seluruh pustaka lucide menambah ~900 kB ke bundel.

## Panggilan API & cookie sesi

Semua panggilan memakai path relatif `/api/...`, diteruskan Vite ke backend
(lihat `vite.config.ts`). Konsekuensinya same-origin: cookie sesi ikut terkirim
tanpa perlu konfigurasi CORS saat pengembangan.

Browser pengguna tidak berada di mesin yang sama dengan backend, jadi frontend
tidak boleh memanggil `localhost` secara langsung.

Saat produksi, letakkan frontend dan backend di origin yang sama, atau atur
`CORS_ORIGINS` + `SESSION_SAMESITE=None` & `SESSION_SECURE=true` di backend.

## Pengujian

```bash
npm run typecheck   # tsc strict
npm run build       # bundel produksi
npm run smoke       # jalankan bundel di jsdom, periksa tiap layar
npm run verify      # ketiganya sekaligus
```

`tools/smoke.mjs` menjalankan aplikasi hasil kompilasi di dalam DOM tiruan
memakai fixture di `tools/fixtures/*.json` — **rekaman respons backend
sungguhan**, bukan karangan. Ia memverifikasi 28 hal, antara lain:

- rute privat mengalihkan ke login saat tidak ada sesi;
- 10 menu sidebar benar-benar berasal dari `nav_menu`;
- screening **tidak** jalan otomatis saat halaman dibuka (kredit tidak
  terpotong tanpa aksi pengguna);
- 19 kartu metrik dibangun dari `metrics_meta`;
- biaya kredit diberitahukan ke pengguna.

Alasannya: `tsc` tidak bisa menangkap layar putih akibat error runtime, dan
lingkungan pengembangan ini tidak punya browser sungguhan.

Bila kontrak backend berubah, rekam ulang fixture-nya:

```bash
# backend hidup di :8080, akun demo sudah ada
curl -s -c /tmp/j -X POST localhost:8080/auth/login -H 'Content-Type: application/json' \
  -d '{"email":"demo@aigen.test","password":"demo1234"}' >/dev/null
curl -s -b /tmp/j localhost:8080/navigation -o tools/fixtures/navigation.json
```
