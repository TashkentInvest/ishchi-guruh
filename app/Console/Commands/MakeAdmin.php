<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class MakeAdmin extends Command
{
    protected $signature   = 'make:admin
                                {--name=     : Full name}
                                {--email=    : Email address}
                                {--password= : Password (min 6 chars)}';

    protected $description = 'Create or promote a user to admin role';

    public function handle(): int
    {
        $name     = $this->option('name')     ?: $this->ask('Full name');
        $email    = $this->option('email')    ?: $this->ask('Email address');
        $password = $this->option('password') ?: $this->secret('Password (min 6 chars)');

        if (strlen($password) < 6) {
            $this->error('Password must be at least 6 characters.');
            return 1;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name'     => $name,
                'password' => Hash::make($password),
                'role'     => 'admin',
                'pinfl'    => null,
            ]
        );

        $action = $user->wasRecentlyCreated ? 'Created' : 'Updated';
        $this->info("{$action} admin user: [{$user->id}] {$user->name} <{$user->email}>");

        return 0;
    }
}
