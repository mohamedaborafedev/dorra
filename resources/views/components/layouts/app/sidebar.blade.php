<aside :class="{ 'w-full md:w-64': sidebarOpen, 'w-0 md:w-16 hidden md:block': !sidebarOpen }"
    class="bg-sidebar text-sidebar-foreground border-r border-gray-200 dark:border-gray-700 sidebar-transition overflow-hidden">
    <div class="h-full flex flex-col">
        <nav aria-label="Main navigation" class="flex-1 overflow-y-auto custom-scrollbar py-4">
            <ul class="space-y-2 px-2">
                <x-layouts.sidebar-link href="{{ route('dashboard') }}" icon="fas-images" :active="request()->routeIs('dashboard', 'designs.*')">Design Library</x-layouts.sidebar-link>
                @can('viewAny', App\Models\User::class)
                    <x-layouts.sidebar-link href="{{ route('users.index') }}" icon="fas-users" :active="request()->routeIs('users.*')">Users</x-layouts.sidebar-link>
                @endcan
                <x-layouts.sidebar-link href="{{ route('settings.profile.edit') }}" icon="fas-gear" :active="request()->routeIs('settings.*')">Account settings</x-layouts.sidebar-link>
            </ul>
        </nav>
    </div>
</aside>
