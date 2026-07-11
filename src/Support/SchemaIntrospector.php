<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Powers the "smart" behavior in Product Bridge: instead of asking the
 * admin to type a model class path or column names from memory, this
 * discovers what's actually in their application and suggests sensible
 * mappings automatically — the admin reviews and confirms rather than
 * typing everything from a blank page.
 *
 * Three responsibilities:
 *   1. discoverModels()  — find real Eloquent models in app/Models
 *   2. getTableColumns() — introspect a model's actual table schema
 *   3. suggestSource()   — guess which synced field a given target
 *      column most likely corresponds to, using name similarity and
 *      preferring sample values that actually look populated (the same
 *      manual reasoning used earlier to figure out that a customer's
 *      real retail price lived in extra_data.price_4, not sel_price —
 *      generalized into a reusable heuristic).
 */
class SchemaIntrospector
{
    /**
     * @return array<string, string> [FQCN => short class name]
     */
    public function discoverModels(): array
    {
        $modelsPath = app_path('Models');
        if (! is_dir($modelsPath)) {
            return [];
        }

        $models = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($modelsPath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace(
                [$modelsPath.DIRECTORY_SEPARATOR, '.php'],
                '',
                $file->getPathname()
            );
            $class = 'App\\Models\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

            if (! class_exists($class)) {
                continue;
            }

            try {
                $reflection = new \ReflectionClass($class);
                if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                    continue;
                }
                $models[$class] = class_basename($class);
            } catch (\Throwable) {
                continue;
            }
        }

        return $models;
    }

    /**
     * @return array<int, array{name: string, required: bool, auto_increment: bool}>
     */
    public function getTableColumns(string $modelClass): array
    {
        if (! class_exists($modelClass)) {
            return [];
        }

        try {
            $model = new $modelClass;
            $table = $model->getTable();
            $connection = $model->getConnectionName() ?: config('database.default');

            $columns = DB::connection($connection)->select(
                'SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_NAME = ? AND TABLE_SCHEMA = DATABASE()
                 ORDER BY ORDINAL_POSITION',
                [$table]
            );
        } catch (\Throwable) {
            return [];
        }

        $skip = ['id', 'created_at', 'updated_at', 'deleted_at'];

        return collect($columns)
            ->reject(fn ($c) => in_array($c->COLUMN_NAME, $skip, true))
            ->map(fn ($c) => [
                'name' => $c->COLUMN_NAME,
                'required' => $c->IS_NULLABLE === 'NO'
                    && $c->COLUMN_DEFAULT === null
                    && ! str_contains((string) $c->EXTRA, 'auto_increment'),
                'auto_increment' => str_contains((string) $c->EXTRA, 'auto_increment'),
            ])
            ->values()
            ->all();
    }

    /**
     * Suggests the most likely source field path for a given target
     * column, using name-similarity heuristics and preferring sample
     * values that look genuinely populated over ones that look empty/
     * zero (mirrors the manual investigation that found a customer's
     * real retail price living in extra_data.price_4 rather than the
     * more obviously-named but actually-unused extra_data.sel_price).
     *
     * @param  array<string, mixed>  $sampleFields  dot-path => sample value, from getAvailablePaths()
     */
    public function suggestSource(string $columnName, array $sampleFields): ?string
    {
        $col = strtolower($columnName);
        $scored = [];

        foreach ($sampleFields as $path => $value) {
            $leaf = strtolower(str_replace('extra_data.', '', $path));
            $score = $this->similarityScore($col, $leaf);

            if ($score <= 0) {
                continue;
            }

            // Prefer fields whose sample value actually looks populated —
            // a matching name with an empty/zero sample is a weaker
            // signal than a matching name with real data.
            $looksPopulated = $value !== null && $value !== '' && $value !== 0 && $value !== '0';
            $scored[$path] = $score + ($looksPopulated ? 15 : 0);
        }

        if (empty($scored)) {
            return null;
        }

        arsort($scored);

        return array_key_first($scored);
    }

    private function similarityScore(string $column, string $field): int
    {
        if ($column === $field) {
            return 100;
        }

        // Common naming variants
        $nameLike = ['name', 'name_ar', 'title', 'product_name'];
        if (in_array($column, $nameLike, true) && $field === 'name') {
            return 90;
        }

        $qtyLike = ['quantity', 'stock', 'qty', 'inventory'];
        if (in_array($column, $qtyLike, true) && $field === 'quantity') {
            return 90;
        }

        $barcodeLike = ['barcode', 'sku', 'upc', 'ean'];
        if (in_array($column, $barcodeLike, true) && $field === 'barcode') {
            return 85;
        }

        if (str_contains($column, 'price') && str_contains($field, 'price')) {
            return 40;
        }

        if (str_contains($column, $field) || str_contains($field, $column)) {
            return 60;
        }

        return 0;
    }
}
