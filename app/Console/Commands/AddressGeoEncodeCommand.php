<?php

namespace App\Console\Commands;

use App\Models\Address;
use App\Models\Center;
use Illuminate\Console\Command;

class AddressGeoEncodeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:address-geoencode';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'get the geo location from the address';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $addressQuery = Address::query()
            ->where('addressable_type', class_basename(Center::class))
            ->where(function ($query) {
                $query->whereNull('latitude')
                    ->orWhereNull('longitude');
            });
        $addressNeedGeoEncode = $addressQuery->count();

        $this->info("Found {$addressNeedGeoEncode} addresses need geocode.");

        if ($addressNeedGeoEncode === 0) {
            $this->info('No addresses need geocode. Exiting.');

            return;
        }

        if (! $this->confirm('Do you wish to continue?')) {
            $this->info('Operation cancelled. Exiting.');

            return;
        }

        $addressQuery
            ->chunk(100, function ($addresses) {
                foreach ($addresses as $address) {
                    $geoCode = $address->geocode()->first()->getCoordinates();
                    $address->latitude = $geoCode->getLatitude();
                    $address->longitude = $geoCode->getLongitude();
                    $address->save();
                    $this->info("Geocoded address ID [{$address->id}]{$address->fulladdress}: ({$address->latitude}, {$address->longitude})");
                }
            });
    }
}
