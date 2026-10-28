<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// Read-only local benchmark. No schema changes, cache flushing, or data writes.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$options = getopt('', ['runs:', 'warmup:', 'output:']);
$runs = (int) ($options['runs'] ?? 20);
$warmup = (int) ($options['warmup'] ?? 3);
if ($runs < 5 || $runs > 100 || $warmup < 1 || $warmup > 20) {
    throw new RuntimeException('Use 5–100 runs and 1–20 warmups.');
}
$db = DB::connection();
if (! $app->environment('local') || $db->getDriverName() !== 'mysql') {
    throw new RuntimeException('This benchmark requires local MySQL.');
}
$db->disableQueryLog();
$indexes = ['idx_designs_category_status_created', 'idx_designs_title', 'idx_designs_created_id'];
$actual = $db->select('SHOW INDEX FROM designs');
foreach ($indexes as $name) {
    if (! in_array($name, array_column($actual, 'Key_name'), true)) {
        throw new RuntimeException('Missing benchmark index: '.$name);
    }
}
$ignore = ' IGNORE INDEX ('.implode(', ', $indexes).')';
$cases = [
    'newest' => ['', [], 0],
    'category_status' => ['category_id = ? AND status = ?', [3, 'published'], 0],
    'category_only' => ['category_id = ?', [3], 0],
    'status_only' => ['status = ?', ['published'], 0],
    'english_prefix' => ["title LIKE ? ESCAPE '!'", ['Modern%'], 0],
    'arabic_prefix' => ["title LIKE ? ESCAPE '!'", ['تصميم%'], 0],
    'combined' => ["title LIKE ? ESCAPE '!' AND category_id = ? AND status = ?", ['Modern%', 3, 'published'], 0],
    'rare_prefix' => ["title LIKE ? ESCAPE '!'", ['Modern Business Card 9999%'], 0],
    'absent_prefix' => ["title LIKE ? ESCAPE '!'", ['NoSuchDesignPrefix%'], 0],
    'deep_offset' => ['', [], 96000],
];
$snapshot = function () use ($db): array {
    $hash = hash_init('sha256');
    $count = 0;
    foreach ($db->cursor('SELECT * FROM designs ORDER BY id') as $row) {
        hash_update($hash, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
        $count++;
    }

    return ['rows' => $count, 'sha256' => hash_final($hash)];
};
$stats = function (array $values): array {
    sort($values);
    $n = count($values);

    return ['min_ms' => $values[0], 'median_ms' => ($values[(int) floor(($n - 1) / 2)] + $values[(int) floor($n / 2)]) / 2,
        'p95_ms' => $values[(int) ceil($n * 0.95) - 1], 'max_ms' => $values[$n - 1]];
};
$report = [
    'started_at_utc' => gmdate('c'), 'php' => PHP_VERSION, 'laravel' => $app->version(),
    'os' => PHP_OS_FAMILY, 'client' => 'Laravel PDO, hrtime around SELECT plus fetchAll, local TCP; no HTTP/render/images',
    'server' => $db->selectOne('SELECT VERSION() AS version, @@version_compile_os AS os, @@version_compile_machine AS architecture, @@innodb_buffer_pool_size AS buffer_pool_bytes, @@transaction_isolation AS isolation, @@session.time_zone AS timezone, @@collation_connection AS collation'),
    'runs_per_variant' => $runs, 'warmups_per_variant' => $warmup,
    'method' => 'Same read-only repeatable-read snapshot; alternating baseline/indexed order per round. Baseline ignores only the three candidate secondary indexes; primary index retained. Not a physical drop/rebuild comparison.',
    'cache' => 'Warm-buffer experiment: full dataset fingerprint scan and explicit warmups. No cache flush/restart, no OS cache control, no cold-cache claim. EXPLAIN ANALYZE is outside timed samples. MySQL 8 has no query result cache. Other local workloads are not isolated.',
    'indexes' => $actual,
    'table' => $db->selectOne("SELECT ENGINE, TABLE_COLLATION, DATA_LENGTH, INDEX_LENGTH FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'designs'"),
];
$cacheCounters = fn () => $db->select("SHOW GLOBAL STATUS WHERE Variable_name IN ('Innodb_buffer_pool_reads', 'Innodb_buffer_pool_read_requests')");
$db->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
$db->beginTransaction();
try {
    $report['dataset_before'] = $snapshot();
    if ($report['dataset_before']['rows'] !== 100000) {
        throw new RuntimeException('Expected exactly 100000 designs.');
    }
    $report['cache_counters_before'] = $cacheCounters();
    foreach ($cases as $name => [$condition, $bindings, $offset]) {
        foreach (['listing', 'count'] as $kind) {
            if ($name === 'deep_offset' && $kind === 'count') {
                continue;
            }
            $where = $condition === '' ? '' : ' WHERE '.$condition;
            $tail = $kind === 'listing' ? ' ORDER BY created_at DESC, id DESC LIMIT 24 OFFSET '.$offset : '';
            $select = $kind === 'listing' ? 'SELECT *' : 'SELECT COUNT(*) AS aggregate';
            $variant = [];
            foreach (['baseline' => $ignore, 'indexed' => ''] as $mode => $hint) {
                $sql = $select.' FROM designs'.$hint.$where.$tail;
                $rows = $db->select($sql, $bindings);
                $variant[$mode] = ['sql' => $sql, 'bindings' => $bindings, 'result_sha256' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)),
                    'rows_returned' => count($rows), 'matching_count' => $kind === 'count' ? $rows[0]->aggregate : null,
                    'explain' => $db->select('EXPLAIN '.$sql, $bindings),
                    'explain_analyze' => $db->select('EXPLAIN ANALYZE '.$sql, $bindings), 'samples_ms' => []];
            }
            if ($variant['baseline']['result_sha256'] !== $variant['indexed']['result_sha256']) {
                throw new RuntimeException('Different results for '.$name.' '.$kind);
            }
            for ($round = -$warmup; $round < $runs; $round++) {
                $order = $round % 2 === 0 ? ['baseline', 'indexed'] : ['indexed', 'baseline'];
                foreach ($order as $mode) {
                    $start = hrtime(true);
                    $rows = $db->select($variant[$mode]['sql'], $bindings);
                    $elapsed = (hrtime(true) - $start) / 1e6;
                    if ($round >= 0) {
                        $variant[$mode]['samples_ms'][] = $elapsed;
                    }
                }
            }
            foreach (['baseline', 'indexed'] as $mode) {
                $variant[$mode]['stats'] = $stats($variant[$mode]['samples_ms']);
            }
            $report['results'][$name.'_'.$kind] = $variant + ['median_speedup' => $variant['baseline']['stats']['median_ms'] / $variant['indexed']['stats']['median_ms']];
            echo $name.' '.$kind.' completed'.PHP_EOL;
        }
    }
    $report['cache_counters_after'] = $cacheCounters();
    $report['dataset_after'] = $snapshot();
    $report['dataset_unchanged'] = $report['dataset_before'] === $report['dataset_after'];
} finally {
    $db->rollBack();
}
$report['finished_at_utc'] = gmdate('c');
$output = $options['output'] ?? __DIR__.'/../docs/performance-results.json';
file_put_contents($output, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
echo 'Saved '.$output.PHP_EOL;
