-- File: seed_formula_config.sql
-- Jalankan sekali di phpMyAdmin (tab SQL, database aigen_db) supaya fundamental_score
-- bisa terhitung. Nilai threshold ini contoh awal — semuanya bisa diubah nanti dari
-- panel admin (menu Manajemen Formula Fundamental) tanpa perlu edit kode.
--
-- Cara baca: threshold_good = nilai yang dianggap skor 100, threshold_bad = skor 0.
-- Untuk DER/PER (makin kecil makin bagus), threshold_good < threshold_bad.

INSERT INTO formula_config (formula_key, formula_name, category, weight, threshold_good, threshold_bad, is_active) VALUES
('roe', 'Return on Equity', 'fundamental', 2.00, 25, 0, 1),
('roa', 'Return on Assets', 'fundamental', 1.00, 10, 0, 1),
('der', 'Debt to Equity Ratio', 'fundamental', 1.50, 0.5, 3, 1),
('per', 'Price to Earnings Ratio', 'fundamental', 1.00, 8, 30, 1),
('pbv', 'Price to Book Value', 'fundamental', 1.00, 1, 5, 1);
