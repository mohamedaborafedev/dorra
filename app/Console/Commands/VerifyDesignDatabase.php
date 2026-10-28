<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VerifyDesignDatabase extends Command
{
    protected $signature = 'dorra:verify-database';

    protected $description = 'Read-only schema, sample data and MySQL query plan inspection';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->error('Run this verification against the target MySQL database.');

            return self::FAILURE;
        }
        $report = [
            'server' => DB::selectOne('SELECT VERSION() AS version, DATABASE() AS database_name, @@session.time_zone AS session_timezone'),
            'counts' => [
                'users' => DB::table('users')->count(),
                'categories' => DB::table('categories')->count(),
                'designs' => DB::table('designs')->count(),
                'arabic_titles' => DB::table('designs')->whereRaw("title REGEXP '[ء-ي]'")->count(),
                'english_titles' => DB::table('designs')->whereRaw("title REGEXP '[A-Za-z]'")->count(),
                'distinct_created_at' => DB::table('designs')->distinct()->count('created_at'),
                'orphan_designs' => DB::table('designs')->leftJoin('categories', 'categories.id', '=', 'designs.category_id')->whereNull('categories.id')->count(),
            ],
            'roles' => DB::table('users')->selectRaw('role, COUNT(*) AS total')->groupBy('role')->get()->all(),
            'statuses' => DB::table('designs')->selectRaw('status, COUNT(*) AS total')->groupBy('status')->get()->all(),
        ];
        foreach (['users', 'categories', 'designs'] as $table) {
            $report['schema'][$table] = [
                'columns' => Schema::getColumns($table),
                'indexes' => DB::select('SHOW INDEX FROM '.$table),
                'foreign_keys' => Schema::getForeignKeys($table),
                'create' => DB::selectOne('SHOW CREATE TABLE '.$table),
            ];
        }
        $queries = [
            'category_status' => "SELECT * FROM designs WHERE category_id = 3 AND status = 'published' ORDER BY created_at DESC, id DESC LIMIT 24",
            'prefix' => "SELECT * FROM designs WHERE title LIKE 'Modern%' ORDER BY created_at DESC, id DESC LIMIT 24",
            'newest' => 'SELECT * FROM designs ORDER BY created_at DESC, id DESC LIMIT 24',
            'category_only' => 'SELECT * FROM designs WHERE category_id = 3 ORDER BY created_at DESC, id DESC LIMIT 24',
            'status_only' => "SELECT * FROM designs WHERE status = 'published' ORDER BY created_at DESC, id DESC LIMIT 24",
        ];
        foreach ($queries as $name => $sql) {
            $report['explain'][$name] = ['sql' => $sql, 'plan' => DB::select('EXPLAIN '.$sql)];
        }
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
