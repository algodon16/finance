<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class ResetAdmin extends Command
{
    protected $signature = 'admin:reset
        {email? : Admin email}
        {--name=System Admin : Name for new admin}
        {--password= : New password (will prompt securely if omitted)}
        {--create : Create admin if email does not exist}';

    protected $description = 'Reset password and ensure admin role for a user. Owner must run this locally via terminal.';

    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('Admin email');

        $user = User::withTrashed()->where('email', $email)->first();

        if (! $user && ! $this->option('create')) {
            if (! $this->confirm("Walang user na [$email]. Gagawa ng bagong admin?", true)) {
                $this->error('Cancelled. Walang binago.');
                return self::FAILURE;
            }
        }

        $password = $this->option('password') ?: $this->secret('New password (min. 8 chars)');
        $passwordFromOption = (bool) $this->option('password');
        $passwordConfirm = $passwordFromOption ? $password : $this->secret('Confirm new password');

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => 'required|email|max:255', 'password' => 'required|string|min:8|max:255']
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        if ($password !== $passwordConfirm) {
            $this->error('Hindi magkatugma ang password at confirmation.');
            return self::FAILURE;
        }

        if ($user) {
            if ($user->trashed()) {
                $user->restore();
            }
            $user->forceFill([
                'password' => $password,
                'role' => 'admin',
                'is_active' => true,
            ])->save();

            $this->info("Na-reset ang admin [$email] at naka-set na role=admin, active=1.");
        } else {
            $user = User::create([
                'name' => $this->option('name') ?: 'System Admin',
                'email' => $email,
                'password' => $password,
                'role' => 'admin',
                'is_active' => true,
            ]);

            $this->info("Gumawa ng bagong admin [$email].");
        }

        return self::SUCCESS;
    }
}
