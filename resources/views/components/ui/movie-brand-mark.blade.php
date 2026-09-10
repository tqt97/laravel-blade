@props(['class' => 'h-9 w-auto'])

<a href="{{ route('cinema.movies.index') }}"
    class="group inline-flex w-fit items-center gap-2.5 text-primary transition hover:opacity-90">
    <span class="grid size-10 place-items-center rounded-xl bg-primary text-primary-foreground shadow-sm transition duration-200 group-hover:-rotate-3 group-hover:shadow-md">
        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 8.5h16v9.25A2.25 2.25 0 0 1 17.75 20H6.25A2.25 2.25 0 0 1 4 17.75V8.5Z" />
            <path d="m4 8.5 2-4.5h14l-2 4.5M8 4l2 4.5M14 4l2 4.5M4 12h16" />
            <path fill="currentColor" stroke="none" d="m10 14 5 2.5-5 2.5v-5Z" />
        </svg>
    </span>
    <span data-sidebar-label class="leading-none">
        <span class="block text-[17px] font-extrabold tracking-[-0.03em] text-foreground">{{ config('app.movie_name') }}</span>
        <span class="mt-1 block text-[9px] font-bold uppercase tracking-[0.28em] text-primary">Cinema</span>
    </span>
</a>
