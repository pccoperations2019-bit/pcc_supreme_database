<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ScanForDuplicates extends Command
{
    protected $signature = 'scan:duplicates {--type=farmers : Scan type: farmers}';

    protected $description = 'Scan database for potentially duplicate farmers';

    public function handle(): int
    {
        $type = $this->option('type');

        if ($type === 'farmers') {
            $this->scanFarmers();
        }

        return self::SUCCESS;
    }

    private function scanFarmers(): void
    {
        $this->newLine();
        $this->info('=== Scanning for duplicate Farmers ===');

        // 1. Check for duplicate codes
        $codeDuplicates = DB::select("
            SELECT f.id, f.code, f.first_name, f.last_name, f.center_id, f.partner_id
            FROM farmers f
            INNER JOIN (
                SELECT code
                FROM farmers
                WHERE code IS NOT NULL AND code != ''
                GROUP BY code
                HAVING COUNT(*) > 1
            ) dup ON dup.code = f.code
            ORDER BY f.code, f.id
        ");

        if (! empty($codeDuplicates)) {
            $this->warn('Found '.count($codeDuplicates).' farmer records with duplicate codes:');
            $this->table(
                ['ID', 'Code', 'First Name', 'Last Name', 'Center', 'Partner'],
                array_map(function ($row) {
                    return [
                        $row->id,
                        $row->code,
                        $row->first_name,
                        $row->last_name ?? 'N/A',
                        $row->center_id,
                        $row->partner_id,
                    ];
                }, $codeDuplicates)
            );
        } else {
            $this->info('No duplicate farmer codes found.');
        }

        // 2. Check for duplicate full names (first_name + last_name)
        $nameDuplicates = DB::select("
            SELECT
                f.id,
                f.code,
                f.first_name,
                f.last_name,
                f.middle_name,
                f.contact_number,
                f.center_id,
                f.partner_id,
                f.birthday
            FROM farmers f
            INNER JOIN (
                SELECT
                    LOWER(TRIM(first_name)) AS norm_first,
                    LOWER(TRIM(COALESCE(last_name, ''))) AS norm_last
                FROM farmers
                GROUP BY LOWER(TRIM(first_name)), LOWER(TRIM(COALESCE(last_name, '')))
                HAVING COUNT(*) > 1
            ) dup ON dup.norm_first = LOWER(TRIM(f.first_name))
                AND dup.norm_last = LOWER(TRIM(COALESCE(f.last_name, '')))
            ORDER BY LOWER(TRIM(f.first_name)), LOWER(TRIM(COALESCE(f.last_name, ''))), f.id
        ");

        if (! empty($nameDuplicates)) {
            $this->newLine();
            $this->warn('Found '.count($nameDuplicates).' farmer records with duplicate names:');
            $this->table(
                ['ID', 'Code', 'First Name', 'Last Name', 'Middle', 'Contact', 'Birthday', 'Center', 'Partner'],
                array_map(function ($row) {
                    return [
                        $row->id,
                        $row->code,
                        $row->first_name,
                        $row->last_name ?? 'N/A',
                        $row->middle_name ?? 'N/A',
                        $row->contact_number ?? 'N/A',
                        $row->birthday ?? 'N/A',
                        $row->center_id,
                        $row->partner_id,
                    ];
                }, $nameDuplicates)
            );

            // Group by normalized name for better display
            $grouped = [];
            foreach ($nameDuplicates as $row) {
                $normalizedName = strtolower(trim($row->first_name).' '.trim($row->last_name ?? ''));
                if (! isset($grouped[$normalizedName])) {
                    $grouped[$normalizedName] = [];
                }
                $grouped[$normalizedName][] = $row;
            }

            $this->newLine();
            $this->info('Duplicate groups by normalized name:');
            foreach ($grouped as $name => $records) {
                $this->warn("\nName: \"{$name}\" (Count: ".count($records).')');
                foreach ($records as $record) {
                    $this->line("  - ID {$record->id}: {$record->code} | Contact: ".($record->contact_number ?? 'N/A').' | Birthday: '.($record->birthday ?? 'N/A')." | Center: {$record->center_id} | Partner: {$record->partner_id}");
                }
            }
        } else {
            $this->info('No duplicate farmer names found.');
        }

        // 3. Check for duplicate contact numbers
        $contactDuplicates = DB::select("
            SELECT
                f.id,
                f.code,
                f.first_name,
                f.last_name,
                f.contact_number,
                f.center_id,
                f.partner_id
            FROM farmers f
            INNER JOIN (
                SELECT contact_number
                FROM farmers
                WHERE contact_number IS NOT NULL AND contact_number != ''
                GROUP BY contact_number
                HAVING COUNT(*) > 1
            ) dup ON dup.contact_number = f.contact_number
            ORDER BY f.contact_number, f.id
        ");

        if (! empty($contactDuplicates)) {
            $this->newLine();
            $this->warn('Found '.count($contactDuplicates).' farmer records with duplicate contact numbers:');
            $this->table(
                ['ID', 'Code', 'First Name', 'Last Name', 'Contact Number', 'Center', 'Partner'],
                array_map(function ($row) {
                    return [
                        $row->id,
                        $row->code,
                        $row->first_name,
                        $row->last_name ?? 'N/A',
                        $row->contact_number,
                        $row->center_id,
                        $row->partner_id,
                    ];
                }, $contactDuplicates)
            );
        } else {
            $this->info('No duplicate farmer contact numbers found.');
        }

        // 4. Check for potential fuzzy matches (same center + same first name + similar last name)
        $this->newLine();
        $this->info('Checking for potential fuzzy matches (same center, same first name, similar last name)...');

        // Note: LEVENSHTEIN may not be available in MySQL, so we'll use SOUNDEX and similarity checks
        $fuzzyMatches = DB::select('
            SELECT
                f1.id AS id1,
                f1.code AS code1,
                f1.first_name AS first_name1,
                f1.last_name AS last_name1,
                f1.contact_number AS contact1,
                f2.id AS id2,
                f2.code AS code2,
                f2.first_name AS first_name2,
                f2.last_name AS last_name2,
                f2.contact_number AS contact2,
                f1.center_id
            FROM farmers f1
            JOIN farmers f2 ON f1.center_id = f2.center_id
                AND f1.id < f2.id
                AND LOWER(TRIM(f1.first_name)) = LOWER(TRIM(f2.first_name))
                AND f1.last_name IS NOT NULL
                AND f2.last_name IS NOT NULL
                AND SOUNDEX(f1.last_name) = SOUNDEX(f2.last_name)
                AND f1.last_name != f2.last_name
            ORDER BY f1.center_id, f1.first_name, f1.last_name
        ');

        if (! empty($fuzzyMatches)) {
            $this->warn('Found '.count($fuzzyMatches).' potential fuzzy matches:');
            $this->table(
                ['ID1', 'Code1', 'Name1', 'Contact1', 'ID2', 'Code2', 'Name2', 'Contact2', 'Center'],
                array_map(function ($row) {
                    return [
                        $row->id1,
                        $row->code1,
                        trim($row->first_name1.' '.$row->last_name1),
                        $row->contact1 ?? 'N/A',
                        $row->id2,
                        $row->code2,
                        trim($row->first_name2.' '.$row->last_name2),
                        $row->contact2 ?? 'N/A',
                        $row->center_id,
                    ];
                }, $fuzzyMatches)
            );
        } else {
            $this->info('No fuzzy matches found.');
        }
    }
}
