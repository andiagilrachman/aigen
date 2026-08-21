# Referensi Endpoint — AIGen
## Pemetaan DataSectors (105 endpoint) & Invezgo (91 endpoint) ke Fase 1 (Fundamental) dan Coming Soon

---

## 1. FASE 1 — FUNDAMENTAL (dibangun penuh)

### 1.1 Identitas & Pencarian Saham
| Endpoint | Vendor | Parameter |
|---|---|---|
| `GET /api/search/market` | DataSectors | query |
| `GET /api/stocks/v2/search` | DataSectors | symbol, market |
| `GET /analysis/list/stock` | Invezgo | — (daftar semua saham IDX) |
| `GET /analysis/information/{code}` | Invezgo | code — profil lengkap: alamat, sektor, subsektor, direksi/komisaris, anak perusahaan, tanggal IPO, kategori (Syariah/IDX index membership) |

### 1.2 Laporan Keuangan & Rasio Kunci
| Endpoint | Vendor | Parameter | Catatan |
|---|---|---|---|
| `GET /api/stocks/v2/equities` | DataSectors | symbol, market | Profil, laporan tahunan, rasio kunci, analyst estimate, ownership |
| `GET /api/stocks/v2/key-ratios` | DataSectors | symbol, market | Valuasi/profitabilitas/likuiditas/leverage/efisiensi + rata-rata 3Y/5Y & industri, histori dari 1994 |
| `GET /api/stocks/v2/insights` | DataSectors | symbol, market | Skor bawaan provider: valuasi/earnings/growth/performance/health vs peer & industri — bisa jadi pembanding skor sendiri |
| `GET /api/stocks/v2/earnings` | DataSectors | symbol, market | Histori & forecast EPS/revenue, earnings surprise |
| `GET /analysis/financial-statement/{code}` | Invezgo | code, statement(BS/IS/CF), type(FY/Q1-Q4), limit | **Raw line-item per akun** — nama akun spesifik, level, parent_id, histori kuartal/tahun. Cukup granular untuk Altman Z/Beneish M/Piotroski F/Graham Number |
| `GET /analysis/financial-statement-chart/{code}` | Invezgo | code | Chart tren laporan keuangan |
| `GET /analysis/keystat/{code}` | Invezgo | code | Key statistics |
| `GET /analysis/keystat-chart/{code}` | Invezgo | code | Chart key statistics |

### 1.3 Kalender & Aksi Korporasi
| Endpoint | Vendor |
|---|---|
| `GET /api/stocks/earnings/events`, `/api/stocks/earnings/details/:ticker` | DataSectors |
| `GET /api/stocks/dividends/events`, `/api/stocks/dividends/details/:ticker` | DataSectors |
| `GET /api/stocks/ipo/events` | DataSectors |
| `GET /api/stocks/splits/events`, `/api/stocks/splits/details/:ticker` | DataSectors |
| `GET /analysis/calendar` | Invezgo — kalender aksi korporasi |

### 1.4 Kepemilikan Saham (Shareholder)
| Endpoint | Vendor | Isi |
|---|---|---|
| `GET /analysis/shareholder/{code}` | Invezgo | Komposisi kepemilikan dengan badge (Pengendali/Komisaris/Direksi) |
| `GET /analysis/shareholder/ksei/{code}` | Invezgo | Data resmi KSEI |
| `GET /analysis/shareholder/classification/{code}` | Invezgo | Klasifikasi lengkap |
| `GET /analysis/shareholder/classify-table/{code}` | Invezgo | Tabel klasifikasi |
| `GET /analysis/shareholder/number/{code}` | Invezgo | Jumlah pemegang saham |
| `GET /analysis/shareholder/high` | Invezgo | Konsentrasi kepemilikan tinggi |
| `GET /analysis/shareholder/relation` | Invezgo | Relasi antar pemegang saham |
| `GET /analysis/shareholder-detail/{code}` | Invezgo | Shareholder ≥5% |
| `GET /analysis/shareholder-detail-one` | Invezgo | Shareholder ≥1% |
| `GET /analysis/shareholder-above`, `-chart/{code}` | Invezgo | Insider KSEI |
| `GET /analysis/shareholder-one`, `-chart/{code}` | Invezgo | Insider ≥1% |
| `GET /analysis/shareholder-insider` | Invezgo | Insider IDX |

> Data kepemilikan ini granular sekali — bisa jadi nilai tambah signifikan untuk fase 1, bukan cuma pelengkap.

### 1.5 Lain-lain Pendukung Fundamental
| Endpoint | Vendor | Isi |
|---|---|---|
| `GET /analysis/price-seasonality/{code}` | Invezgo | Pola musiman harga historis — bisa jadi nilai jual unik |
| `GET /api/news`, `/api/news/type`, `/api/news/search` | DataSectors | Berita — **tidak ada skor sentimen bawaan**, kalau mau dipakai di fase 1 perlu diklasifikasi manual/AI |

### 1.6 Formula yang Bisa Dibangun (Bank Rumus Fundamental — 86 formula, dari histori sebelumnya)
Karena `analysis/financial-statement/{code}` Invezgo memberi raw line-item, formula lanjutan berikut **dipastikan bisa dibangun** (sebelumnya berstatus "perlu verifikasi"):
- **Altman Z-Score** (prediksi risiko kebangkrutan)
- **Beneish M-Score** (deteksi manipulasi laporan keuangan)
- **Piotroski F-Score** (kualitas fundamental 9 kriteria)
- **Graham Number** (valuasi ala Benjamin Graham)

