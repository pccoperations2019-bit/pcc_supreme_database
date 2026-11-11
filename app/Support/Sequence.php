<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class Sequence
{
    // Atomic increment per key using MySQL's LAST_INSERT_ID trick
    public static function next(string $key): int
    {
        return DB::transaction(function () use ($key) {
            DB::table('sequences')->insertOrIgnore([
                'key' => $key,
                'last_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('sequences')
                ->where('key', $key)
                ->lockForUpdate()
                ->increment('last_value', 1, ['updated_at' => now()]);

            return (int) DB::table('sequences')
                ->where('key', $key)
                ->value('last_value');
        });
    }

    public static function ensureAtLeast(string $key, int $minLastValue): void
    {
        DB::transaction(function () use ($key, $minLastValue) {
            DB::table('sequences')->insertOrIgnore([
                'key' => $key,
                'last_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $current = (int) DB::table('sequences')
                ->where('key', $key)
                ->lockForUpdate()
                ->value('last_value');

            if ($current < $minLastValue) {
                DB::table('sequences')
                    ->where('key', $key)
                    ->update([
                        'last_value' => $minLastValue,
                        'updated_at' => now(),
                    ]);
            }
        });
    }
}
