<form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Delete this user permanently? This cannot be undone.');">
    @csrf
    @method('DELETE')
    <button class="text-red-600 dark:text-red-400 hover:underline" type="submit" aria-label="Delete {{ $user->name }}">Delete</button>
</form>
