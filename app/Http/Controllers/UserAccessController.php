<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserAccessController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
        $search = $filters['search'] ?? '';
        $users = User::query()->when($search !== '', fn ($query) => $query->where(fn ($match) => $match
            ->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))
            ->orderBy('name')->orderBy('id')->paginate(25, ['id', 'name', 'email', 'role', 'is_active'])->withQueryString();

        return Inertia::render('menu/users', ['users' => $users, 'filters' => ['search' => $search]]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'lowercase', 'max:255', 'unique:users,email'], 'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()], 'role' => ['required', Rule::in(['admin', 'staff'])], 'current_password' => ['required', 'current_password']]);
        DB::transaction(function () use ($data) {
            $user = User::create(collect($data)->only(['name', 'email', 'password'])->all());
            $user->forceFill(['role' => $data['role'], 'is_active' => true])->save();
            AuditLog::record('user.created', 'user', $user->id, ['role' => $user->role]);
        });

        return back()->with('success', 'Account created. Share its credentials securely with the intended staff member.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', Password::defaults(), 'confirmed'],
            'current_password' => ['required', 'current_password'],
        ]);
        DB::transaction(function () use ($request, $user, $data) {
            $account = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $account->forceFill(['password' => $data['password'], 'remember_token' => null])->save();
            DB::table('password_reset_tokens')->where('email', $account->email)->delete();
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $account->id)
                    ->when($account->id === $request->user()->id, fn ($query) => $query->where('id', '!=', $request->session()->getId()))->delete();
            }
            AuditLog::record('user.password_reset', 'user', $account->id);
        });

        return back()->with('success', 'Password reset. Share the new password securely with the account owner.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => ['sometimes', 'required', 'email', 'lowercase', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in(['admin', 'staff'])], 'is_active' => ['required', 'boolean'],
            'current_password' => ['required', 'current_password'],
        ]);
        DB::transaction(function () use ($request, $user, $data) {
            $admins = User::where('role', 'admin')->where('is_active', true)->lockForUpdate()->get();
            if ($user->id === $request->user()->id && ($data['role'] !== 'admin' || ! $data['is_active'])) {
                throw ValidationException::withMessages(['role' => 'You cannot remove your own administrative access.']);
            }
            if ($admins->contains('id', $user->id) && $admins->count() === 1 && ($data['role'] !== 'admin' || ! $data['is_active'])) {
                throw ValidationException::withMessages(['role' => 'The last active administrator must remain enabled.']);
            }
            $user->fill(collect($data)->only(['name', 'email'])->all());
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }
            $user->forceFill(collect($data)->only(['role', 'is_active'])->all())->save();
            if (! $data['is_active']) {
                $user->forceFill(['remember_token' => null])->save();
            }
            AuditLog::record('user.access_updated', 'user', $user->id, ['role' => $user->role, 'is_active' => $user->is_active]);
        });

        return back()->with('success', 'Account updated.');
    }
}
