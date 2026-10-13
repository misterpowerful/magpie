<?php

use Illuminate\Support\Facades\DB;

test('sqlite connection honors journal_mode config as WAL', function () {
    $path = tempnam(sys_get_temp_dir(), 'magpie_wal_test_');

    config(['database.connections.wal_test' => [
        'driver' => 'sqlite',
        'database' => $path,
        'prefix' => '',
        'foreign_key_constraints' => true,
        'journal_mode' => 'WAL',
    ]]);

    $result = DB::connection('wal_test')->select('PRAGMA journal_mode');

    expect(strtolower($result[0]->journal_mode))->toBe('wal');

    DB::purge('wal_test');
    @unlink($path);
});
