<?php

declare(strict_types=1);

namespace Aigen\Tests;

use Aigen\Core\Database;
use PDO;

/**
 * Menerjemahkan database/schema.sql (MariaDB) menjadi SQLite in-memory.
 *
 * Tujuannya supaya test suite menguji SKEMA YANG SEBENARNYA dipakai produksi,
 * bukan skema tiruan yang bisa menyimpang diam-diam. Setiap perubahan kolom di
 * schema.sql otomatis ikut teruji.
 */
final class TestSchema
{
    public static function bootSqlite(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');

        Database::configure(['driver' => 'sqlite', 'path' => ':memory:']);
        Database::setConnection($pdo);

        foreach (self::statements(self::schemaPath()) as $sql) {
            $pdo->exec($sql);
        }

        return $pdo;
    }

    public static function loadSeed(PDO $pdo): void
    {
        foreach (self::statements(self::seedPath()) as $sql) {
            $pdo->exec($sql);
        }
    }

    private static function schemaPath(): string
    {
        return dirname(__DIR__) . '/database/schema.sql';
    }

    private static function seedPath(): string
    {
        return dirname(__DIR__) . '/database/seed.sql';
    }

    /** @return array<int,string> Pernyataan SQL yang sudah diterjemahkan ke dialek SQLite. */
    private static function statements(string $path): array
    {
        $sql = (string) file_get_contents($path);
        $sql = self::translate($sql);

        $out = [];
        foreach (self::splitStatements($sql) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || self::shouldSkip($stmt)) {
                continue;
            }

            // ALTER TABLE tidak sepenuhnya didukung SQLite: PRIMARY KEY sudah
            // ditanam inline saat CREATE TABLE, dan UNIQUE KEY diterjemahkan
            // menjadi CREATE UNIQUE INDEX terpisah.
            // SQLite hanya menerima satu tabel per DROP.
            if (stripos($stmt, 'DROP TABLE') === 0) {
                preg_match_all('/`(\w+)`/', $stmt, $tables);
                foreach ($tables[1] as $table) {
                    $out[] = "DROP TABLE IF EXISTS `{$table}`";
                }
                continue;
            }

            if (stripos($stmt, 'ALTER TABLE') === 0) {
                foreach (self::rewriteAlter($stmt) as $rewritten) {
                    $out[] = $rewritten;
                }
                continue;
            }

            $out[] = $stmt;
        }

        return $out;
    }

    /** @return array<int,string> */
    private static function rewriteAlter(string $stmt): array
    {
        if (!preg_match('/ALTER TABLE\s+`(\w+)`/i', $stmt, $m)) {
            return [];
        }
        $table = $m[1];
        $out = [];

        // Hanya UNIQUE KEY yang perlu dibuat ulang; index biasa & FK dilewati
        // karena tidak mempengaruhi kebenaran logika yang diuji.
        if (preg_match_all('/ADD\s+UNIQUE\s+KEY\s+`(\w+)`\s*\(([^)]*)\)/i', $stmt, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $indexName = $match[1];
                $columns = $match[2];
                $out[] = "CREATE UNIQUE INDEX IF NOT EXISTS `{$indexName}` ON `{$table}` ({$columns})";
            }
        }

        return $out;
    }

    private static function shouldSkip(string $stmt): bool
    {
        $upper = strtoupper($stmt);

        foreach (['SET ', 'USE ', 'CREATE DATABASE', 'COMMIT', 'START TRANSACTION'] as $prefix) {
            if (str_starts_with($upper, $prefix)) {
                return true;
            }
        }

        // AUTO_INCREMENT & indeks non-unik tidak relevan untuk test SQLite.
        if (str_starts_with($upper, 'ALTER TABLE') && str_contains($upper, 'MODIFY')) {
            return true;
        }

        return false;
    }

    private static function translate(string $sql): string
    {
        // Buang komentar baris
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;

        // Tipe & atribut khas MySQL -> SQLite
        $replacements = [
            '/\bENGINE=\w+[^;]*/i'                       => '',
            '/\bAUTO_INCREMENT=\d+/i'                    => '',
            '/\bCHARACTER SET \w+/i'                     => '',
            '/\bCOLLATE \w+/i'                           => '',
            '/\bunsigned\b/i'                            => '',
            '/\bCOMMENT\s+\'(?:[^\'\\\\]|\\\\.)*\'/i'    => '',
            '/\bbigint\(\d+\)/i'                         => 'INTEGER',
            '/\b(?:tiny|small|medium)?int\(\d+\)/i'      => 'INTEGER',
            '/\bdecimal\(\d+,\d+\)/i'                    => 'REAL',
            '/\bdouble\b/i'                              => 'REAL',
            '/\bvarchar\(\d+\)/i'                        => 'TEXT',
            '/\blongtext\b|\bmediumtext\b|\btinytext\b/i'=> 'TEXT',
            '/\bdatetime\b/i'                            => 'TEXT',
            '/\bcurrent_timestamp\(\)/i'                 => 'CURRENT_TIMESTAMP',
            '/\s+ON UPDATE CURRENT_TIMESTAMP/i'          => '',
            '/\bCHECK \(json_valid\(`\w+`\)\)/i'         => '',
        ];

        foreach ($replacements as $pattern => $replacement) {
            $sql = preg_replace($pattern, $replacement, $sql) ?? $sql;
        }

        // enum('a','b') -> TEXT
        $sql = preg_replace("/\benum\([^)]*\)/i", 'TEXT', $sql) ?? $sql;

        // Dump MariaDB memasang PRIMARY KEY & AUTO_INCREMENT lewat ALTER TABLE,
        // yang tidak didukung SQLite. Tanam langsung di kolom `id`.
        $sql = preg_replace(
            '/^(\s*`id`\s+)INTEGER\s+NOT NULL\s*,/mi',
            '$1INTEGER PRIMARY KEY AUTOINCREMENT,',
            $sql
        ) ?? $sql;

        // Bersihkan koma menggantung akibat penghapusan CHECK(json_valid(...)).
        $sql = preg_replace('/,\s*(\n\s*\))/', '$1', $sql) ?? $sql;

        // Upsert MariaDB -> upsert SQLite:
        //   ON DUPLICATE KEY UPDATE c = VALUES(c)  ->  ON CONFLICT DO UPDATE SET c = excluded.c
        //
        // Sengaja TIDAK memakai INSERT OR REPLACE, karena itu menghapus lalu
        // menyisipkan ulang baris sehingga id berubah dan baris anak yang
        // menunjuk lewat foreign key jadi yatim. ON CONFLICT DO UPDATE
        // memperbarui di tempat, persis seperti perilaku MariaDB.
        $sql = preg_replace('/\bON DUPLICATE KEY UPDATE\b/i', 'ON CONFLICT DO UPDATE SET', $sql) ?? $sql;
        $sql = preg_replace('/\bVALUES\s*\(\s*(`\w+`)\s*\)/i', 'excluded.$1', $sql) ?? $sql;

        return $sql;
    }

    /** Pisah per titik-koma, hormati string berkutip. */
    private static function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $inString = false;
        $quote = '';
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($inString) {
                if ($char === '\\') {
                    $buffer .= $char . ($sql[$i + 1] ?? '');
                    $i++;
                    continue;
                }
                if ($char === $quote) {
                    $inString = false;
                }
                $buffer .= $char;
                continue;
            }

            if ($char === "'" || $char === '"') {
                $inString = true;
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === ';') {
                $statements[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $statements[] = $buffer;
        }

        return $statements;
    }
}
