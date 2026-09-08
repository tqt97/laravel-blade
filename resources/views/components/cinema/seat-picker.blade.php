{{-- @props(['screening', 'action'])

<form method="POST" action="{{ $action }}" class="space-y-5" data-seat-picker>
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) Str::uuid()) }}">
    <section class="rounded-3xl border border-[#252B36] bg-[#11151D] p-4 shadow-2xl sm:p-8">
        <div
            class="mb-8 rounded-full bg-[#1B212C] py-2 text-center text-xs font-bold uppercase tracking-[.25em] text-[#9CA3AF]">
            Màn hình</div>
        <div class="overflow-x-auto pb-2">
            <div class="mx-auto grid min-w-[560px] max-w-3xl gap-3">
                @foreach ($screening->screeningSeats->groupBy(fn($item) => $item->seat->row_label) as $row => $seats)
                <div class="flex items-center gap-3"><span
                        class="w-7 text-center text-xs font-bold text-[#9CA3AF]">{{ $row }}</span>
                    <div class="grid flex-1 gap-2"
                        style="grid-template-columns: repeat({{ min(12, max(1, $seats->count())) }}, minmax(0, 1fr));">
                        @foreach ($seats as $screeningSeat)
                        @php($available = $screeningSeat->isAvailableForSelection())
                        <button type="button" data-seat-id="{{ $screeningSeat->seat_id }}" @disabled(!$available)
                            aria-label="Ghế {{ $screeningSeat->seat->row_label }}{{ $screeningSeat->seat->seat_number }}"
                            aria-pressed="false"
                            class="grid min-h-10 min-w-10 place-items-center rounded-lg border text-xs font-bold transition-colors {{ $available ? 'border-[#CBD5E1] bg-[#303846] text-white hover:border-[#F43F5E]' : 'cursor-not-allowed border-[#667085] bg-[#1B212C] text-[#667085] line-through' }}">{{ $screeningSeat->seat->seat_number }}</button>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        <div class="mt-8 flex flex-wrap justify-center gap-4 text-xs text-[#9CA3AF]"><span><i
                    class="mr-1 inline-block size-3 rounded border border-[#CBD5E1] bg-[#303846]"></i>Còn
                trống</span><span><i class="mr-1 inline-block size-3 rounded bg-[#E11D48]"></i>Đang chọn</span><span><i
                    class="mr-1 inline-block size-3 rounded border border-[#667085] bg-[#1B212C]"></i>Đã bán</span>
        </div>
    </section>
    <div
        class="sticky bottom-3 z-20 flex items-center justify-between gap-4 rounded-2xl border border-[#252B36] bg-[#1B212C]/95 p-4 shadow-2xl backdrop-blur sm:static">
        <p class="text-sm text-[#D1D5DB]"><span class="font-bold text-white" data-seat-count>0</span> ghế đã chọn</p>
        <button type="submit" data-seat-submit disabled
            class="inline-flex min-h-11 items-center rounded-xl bg-[#E11D48] px-5 text-sm font-bold text-white transition-colors hover:bg-[#F43F5E] disabled:cursor-not-allowed disabled:opacity-50">Tiếp
            tục →</button>
    </div>
</form> --}}