Plus seluruh rasio standar (ROE, ROA, DER, PER, PBV, dst.) dari `key-ratios` dan `equities`.

---

## 2. COMING SOON — 5 Engine Lain

### 2.1 Technical Analysis
| Sumber | Isi |
|---|---|
| DataSectors — 48 endpoint indikator | Moving Average (8: SMA/EMA/DEMA/TEMA/WMA/HMA/VWMA/SMMA), Momentum (8: RSI/MACD/Stochastic/CCI/ROC/Williams%R/AO/Stoch-RSI), Trend (5: ADX/Aroon/Supertrend/PSAR/Vortex), Volatilitas (9: ATR/Bollinger×3/Keltner/Donchian/StdDev/Historical Vol/Chaikin Vol), Volume (9: OBV/MFI/CMF/AD/VWAP/EOM/PVT/Net Volume/Vol Oscillator), Lainnya (6: Ichimoku/ZigZag/Alligator/Fisher/Fractal/Price Channel) |
| DataSectors — Chart | `/api/chart-saham/*`, `/api/chart/*`, `/api/chart/v2/*` (OHLCV mentah, dipakai untuk hitung indikator sendiri, bukan panggil tiap endpoint) |
| Invezgo — Chart realtime | `analysis/chart/stock/{code}`, `/chart/index/{code}`, `/chart/multi-time/{code}`, `/intraday/{code}`, `/intraday-data/{code}`, `/running-trade/{code}`, `/order-book/{code}`, `/queue/{code}`, `/time-table/{code}`, `/price-table/{code}`, `/price-diary/{code}` |

### 2.2 Smart Money / Bandarmology
| Sumber | Isi |
|---|---|
| DataSectors | `stocks/investors/list`, `/portfolio`, `/trade-activity`; `stocks/smartmoney/guru-consensus`, `/guru-performance`, `/institutional-flow` |
| Invezgo | `analysis/summary/stock/{code}`, `/summary/broker/{code}`, `/summary-chart/stock/{code}`, `/summary-chart/broker/{code}` (broker summary); `analysis/inventory-chart/stock/{code}`, `/broker/{code}`, `/intraday-inventory-chart/{code}` (posisi kumulatif); `analysis/stalker/broker/{broker}/{stock}`, `/stalker/list/{code}`, `/stalker/sector` (tracking broker/sektor); `analysis/sankey-chart/{code}` (visualisasi arus dana — berpotensi nilai jual unik); `analysis/momentum-chart/{code}` |

### 2.3 Money Flow
| Sumber | Isi |
|---|---|
| Invezgo | `analysis/top/foreign` (top foreign flow), `analysis/top/accumulation` (top BDM flow), `analysis/top/ritel` (top ritel flow), `analysis/top/change` (top gainer/loser), `analysis/sector/rotation` (sector rotation) |

> Catatan: kategori ini overlap dengan Smart Money — bank rumus sebelumnya mencatatnya sebagai cross-reference, bukan endpoint terpisah baru sepenuhnya.

### 2.4 Risk Engine
Tidak ada endpoint vendor khusus — seluruhnya dihitung lokal dari histori harga (volatilitas, beta vs IHSG, max drawdown, Value at Risk, dst), sama pola dengan pendekatan Technical (fetch OHLCV sekali, hitung sendiri).

### 2.5 AI Strategy / AI Engine
Layer sintesis di atas 5 engine lain (Fundamental + Technical + Smart Money + Money Flow + Risk) menggunakan AI provider (Groq/Gemini, sesuai keputusan sebelumnya) — bukan dari endpoint vendor data.

---

## 3. Fitur Lain di Invezgo (di luar 6 Engine, untuk referensi ke depan)
Tidak termasuk fundamental maupun 5 Coming Soon di atas, tapi tersedia kalau nanti mau dikembangkan:
- **Screener** (`POST /screener/screen`, simpan/kelola preset) — Invezgo sudah expose sebagai API jadi, bisa dipertimbangkan dipakai langsung
- **Watchlist & Portfolio** (`/watchlists/*`, `/portfolios/*`)
- **Journal transaksi** (`/journals/*`, `/trades/*`)
- **Alert** (`/alerts/*`)
- **Forum/Post** (`/posts/*`) — mirip fitur forum diskusi yang pernah direncanakan di stockvision.id
- **Crypto** (DataSectors `/api/crypto/*`) — di luar fokus IHSG, diabaikan

---

## 4. Kesimpulan untuk Fase 1

**Yang dibangun penuh (Fundamental):** identitas saham, laporan keuangan detail (termasuk formula lanjutan Altman Z/Beneish M/Piotroski F/Graham Number), rasio kunci, insight vs peer, kalender aksi korporasi, kepemilikan saham lengkap, price seasonality.

**Yang tampil "Coming Soon" di sidebar:** Technical Analysis, Smart Money/Bandarmology, Money Flow, Risk Engine, AI Strategy — kelimanya punya endpoint/data pendukung yang sudah terpetakan jelas, tinggal menunggu giliran dibangun sesuai roadmap.
