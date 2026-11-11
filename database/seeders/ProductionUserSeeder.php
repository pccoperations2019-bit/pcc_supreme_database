<?php

namespace Database\Seeders;

use App\Enums\UserRoleEnum;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductionUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $agency = Agency::where('code', ProductionSeeder::AGENCY_CODE)->first();
        $center = $agency->centers()->where('code', ProductionSeeder::CENTER_CODE)->first();

        $password = 'y#C*KPX#zYgF';

        $user = User::updateOrCreate([
            'email' => ProductionSeeder::AGENCY_USER01,
        ], [
            'username' => 'pcc_admin',
            'password' => bcrypt($password),
            'name' => 'PCC Administrator',
        ])->assignRole(UserRoleEnum::AGENCY_ADMIN->value);

        $this->command->info('Created agency user: '.ProductionSeeder::AGENCY_USER01." | Username: pcc_admin | Password: {$password}");

        $user->agency()->associate($agency);
        $user->center()->associate($center);
        $user->save();
    }
}
