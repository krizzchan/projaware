<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetUserRole extends Command
{
    protected $signature = 'operations:set-role {email} {role : admin or staff}';

    protected $description = 'Assign an operational role to an existing account';

    public function handle(): int
    {
        if (! in_array($this->argument('role'), ['admin', 'staff'], true)) {
            $this->error('Role must be admin or staff.');

            return self::FAILURE;
        }
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('No existing account matches that email.');

            return self::FAILURE;
        }

        return DB::transaction(function () use ($user) {
            $admins = User::where('role', 'admin')->where('is_active', true)->lockForUpdate()->get();
            if ($user->role === 'admin' && $user->is_active && $admins->count() === 1 && $this->argument('role') !== 'admin') {
                $this->error('The last active administrator cannot be demoted.');

                return self::FAILURE;
            }
            $user->forceFill(['role' => $this->argument('role'), 'is_active' => true])->save();
            AuditLog::record('user.role_assigned_cli', 'user', $user->id, ['role' => $user->role]);
            $this->info('Role updated successfully.');

            return self::SUCCESS;
        });
    }
}
