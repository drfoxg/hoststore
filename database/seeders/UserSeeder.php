<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => config('app.admin_name'),
            'email' => config('app.admin_email'),
            'email_verified_at' => Carbon::now(),
            'is_admin' => true,
            'password' => Hash::make(config('app.admin_password')),
        ]);

    }
}
