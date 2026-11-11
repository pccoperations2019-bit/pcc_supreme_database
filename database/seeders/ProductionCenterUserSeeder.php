<?php

namespace Database\Seeders;

use App\Enums\UserRoleEnum;
use App\Models\Agency;
use App\Models\Center;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductionCenterUserSeeder extends Seeder
{
    /**
     * Sample center data with codes and names
     */
    private const CENTER_SAMPLE_DATA = [
        ['code' => 'MMSU', 'name' => 'Mariano Marcos State University', 'email_prefix' => 'pcc-mmsu', 'username' => 'mmsu', 'password' => 'RcXA3HcPwo0W'],
        ['code' => 'DMMMSU', 'name' => 'Don Mariano Marcos Memorial State University', 'email_prefix' => 'pcc-dmmmsu', 'username' => 'dmmmsu', 'password' => 'JT#NStDYrYZK'],
        ['code' => 'CSU', 'name' => 'Cagayan State University', 'email_prefix' => 'pcc-csu', 'username' => 'csu', 'password' => 'vVz#K4F&xqE*'],
        ['code' => 'CLSU', 'name' => 'Central Luzon State University', 'email_prefix' => 'pcc-clsu', 'username' => 'clsu', 'password' => 'L*JHHVFnFC3F'],
        ['code' => 'UPLB', 'name' => 'University of the Philippines Los BaÃ±os', 'email_prefix' => 'pcc-uplb', 'username' => 'uplb', 'password' => 'CX2RTYquze7Y'],
        ['code' => 'WVSU', 'name' => 'West Visayas State University', 'email_prefix' => 'pcc-wvsu', 'username' => 'wvsu', 'password' => 'Kc3Wxa6T&a!H'],
        ['code' => 'LCSF', 'name' => 'La Carlota Stock Farm', 'email_prefix' => 'pcc-lcsf', 'username' => 'lcsf', 'password' => 'njMoGP4RIfHA'],
        ['code' => 'USF', 'name' => 'Ubay Stock Farm', 'email_prefix' => 'pcc-usf', 'username' => 'usf', 'password' => '1FN30Lg$vKr@'],
        ['code' => 'VSU', 'name' => 'Visayas State University', 'email_prefix' => 'pcc-vsu', 'username' => 'vsu', 'password' => 'dT$VUKyOE&X5'],
        ['code' => 'MLPC', 'name' => 'Mindanao Livestock Production Center', 'email_prefix' => 'pcc-mlpc', 'username' => 'mlpc', 'password' => 'zrgpc4CAnm1D'],
        ['code' => 'CMU', 'name' => 'Central Mindanao University', 'email_prefix' => 'pcc-cmu', 'username' => 'cmu', 'password' => 'F%%pu@sR10uZ'],
        ['code' => 'USM', 'name' => 'University of Southern Mindanao', 'email_prefix' => 'pcc-usm', 'username' => 'usm', 'password' => 'S041Yt$59Llx'],
        ['code' => 'MSU', 'name' => 'Mindanao State University', 'email_prefix' => 'pcc-msu', 'username' => 'msu', 'password' => 'IsrH#HloF6ey'],
        ['code' => 'GP', 'name' => 'Genepool', 'email_prefix' => 'pcc-gp', 'username' => 'gp', 'password' => 'hia3YGb^cw2Y'],
        ['code' => 'NIZ', 'name' => 'National Impact Zone', 'email_prefix' => 'pcc-niz', 'username' => 'niz', 'password' => 'Wr%YcGcCBT6Q'],
        ['code' => 'NHQ', 'name' => 'National Headquarters', 'email_prefix' => 'pcc-nhq', 'username' => 'nhq', 'password' => 'i3*DLugGi@!&'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $agency = Agency::where('code', ProductionSeeder::AGENCY_CODE)->first();

        if (! $agency) {
            $this->command->error('Agency not found. Please run ProductionSeeder first.');

            return;
        }

        DB::transaction(function () use ($agency) {
            foreach (self::CENTER_SAMPLE_DATA as $centerData) {
                // Create or find center
                $center = Center::firstOrCreate([
                    'code' => $centerData['code'],
                ], [
                    'agency_id' => $agency->id,
                    'name' => $centerData['name'],
                    'description' => $centerData['name'].' - Carabao Research Center',
                ]);

                // Generate email, username and password (use provided values or fallback)
                $email = $centerData['email_prefix'].'@pcc.gov.ph';
                $username = $centerData['username'] ?? strtolower($centerData['code']);
                $password = $centerData['password'] ?? $this->generateSecurePassword();

                // Create center user
                $user = User::firstOrCreate([
                    'email' => $email,
                ], [
                    'username' => $username,
                    'password' => bcrypt($password),
                    'name' => $centerData['name'].' Administrator',
                ])->assignRole(UserRoleEnum::CENTER_ADMIN->value);

                // Associate user with agency and center
                $user->agency()->associate($agency);
                $user->center()->associate($center);
                $user->save();

                $this->command->info("Created center user: {$email} | Username: {$username} | Password: {$password}");
            }
        });
    }

    /**
     * Generate a secure random password
     */
    private function generateSecurePassword(): string
    {
        $length = 12;
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $password;
    }
}
