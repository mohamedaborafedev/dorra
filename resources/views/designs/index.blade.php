<x-layouts.app>
    <div class="library space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div><h1 class="text-3xl font-semibold tracking-tight">Design Library</h1><p class="mt-2 text-gray-500 dark:text-gray-400">{{ auth()->user()->can('create', App\Models\Design::class) ? 'Find the right design. Browse your collection by title, category, or status.' : 'Find the right design. Browse published designs by title or category.' }}</p></div>
            @can('create', App\Models\Design::class)
                <a class="library-primary" href="{{ route('designs.create') }}">Add design</a>
            @endcan
        </header>

        <form method="GET" action="{{ route('dashboard') }}" @class(['library-panel grid gap-4 p-5 md:grid-cols-2', 'xl:grid-cols-[2fr_1fr_1fr_auto]' => auth()->user()->can('create', App\Models\Design::class), 'xl:grid-cols-[2fr_1fr_auto]' => ! auth()->user()->can('create', App\Models\Design::class)])>
            <div><label class="library-label" for="search">Search title</label><input class="library-input" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Title starts with…" maxlength="255"><p class="mt-1 text-xs text-gray-500">Arabic and English prefixes supported.</p></div>
            <div><label class="library-label" for="category_id">Category</label><select class="library-input" id="category_id" name="category_id"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(($filters['category_id'] ?? '') == $category->id)>{{ $category->name }}</option>@endforeach</select></div>
            @can('create', App\Models\Design::class)
                <div><label class="library-label" for="status">Status</label><select class="library-input" id="status" name="status"><option value="">All statuses</option>@foreach($availableStatuses as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ ucfirst($status->value) }}</option>@endforeach</select></div>
            @endcan
            <div class="flex items-start gap-3 md:pt-6"><button class="library-primary" type="submit">Apply filters</button><a class="library-secondary" href="{{ route('dashboard') }}">Clear filters</a></div>
            @if($errors->any())<div role="alert" class="text-sm text-red-600 md:col-span-2">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        </form>

        <section class="library-panel overflow-hidden" aria-label="Design results">
            <div class="flex flex-wrap justify-between gap-2 border-b border-gray-200 px-5 py-4 dark:border-gray-700"><h2 class="font-semibold">Your collection <span class="ml-2 font-normal text-gray-500">{{ number_format($designs->total()) }} designs</span></h2><p class="text-sm text-gray-500">Newest first · 24 per page</p></div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="bg-gray-50 text-gray-500 dark:bg-gray-900/40 dark:text-gray-400"><tr><th class="px-5 py-3 font-medium">Preview</th><th class="px-5 py-3 font-medium">Title</th><th class="px-5 py-3 font-medium">Category</th><th class="px-5 py-3 font-medium">Status</th><th class="px-5 py-3 font-medium">Created</th><th class="px-5 py-3 text-right font-medium">Actions</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($designs as $design)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                            <td class="px-5 py-3"><img src="{{ $design->thumbnail_url }}" alt="" width="64" height="44" loading="lazy" class="h-11 w-16 rounded object-cover bg-gray-100"></td>
                            <td class="max-w-xs px-5 py-3"><a href="{{ route('designs.show', $design) }}" class="font-medium hover:underline" dir="auto">{{ $design->table_title }}</a></td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ $design->category->name }}</td>
                            <td class="px-5 py-3"><span @class(['rounded-full px-2.5 py-1 text-xs font-medium', 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' => $design->status === App\Enums\DesignStatus::Published, 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' => $design->status === App\Enums\DesignStatus::Draft])>{{ ucfirst($design->status->value) }}</span></td>
                            <td class="whitespace-nowrap px-5 py-3 text-gray-500 dark:text-gray-400">{{ $design->created_at?->format('M j, Y') ?? '—' }}</td>
                            <td class="px-5 py-3"><div class="flex justify-end gap-3"><a class="text-blue-600 dark:text-blue-400 hover:underline" href="{{ route('designs.show', $design) }}">View</a>@can('update', $design)<a class="text-blue-600 dark:text-blue-400 hover:underline" href="{{ route('designs.edit', $design) }}">Edit</a>@endcan @can('delete', $design)@include('designs.delete')@endcan</div></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-16 text-center"><h3 class="text-lg font-semibold">No designs found</h3><p class="mt-2 text-gray-500">Try another title prefix or clear your filters.</p><a class="mt-4 inline-block text-blue-600 hover:underline" href="{{ route('dashboard') }}">Clear filters</a></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 p-5 dark:border-gray-700">{{ $designs->onEachSide(1)->links() }}</div>
        </section>
    </div>
</x-layouts.app>
