<form method="POST" action="{{ route('designs.destroy', $design) }}" onsubmit="return confirm('Delete this design permanently? This cannot be undone.');">
    @csrf
    @method('DELETE')
    <button type="submit" class="text-red-600 dark:text-red-400 hover:underline" aria-label="Delete {{ $design->title }}">Delete</button>
</form>
