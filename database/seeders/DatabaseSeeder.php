<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Faker\Factory as Faker;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $faker = Faker::create();

        $path = storage_path('app/users.csv');

        $file = fopen($path, 'w');

        for ($i = 0; $i < 1000000; $i++) {

            fputcsv($file, [
                $faker->name(),
                Str::uuid() . '@mail.com',
                now(),
                bcrypt('password'),
                Str::random(10),
                now(),
                now(),
            ]);
        }

        fclose($file);
    }
}
