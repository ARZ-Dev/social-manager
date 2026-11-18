<x-layouts.base>
    @guest
        {{ $slot }}
    @endguest

    @auth
        <div x-data="{ sidebar: false }" x-on:keydown.escape.window="sidebar = false" class="min-h-full">
            {{-- Mobile: dim the page behind the open sidebar --}}
            <div x-show="sidebar" x-transition.opacity x-cloak x-on:click="sidebar = false"
                class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden"></div>

            <aside id="sidebar"
                class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-slate-200 bg-white transition-transform duration-200 lg:translate-x-0 dark:border-slate-800 dark:bg-slate-900"
                :class="sidebar && '!translate-x-0'">
                @include('components.sidebar')
            </aside>

            {{-- Full screen height, so the footer stays at the bottom on short pages --}}
            <div class="flex min-h-dvh flex-col lg:pl-64">
                @include('components.navbar')

                <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    {{ $slot }}
                </main>

                <footer class="flex justify-end px-4 py-4 text-right text-xs text-slate-400 sm:px-6 lg:px-8 dark:text-slate-500">
                    <p>
                        SocialM © {{ date('Y') }} · Designed &amp; developed by
                        <a href="https://www.aarzgt.com/" target="_blank" rel="noopener" class="font-medium hover:text-slate-600 dark:hover:text-slate-300">ARZGT</a>
                    </p>
                </footer>
            </div>
        </div>
    @endauth
</x-layouts.base>
