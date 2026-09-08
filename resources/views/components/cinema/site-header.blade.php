{{-- <header class="sticky top-0 z-50 border-b border-[#252B36] bg-[#0B0E14]/90 backdrop-blur-md">
    <div class="mx-auto flex h-16 w-full max-w-[1440px] items-center justify-between gap-4 px-4 sm:px-6 lg:px-8 xl:px-10">
        <div class="flex min-w-0 items-center gap-8">
            <a href="{{ route('cinema.movies.index') }}" class="flex shrink-0 items-center gap-2.5 text-[#F8FAFC]">
                <span class="grid size-9 place-items-center rounded-xl bg-[#E11D48] text-lg font-extrabold text-white">C</span>
                <span class="hidden sm:block">
                    <span class="block font-bold tracking-tight">CINEMAX</span>
                    <span class="block text-[10px] uppercase tracking-[.22em] text-[#F43F5E]">Luxury Cinema</span>
                </span>
            </a>
            <nav class="hidden items-center gap-1 lg:flex" aria-label="Điều hướng chính">
                <a href="{{ route('cinema.movies.index') }}" @class(['rounded-xl px-3 py-2 text-sm font-semibold transition-colors hover:bg-[#1B212C] hover:text-white', 'bg-[#E11D48] text-white' => request()->routeIs('cinema.movies.*')])>Phim</a>
                <a href="{{ route('user.screenings.index') }}" @class(['rounded-xl px-3 py-2 text-sm font-semibold transition-colors hover:bg-[#1B212C] hover:text-white', 'bg-[#E11D48] text-white' => request()->routeIs('user.screenings.*')])>Lịch chiếu</a>
                @auth
                    <a href="{{ route('user.bookings.index') }}" @class(['rounded-xl px-3 py-2 text-sm font-semibold transition-colors hover:bg-[#1B212C] hover:text-white', 'bg-[#E11D48] text-white' => request()->routeIs('user.bookings.*')])>Vé của tôi</a>
                @endauth
            </nav>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            <form action="{{ route('cinema.movies.index') }}" method="GET" class="relative hidden md:block">
                <label for="cinema-search" class="sr-only">Tìm phim</label>
                <input id="cinema-search" name="search" value="{{ request('search') }}" placeholder="Tìm phim, diễn viên..." class="h-10 w-52 rounded-xl border border-[#252B36] bg-[#11151D] px-3 text-sm text-[#F8FAFC] placeholder:text-[#667085] focus:border-[#E11D48] focus:outline-none focus:ring-2 focus:ring-[#E11D48]/30 xl:w-64">
            </form>
            <a href="{{ route('cinema.movies.index') }}" class="grid size-10 place-items-center rounded-xl text-[#D1D5DB] hover:bg-[#1B212C] hover:text-white md:hidden" aria-label="Tìm phim">⌕</a>
            @auth
                <a href="{{ route('user.dashboard') }}" class="grid size-10 place-items-center rounded-full border border-[#252B36] bg-[#1B212C] text-sm font-bold text-[#F8FAFC]" aria-label="Tài khoản">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</a>
            @else
                <a href="{{ route('login') }}" class="inline-flex min-h-10 items-center rounded-xl bg-[#E11D48] px-3 text-sm font-semibold text-white transition-colors hover:bg-[#F43F5E]">Đăng nhập</a>
            @endauth
        </div>
    </div>
</header> --}}
