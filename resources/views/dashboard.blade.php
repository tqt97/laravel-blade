<x-layouts.auth>
    <x-slot:breadcrumbs>
        <x-admin.breadcrumbs :items="[]" />
    </x-slot:breadcrumbs>
    <div class="mx-auto px-2 space-y-6">
        <section
            class="relative overflow-hidden rounded-3xl bg-neutral-950 p-6 text-white shadow-xl shadow-neutral-900/10 dark:bg-white dark:text-neutral-950 sm:p-8">
            <div
                class="pointer-events-none absolute -right-16 -top-24 size-72 rounded-full bg-neutral-400/20 blur-3xl dark:bg-white/30">
            </div>
            <div class="relative flex flex-col justify-between gap-8 sm:flex-row sm:items-end">
                <div class="max-w-2xl">
                    <div
                        class="mb-5 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-1.5 text-xs font-semibold text-neutral-200 dark:border-neutral-950/10 dark:bg-neutral-950/10 dark:text-neutral-950">
                        <span class="size-1.5 rounded-full bg-emerald-400 dark:bg-emerald-600"></span> Hệ thống đang
                        hoạt động
                    </div>
                    <h2 class="text-2xl font-semibold tracking-tight sm:text-4xl">
                        Chào mừng trở lại,
                        {{ auth()->user()->name }}.
                    </h2>
                    <p class="mt-4 max-w-xl text-sm leading-6 text-neutral-400 dark:text-neutral-800">Theo dõi tiến độ
                        học tập, quản lý các module và tiếp tục xây dựng sản phẩm Laravel của bạn.
                    </p>
                </div>
                <a href="{{ url('/') }}"
                    class="inline-flex w-fit items-center gap-2 rounded-xl bg-white/10 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/15 dark:bg-neutral-950/10 dark:text-neutral-950 dark:hover:bg-neutral-950/20">Xem
                    trang chủ
                    <span aria-hidden="true">→</span>
                </a>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Chỉ số tổng quan">
            <x-admin.stat-card label="Bài học hoàn thành" value="12" delta="+8.2%">
                <x-slot:icon>
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        aria-hidden="true">
                        <path
                            d="M4 19.5V4.5A2.5 2.5 0 0 1 6.5 2H20v17H6.5A2.5 2.5 0 0 0 4 21.5m0-2A2.5 2.5 0 0 1 6.5 17H20" />
                        <path d="m9 9 2 2 4-4" />
                    </svg>
                </x-slot:icon>
            </x-admin.stat-card>
            <x-admin.stat-card label="Dự án đang làm" value="03" delta="+02">
                <x-slot:icon>
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        aria-hidden="true">
                        <path d="m4 7 8-4 8 4-8 4-8-4Z" />
                        <path d="m4 12 8 4 8-4M4 17l8 4 8-4" />
                    </svg>
                </x-slot:icon>
            </x-admin.stat-card>
            <x-admin.stat-card label="Thời gian học" value="18.5h" delta="+14.5%">
                <x-slot:icon>
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        aria-hidden="true">
                        <circle cx="12" cy="12" r="9" />
                        <path d="M12 7v5l3 2" />
                    </svg>
                </x-slot:icon>
            </x-admin.stat-card>
            <x-admin.stat-card label="Chuỗi ngày học" value="07 ngày" delta="+03">
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
            <div
                class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-neutral-900 sm:p-7">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-600 dark:text-neutral-300">
                            Tiếp tục học</p>
                        <h2 class="mt-2 text-xl font-semibold tracking-tight">Laravel Fundamentals</h2>
                    </div><span
                        class="rounded-full bg-neutral-50 px-3 py-1 text-xs font-semibold text-neutral-700 dark:bg-neutral-400/10 dark:text-neutral-300">68%</span>
                </div>
                <div class="mt-8 h-2 overflow-hidden rounded-full bg-neutral-100 dark:bg-white/10">
                    <div class="h-full w-[68%] rounded-full bg-neutral-500"></div>
                </div>
                <div class="mt-4 flex items-center justify-between text-sm text-neutral-500 dark:text-neutral-400">
                    <span>17 / 25 bài học</span><span>Khoảng 2 giờ còn lại</span>
                </div>
                <a href="#"
                    class="mt-7 inline-flex items-center gap-2 text-sm font-semibold text-neutral-900 transition hover:text-neutral-600 dark:text-white dark:hover:text-neutral-300">Tiếp
                    tục bài học <span aria-hidden="true">→</span></a>
            </div>

            <div
                class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-neutral-900 sm:p-7">
                <div class="flex items-center justify-between">
                    <div>
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-600 dark:text-neutral-300">
                            Hoạt động gần đây</p>
                        <h2 class="mt-2 text-xl font-semibold tracking-tight">Tuần này</h2>
                    </div><span class="text-xs font-medium text-neutral-400">7 ngày</span>
                </div>
                <div class="mt-6 space-y-5">
                    <div class="flex gap-3"><span
                            class="mt-1 flex size-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-300"><svg
                                class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                aria-hidden="true">
                                <path d="m5 12 4 4L19 6" />
                            </svg></span>
                        <div>
                            <p class="text-sm font-semibold">Hoàn thành bài học</p>
                            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Eloquent relationships · 2
                                giờ trước</p>
                        </div>
                    </div>
                    <div class="flex gap-3"><span
                            class="mt-1 flex size-8 shrink-0 items-center justify-center rounded-full bg-neutral-100 text-neutral-600 dark:bg-neutral-400/10 dark:text-neutral-300"><svg
                                class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                aria-hidden="true">
                                <path d="M12 3v18M3 12h18" />
                            </svg></span>
                        <div>
                            <p class="text-sm font-semibold">Tạo project mới</p>
                            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Laravel Auth Lab · Hôm qua
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
                            <p class="text-sm font-semibold">Lưu tài liệu tham khảo</p>
                            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Validation & Requests · 2
                                ngày trước</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section
            class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-neutral-900 sm:p-7">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-600 dark:text-neutral-300">
                        Lối tắt
                    </p>
                    <h2 class="mt-2 text-xl font-semibold tracking-tight">Bắt đầu nhanh</h2>
                </div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Các khu vực sẽ được mở rộng trong những phiên
                    bản tiếp theo.</p>
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
                        <span class="block text-sm font-semibold">Tạo bài học</span>
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
                        <span class="block text-sm font-semibold">Quản lý học viên

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
                        <span class="block text-sm font-semibold">Thiết lập workspace

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
