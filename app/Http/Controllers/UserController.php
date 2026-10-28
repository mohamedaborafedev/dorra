<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        return view('users.index', ['users' => User::orderByDesc('id')->paginate(24)]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('users.form', ['user' => new User]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = new User;
        $user->fill($request->safe()->only(['name', 'email', 'password']));
        $user->role = UserRole::from($request->validated('role'));
        $user->save();

        return to_route('users.show', $user)->with('status', 'User created successfully.');
    }

    public function show(User $user): View
    {
        Gate::authorize('view', $user);

        return view('users.show', ['user' => $user]);
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('users.form', ['user' => $user]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        if ($request->user()?->is($user) && $request->validated('role') !== UserRole::Admin->value) {
            throw ValidationException::withMessages(['role' => 'You cannot remove your own administrator access.']);
        }
        DB::transaction(function () use ($request, $user) {
            $admins = User::where('role', UserRole::Admin->value)->lockForUpdate()->get();
            if ($user->role === UserRole::Admin && $request->validated('role') !== UserRole::Admin->value && $admins->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'At least one administrator must remain.']);
            }
            $user->fill($request->safe()->only(['name', 'email']));
            $user->role = UserRole::from($request->validated('role'));
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }
            if ($request->filled('password')) {
                $user->password = $request->validated('password');
                $user->remember_token = Str::random(60);
                // Invalidate existing sessions when the admin resets a password.
                if (config('session.driver') === 'database') {
                    DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
                }
            }
            $user->save();
        });

        return to_route('users.show', $user)->with('status', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);
        DB::transaction(function () use ($user) {
            $admins = User::where('role', UserRole::Admin->value)->lockForUpdate()->get();
            if ($user->role === UserRole::Admin && $admins->count() <= 1) {
                throw ValidationException::withMessages(['user' => 'At least one administrator must remain.']);
            }
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
            $user->delete();
        });

        return to_route('users.index')->with('status', 'User deleted successfully.');
    }
}
