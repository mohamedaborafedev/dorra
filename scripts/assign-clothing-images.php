<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = DB::connection();
if (! $app->environment('local') || $db->getDriverName() !== 'mysql') {
    throw new RuntimeException('Local MySQL only.');
}
for ($i = 1; $i <= 10; $i++) {
    $path = public_path(sprintf('clothing/clothing-%02d.jpg', $i));
    $size = getimagesize($path);
    if (! $size || $size[0] !== 480 || $size[1] !== 320 || $size['mime'] !== 'image/jpeg') {
        throw new RuntimeException('Invalid clothing image: '.$path);
    }
}
$directory = storage_path('app/private/backups');
if (! is_dir($directory)) {
    mkdir($directory, 0755, true);
}
$backup = $directory.'/design-thumbnails-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.jsonl';
$file = fopen($backup, 'x');
if (! $file) {
    throw new RuntimeException('Unable to create thumbnail backup.');
}
$db->transaction(function () use ($db, $file) {
    // Only thumbnail changes. IDs, titles, statuses and timestamps stay intact.
    foreach ($db->cursor('SELECT id, thumbnail FROM designs ORDER BY id') as $row) {
        if (fwrite($file, json_encode($row, JSON_THROW_ON_ERROR)."\n") === false) {
            throw new RuntimeException('Unable to write thumbnail backup.');
        }
    }
    fflush($file);
    $db->update("UPDATE designs SET thumbnail = CONCAT('clothing/clothing-', LPAD(MOD(CRC32(CONCAT('dorra-clothing-20261028-', id)), 10) + 1, 2, '0'), '.jpg')");
});
fclose($file);
echo 'Previous thumbnail paths saved privately: '.$backup.PHP_EOL;
$distribution = $db->select('SELECT thumbnail, COUNT(*) AS total FROM designs GROUP BY thumbnail ORDER BY thumbnail');
file_put_contents(__DIR__.'/../docs/clothing-distribution.json', json_encode(['seed' => 'dorra-clothing-20261028-', 'method' => 'CRC32(seed + id) modulo 10; deterministic pseudorandom assignment', 'distribution' => $distribution], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($distribution, JSON_PRETTY_PRINT).PHP_EOL;
