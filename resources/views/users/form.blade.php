<x-layouts.app>
    <div class="library mx-auto max-w-2xl space-y-6">
        <header><a class="text-blue-600 dark:text-blue-400 hover:underline" href="{{ route('users.index') }}">Back to users</a><h1 class="mt-3 text-3xl font-semibold">{{ $user->exists ? 'Edit user' : 'Add user' }}</h1></header>
        <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="library-panel space-y-5 p-6">
            @csrf
            @if($user->exists) @method('PUT') @endif
            <div><label class="library-label" for="name">Name</label><input class="library-input" id="name" name="name" required maxlength="255" value="{{ old('name', $user->name) }}">@error('name')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <div><label class="library-label" for="email">Email</label><input class="library-input" id="email" name="email" type="email" required maxlength="255" value="{{ old('email', $user->email) }}">@error('email')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <div><label class="library-label" for="role">Role</label><select class="library-input" id="role" name="role">@foreach(App\Enums\UserRole::cases() as $role)<option value="{{ $role->value }}" @selected(old('role', $user->role?->value ?? 'viewer') === $role->value)>{{ ucfirst($role->value) }}</option>@endforeach</select>@error('role')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <div><label class="library-label" for="password">{{ $user->exists ? 'New password (optional)' : 'Password' }}</label><input class="library-input" id="password" name="password" type="password" autocomplete="new-password" minlength="8" @required(! $user->exists)><p class="mt-1 text-sm text-gray-500">Minimum 8 characters. {{ $user->exists ? 'Leave blank to keep the current password.' : '' }}</p>@error('password')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <div><label class="library-label" for="password_confirmation">Confirm password</label><input class="library-input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" @required(! $user->exists)></div>
            <div class="flex gap-3"><button class="library-primary" type="submit">{{ $user->exists ? 'Save changes' : 'Create user' }}</button><a class="library-secondary" href="{{ route('users.index') }}">Cancel</a></div>
        </form>
    </div>
</x-layouts.app>
