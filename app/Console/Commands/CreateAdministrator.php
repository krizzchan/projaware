<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdministrator extends Command
{
    protected $signature = 'operations:create-admin';

    protected $description = 'Privately provision the first administrator when none exists';

    public function handle(): int
    {
        if (User::where('role', 'admin')->where('is_active', true)->exists()) {
            $this->error('An administrator already exists. Use Account Access to manage additional accounts.');

            return self::FAILURE;
        }
        $data = [
            'name' => $this->ask('Administrator name'),
            'email' => $this->ask('Administrator email'),
            'password' => $this->secret('Password (12+ characters, mixed case, number, symbol)'),
            'password_confirmation' => $this->secret('Confirm password'),
        ];
        $validator = Validator::make($data, ['name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'lowercase', 'max:255', 'unique:users,email'], 'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()]]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        DB::transaction(function () use ($data) {
            $user = User::create(collect($data)->only(['name', 'email', 'password'])->all());
            $user->forceFill(['role' => 'admin', 'is_active' => true])->save();
            AuditLog::record('user.initial_admin_created_cli', 'user', $user->id);
        });
        $this->info('Administrator created. Sign in and open Account Access to manage staff.');

        return self::SUCCESS;
    }
}
