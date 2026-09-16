@php
    use App\Support\AdminNavigation;

    $navItems = auth()->check() ? AdminNavigation::build(auth()->user()) : [];
@endphp

<aside class="flex w-64 shrink-0 flex-col border-r border-zinc-200 bg-white">
    <div class="border-b border-zinc-200 px-5 py-4">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3">
            <img src="{{ $branding['logoUrl'] }}" alt="{{ $branding['shortName'] }}" class="h-8 w-auto">
            <div>
                <div class="text-sm font-semibold">{{ $branding['shortName'] }}</div>
                <div class="text-xs text-zinc-500">{{ $branding['adminLabel'] }}</div>
            </div>
        </a>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
        @foreach ($navItems as $item)
            @php
                $active = request()->routeIs($item['route']) || request()->routeIs(str_replace('.index', '.*', $item['route']));
            @endphp
            <a
                href="{{ $item['href'] }}"
                class="flex items-center rounded-lg px-3 py-2 text-sm font-medium {{ $active ? 'border-l-2 border-zinc-900 bg-zinc-100 text-zinc-900' : 'text-zinc-600 hover:bg-zinc-50 hover:text-zinc-900' }}"
            >
                {{ $item['label'] }}
            </a>

            @if (! empty($item['children']) && $active)
                <div class="ml-3 space-y-1 border-l border-zinc-200 pl-3">
                    @foreach ($item['children'] as $child)
                        <a
                            href="{{ $child['href'] }}"
                            class="block rounded-md px-2 py-1.5 text-xs font-medium {{ request()->routeIs($child['route']) ? 'bg-zinc-100 text-zinc-900' : 'text-zinc-500 hover:text-zinc-800' }}"
                        >
                            {{ $child['label'] }}
                        </a>
                    @endforeach
                </div>
            @endif
        @endforeach
    </nav>

  <div class="border-t border-zinc-200 p-4">
        <div class="flex items-center gap-3">
            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-zinc-900 text-xs font-semibold text-white">
                {{ collect(explode(' ', auth()->user()->name))->filter()->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->take(2)->implode('') }}
            </div>
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-medium">{{ auth()->user()->name }}</div>
                <div class="truncate text-xs text-zinc-500">{{ auth()->user()->email }}</div>
            </div>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="rounded-md p-1.5 text-zinc-500 hover:bg-zinc-100" title="Sign out">↗</button>
            </form>
        </div>
    </div>
</aside>
