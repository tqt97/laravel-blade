@props(['current'])

@php
    $steps = [
        'seats' => __('cinema.public.choose_seats'),
        'review' => __('booking.checkout.review_step'),
        'payment' => __('booking.checkout.payment_step'),
        'done' => __('booking.checkout.complete_step'),
    ];
    $currentIndex = array_search($current, array_keys($steps), true);
@endphp

<nav aria-label="{{ __('booking.checkout.progress_label') }}" class="overflow-x-auto pb-1">
    <ol class="mx-auto flex min-w-max items-center justify-center gap-2 text-xs sm:gap-3">
        @foreach ($steps as $key => $label)
            @php($stepIndex = $loop->index)
            <li class="flex items-center gap-2 {{ $stepIndex <= $currentIndex ? 'text-primary' : 'text-muted-foreground' }}">
                <span class="grid size-7 place-items-center rounded-full border text-[11px] font-bold {{ $stepIndex < $currentIndex ? 'border-primary bg-primary text-primary-foreground' : ($stepIndex === $currentIndex ? 'border-primary bg-primary-soft' : 'border-border bg-card') }}">
                    @if ($stepIndex < $currentIndex) ✓ @else {{ $loop->iteration }} @endif
                </span>
                <span class="font-semibold">{{ $label }}</span>
                @if (!$loop->last)<span class="mx-1 h-px w-5 bg-border sm:w-10" aria-hidden="true"></span>@endif
            </li>
        @endforeach
    </ol>
</nav>
