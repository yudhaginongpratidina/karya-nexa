<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{

    private function generateEmail(string $name): string
    {
        $base = strtolower(str_replace(' ', '-', $name));
        $email = $base . '@gmail.com';

        $counter = 1;

        while (User::where('email', $email)->exists()) {
            $email = $base . $counter . '@gmail.com';
            $counter++;
        }

        return $email;
    }

    public function run(): void
    {
        $users = [
            [
                'name' => 'Super Admin',
                'role' => 'admin'
            ],
            [
                'name' => 'User One',
                'role' => 'user'
            ],
            [
                'name' => 'User Two',
                'role' => 'user'
            ],
            [
                'name' => 'User Three',
                'role' => 'user'
            ],
        ];

        foreach ($users as $data) {
            $email = $this->generateEmail($data['name']);

            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('12345678'),
                    'role' => $data['role'],
                ]
            );
        }
    }
}
