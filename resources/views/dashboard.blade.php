<x-layouts.auth>
    <x-slot:breadcrumbs>
        <x-admin.breadcrumbs :items="[]" />
    </x-slot:breadcrumbs>
    <div class="mx-auto px-2 space-y-6">
        <section
            class="relative overflow-hidden rounded-3xl bg-primary p-6 text-primary-foreground shadow-xl shadow-primary/20 sm:p-8">
            <div
                class="pointer-events-none absolute -right-16 -top-24 size-72 rounded-full bg-primary-foreground/20 blur-3xl">
            </div>
            <div class="relative flex flex-col justify-between gap-8 sm:flex-row sm:items-end">
                <div class="max-w-2xl">
                    <div
                        class="mb-5 inline-flex items-center gap-2 rounded-full border border-primary-foreground/20 bg-primary-foreground/10 px-3 py-1.5 text-xs font-semibold text-primary-foreground">
                        <span class="size-1.5 rounded-full bg-emerald-400 dark:bg-emerald-600"></span>
                        {{ __('ui.dashboard.online') }}
                    </div>
                    <h2 class="text-2xl font-semibold tracking-tight sm:text-4xl">
                        {{ __('ui.dashboard.welcome') }}
                        {{ auth()->user()->name }}.
                    </h2>
                    <p class="mt-4 max-w-xl text-sm leading-6 text-primary-foreground/75">
                        {{ __('ui.dashboard.description') }}
                    </p>
                </div>
                <a href="{{ url('/') }}"
                    class="inline-flex w-fit items-center gap-2 rounded-xl bg-primary-foreground/10 px-4 py-3 text-sm font-semibold text-primary-foreground transition hover:bg-primary-foreground/20">{{ __('ui.dashboard.home') }}
                    <span aria-hidden="true">→</span>
                </a>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="{{ __('ui.dashboard.overview') }}">
            <x-admin.stat-card label="{{ __('ui.dashboard.completed_lessons') }}" value="12" delta="+8.2%">
                <x-slot:icon>
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        aria-hidden="true">
                        <path
                            d="M4 19.5V4.5A2.5 2.5 0 0 1 6.5 2H20v17H6.5A2.5 2.5 0 0 0 4 21.5m0-2A2.5 2.5 0 0 1 6.5 17H20" />
                        <path d="m9 9 2 2 4-4" />
                    </svg>
                </x-slot:icon>
            </x-admin.stat-card>
            <x-admin.stat-card label="{{ __('ui.dashboard.active_projects') }}" value="03" delta="+02">
                <x-slot:icon>
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        aria-hidden="true">
                        <path d="m4 7 8-4 8 4-8 4-8-4Z" />
                        <path d="m4 12 8 4 8-4M4 17l8 4 8-4" />
                    </svg>
                </x-slot:icon>
            </x-admin.stat-card>
            <x-admin.stat-card label="{{ __('ui.dashboard.study_time') }}" value="18.5h" delta="+14.5%">
                <x-slot:icon>
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        aria-hidden="true">
                        <circle cx="12" cy="12" r="9" />
                        <path d="M12 7v5l3 2" />
                    </svg>
                </x-slot:icon>
            </x-admin.stat-card>
            <x-admin.stat-card label="{{ __('ui.dashboard.learning_streak') }}" value="07 {{ __('ui.dashboard.days') }}"
                delta="+03">
                <x-slot:icon>
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        aria-hidden="true">
                        <path d="M12 3c2 3 5 4.5 5 8.5A5 5 0 0 1 7 12c0-2.5 1.5-4.2 3.2-5.8.8-.8 1.3-1.6 1.8-3.2Z" />
                        <path
                            d="M9.5 15.5A2.5 2.5 0 0 0 12 18a2.5 2.5 0 0 0 2.5-2.5c0-1.2-.7-2-1.7-2.8-.3 1-.8 1.4-1.3 1.8-.5-.8-.8-1.2-1.1-1.8-.5.7-.9 1.5-.9 2.8Z" />
                    </svg>
                </x-slot:icon>
            </x-admin.stat-card>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.25fr_0.75fr]">
            <div class="rounded-3xl border border-border bg-card p-6 text-card-foreground shadow-sm sm:p-7">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                            {{ __('ui.dashboard.continue_learning') }}
                        </p>
                        <h2 class="mt-2 text-xl font-semibold tracking-tight">Laravel Fundamentals</h2>
                    </div><span
                        class="rounded-full bg-muted px-3 py-1 text-xs font-semibold text-muted-foreground">68%</span>
                </div>
                <div class="mt-8 h-2 overflow-hidden rounded-full bg-muted">
                    <div class="h-full w-[68%] rounded-full bg-primary"></div>
                </div>
                <div class="mt-4 flex items-center justify-between text-sm text-muted-foreground">
                    <span>{{ __('ui.dashboard.lessons_count') }}</span><span>{{ __('ui.dashboard.time_left') }}</span>
                </div>
                <a href="#"
                    class="mt-7 inline-flex items-center gap-2 text-sm font-semibold text-primary transition hover:text-primary-strong">{{ __('ui.dashboard.continue_lesson') }}
                    <span aria-hidden="true">→</span></a>
            </div>

            <div class="rounded-3xl border border-border bg-card p-6 text-card-foreground shadow-sm sm:p-7">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                            {{ __('ui.dashboard.recent_activity') }}
                        </p>
                        <h2 class="mt-2 text-xl font-semibold tracking-tight">{{ __('ui.dashboard.week') }}</h2>
                    </div><span class="text-xs font-medium text-muted-foreground">{{ __('ui.dashboard.days') }}</span>
                </div>
                <div class="mt-6 space-y-5">
                    <div class="flex gap-3"><span
                            class="mt-1 flex size-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-300"><svg
                                class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                aria-hidden="true">
                                <path d="m5 12 4 4L19 6" />
                            </svg></span>
                        <div>
                            <p class="text-sm font-semibold">{{ __('ui.dashboard.completed_lesson') }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">Eloquent relationships ·
                                {{ __('ui.dashboard.two_hours_ago') }}</p>
                        </div>
                    </div>
                    <div class="flex gap-3"><span
                            class="mt-1 flex size-8 shrink-0 items-center justify-center rounded-full bg-neutral-100 text-neutral-600 dark:bg-neutral-400/10 dark:text-neutral-300"><svg
                                class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                aria-hidden="true">
                                <path d="M12 3v18M3 12h18" />
                            </svg></span>
                        <div>
                            <p class="text-sm font-semibold">{{ __('ui.dashboard.created_project') }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">Laravel Auth Lab ·
                                {{ __('ui.dashboard.yesterday') }}
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-3"><span
                            class="mt-1 flex size-8 shrink-0 items-center justify-center rounded-full bg-violet-100 text-violet-600 dark:bg-violet-400/10 dark:text-violet-300"><svg
                                class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                aria-hidden="true">
                                <path
                                    d="M4 19.5V4.5A2.5 2.5 0 0 1 6.5 2H20v17H6.5A2.5 2.5 0 0 0 4 21.5m0-2A2.5 2.5 0 0 1 6.5 17H20" />
                            </svg></span>
                        <div>
                            <p class="text-sm font-semibold">{{ __('ui.dashboard.saved_reference') }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">Validation & Requests ·
                                {{ __('ui.dashboard.two_days_ago') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-border bg-card p-6 text-card-foreground shadow-sm sm:p-7">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                        {{ __('ui.dashboard.shortcuts') }}
                    </p>
                    <h2 class="mt-2 text-xl font-semibold tracking-tight">{{ __('ui.dashboard.quick_start') }}</h2>
                </div>
                <p class="text-sm text-muted-foreground">{{ __('ui.dashboard.coming_soon_description') }}</p>
            </div>
            <div class="mt-6 grid gap-3 sm:grid-cols-3">
                <button type="button" disabled
                    class="ui-action flex cursor-not-allowed items-center gap-3 rounded-2xl border border-dashed border-neutral-200 p-4 text-left opacity-60 transition hover:border-neutral-300 dark:border-white/10 dark:hover:border-neutral-400/40">
                    <span
                        class="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-500 dark:bg-white/6 dark:text-neutral-400"><svg
                            class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                            aria-hidden="true">
                            <path d="M12 3v18M3 12h18" />
                        </svg>
                    </span>
                    <span>
                        <span class="block text-sm font-semibold">{{ __('ui.dashboard.create_lesson') }}</span>
                        <span class="mt-1 block text-xs text-neutral-500 dark:text-neutral-400">Sắp
                            có
                        </span>
                    </span>
                </button>
                <button type="button" disabled
                    class="ui-action flex cursor-not-allowed items-center gap-3 rounded-2xl border border-dashed border-neutral-200 p-4 text-left opacity-60 transition hover:border-neutral-300 dark:border-white/10 dark:hover:border-neutral-400/40">
                    <span
                        class="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-500 dark:bg-white/6 dark:text-neutral-400"><svg
                            class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                            aria-hidden="true">
                            <path
                                d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                    </span>
                    <span>
                        <span class="block text-sm font-semibold">{{ __('ui.dashboard.manage_students') }}

                        </span>
                        <span class="mt-1 block text-xs text-neutral-500 dark:text-neutral-400">Sắp
                            có
                        </span>
                    </span>
                </button>
                <button type="button" disabled
                    class="ui-action flex cursor-not-allowed items-center gap-3 rounded-2xl border border-dashed border-neutral-200 p-4 text-left opacity-60 transition hover:border-neutral-300 dark:border-white/10 dark:hover:border-neutral-400/40"><span
                        class="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-500 dark:bg-white/6 dark:text-neutral-400"><svg
                            class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                            aria-hidden="true">
                            <path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" />
                            <path
                                d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-1.8 1.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5v.2h-2.6v-.2a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1-1.8-1.8.1-.1A1.7 1.7 0 0 0 8 15a1.7 1.7 0 0 0-1.5-1H6v-2.6h.5A1.7 1.7 0 0 0 8 10a1.7 1.7 0 0 0-.3-1.9l-.1-.1 1.8-1.8.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.5v-.2H15v.2a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1 1.8 1.8-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.5 1h.2V14h-.2a1.7 1.7 0 0 0-1.5 1Z" />
                        </svg>
                    </span>
                    <span>
                        <span class="block text-sm font-semibold">{{ __('ui.dashboard.workspace_setup') }}

                        </span>
                        <span class="mt-1 block text-xs text-neutral-500 dark:text-neutral-400">Sắp
                            có
                        </span>
                    </span>
                </button>
            </div>
        </section>
    </div>
</x-layouts.auth>
