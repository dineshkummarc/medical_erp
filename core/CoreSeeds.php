<?php
/* ---------------------------------------------------------------------------
 * core/CoreSeeds.php — One-call, idempotent seeding of the default taxonomy.
 *
 * Why not a shared/global table: medicines.category_id and
 * medicines.manufacturer_id are foreign keys into THIS tenant's tables, so the
 * rows have to live locally. Seeding per tenant keeps renames, deletes and
 * bulk-import growth fully tenant-owned while giving every new store a
 * ready-made dropdown list from day one.
 *
 * Idempotent: names already present are skipped by lookup (works with or
 * without UNIQUE indexes, so running it twice is always safe).
 *
 * Usage:
 *   require __DIR__ . '/CoreSeeds.php';
 *   CoreSeeds::run(); // after tenant provisioning / from api/v1/seed-defaults.php
 * ------------------------------------------------------------------------- */

require_once __DIR__ . '/models/Category.php';
require_once __DIR__ . '/models/Manufacturer.php';

class CoreSeeds
{
    /** Load (and cache) the taxonomy seed file. */
    public static function taxonomy(): array
    {
        static $taxonomy = null;
        if ($taxonomy === null) {
            $taxonomy = require __DIR__ . '/seeds/taxonomy.php';
        }
        return $taxonomy;
    }

    /**
     * Seed both tables for the current tenant.
     * @return array{categories:array{inserted:int,skipped:int},manufacturers:array{inserted:int,skipped:int}}
     */
    public static function run(): array
    {
        $t = self::taxonomy();
        return [
            'categories'    => self::seedCategories($t['categories'] ?? []),
            'manufacturers' => self::seedManufacturers($t['manufacturers'] ?? []),
        ];
    }

    private static function seedCategories(array $names): array
    {
        $inserted = 0; $skipped = 0;
        foreach (self::clean($names) as $name) {
            try {
                if (Category::first('name', '=', $name)) { $skipped++; continue; }
                Category::create(['name' => $name]);
                $inserted++;
            } catch (\Throwable $e) {
                $skipped++; // one bad row never blocks the rest
            }
        }
        return ['inserted' => $inserted, 'skipped' => $skipped];
    }

    private static function seedManufacturers(array $names): array
    {
        $inserted = 0; $skipped = 0;
        foreach (self::clean($names) as $name) {
            try {
                if (Manufacturer::first('name', '=', $name)) { $skipped++; continue; }
                Manufacturer::create(['name' => $name, 'status' => 'Active']);
                $inserted++;
            } catch (\PDOException $e) {
                // Older tenant schema (before the contact/status migration): retry name-only.
                try {
                    if (Manufacturer::first('name', '=', $name)) { $skipped++; continue; }
                    Manufacturer::create(['name' => $name]);
                    $inserted++;
                } catch (\Throwable $e2) {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $skipped++;
            }
        }
        return ['inserted' => $inserted, 'skipped' => $skipped];
    }

    /** Trim blanks and de-duplicate case-insensitively, preserving first spelling. */
    private static function clean(array $names): array
    {
        $out = []; $seen = [];
        foreach ($names as $n) {
            $n = trim((string) $n);
            if ($n === '') { continue; }
            $key = strtolower($n); // list is ASCII
            if (isset($seen[$key])) { continue; }
            $seen[$key] = true;
            $out[] = $n;
        }
        return $out;
    }
}
