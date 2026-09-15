<?php

namespace Database\Seeders;

use App\Support\CountryRegistrationCatalog;
use Illuminate\Database\Seeder;

class CountryRegistrationSeeder extends Seeder
{
    /**
     * Seed the editable country registration catalog from the config file.
     *
     * The work lives in the applier so a migration can run it too — a deploy
     * runs migrations, not seeders, and without that the admin panel listed
     * nothing (report item 1).
     */
    public function run(): void
    {
        CountryRegistrationCatalog::apply();
    }
}
