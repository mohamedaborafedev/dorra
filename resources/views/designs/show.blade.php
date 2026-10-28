<x-layouts.app>
    <article class="library mx-auto max-w-4xl space-y-6">
        <a href="{{ route('dashboard') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">Back to library</a>
        <header class="flex flex-wrap items-center justify-between gap-4"><h1 class="text-3xl font-semibold" dir="auto">{{ $design->title }}</h1><div class="flex items-center gap-4">@can('update', $design)<a class="library-primary" href="{{ route('designs.edit', $design) }}">Edit design</a>@endcan @can('delete', $design)@include('designs.delete')@endcan</div></header>
        <div class="library-panel overflow-hidden"><img src="{{ $design->thumbnail_url }}" alt="{{ $design->title }}" class="max-h-[480px] w-full bg-gray-100 object-contain dark:bg-gray-900"><dl class="grid gap-5 p-6 sm:grid-cols-3"><div><dt class="library-label">Category</dt><dd>{{ $design->category->name }}</dd></div><div><dt class="library-label">Status</dt><dd>{{ ucfirst($design->status->value) }}</dd></div><div><dt class="library-label">Created</dt><dd>{{ $design->created_at?->format('M j, Y H:i') ?? '—' }}</dd></div></dl></div>
    </article>
</x-layouts.app>
