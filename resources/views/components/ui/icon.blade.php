@props([
    'name',
    'class' => 'size-4',
    'strokeWidth' => '1.8',
])

@php
    /**
     * Keep the SVG vocabulary in one place so action buttons and navigation
     * controls cannot drift into slightly different versions of the same icon.
     */
    $markup = match ($name) {
        'plus' => '<path d="M12 5v14M5 12h14" />',
        'save' => '<path d="M5 4h12l2 2v14H5V4Z" /><path d="M8 4v5h8V4M8 20v-6h8v6" />',
        'edit', 'pencil' => '<path d="m4 16-.8 4.8L8 20l10.8-10.8-4-4L4 16Z" /><path d="m13.5 6.5 4 4" />',
        'trash', 'delete' => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" />',
        'close' => '<path d="m6 6 12 12M18 6 6 18" />',
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6" />',
        'search' => '<circle cx="11" cy="11" r="6.5" /><path d="m16 16 5 5" />',
        'restore' => '<path d="M3 12a9 9 0 1 0 3-6.7" /><path d="M3 4v6h6" /><path d="M12 7v5l3 2" />',
        'eye' => '<path d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5Z" /><circle cx="12" cy="12" r="2.5" />',
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5" /><rect x="14" y="3" width="7" height="7" rx="1.5" /><rect x="3" y="14" width="7" height="7" rx="1.5" /><rect x="14" y="14" width="7" height="7" rx="1.5" />',
        'movies' => '<rect x="3" y="4" width="18" height="16" rx="2" /><path d="m8 4 3 4-3 4M16 4l-3 4 3 4M8 20l3-4M16 20l-3-4" />',
        'bookings' => '<rect x="3" y="5" width="18" height="16" rx="2" /><path d="M16 3v4M8 3v4M3 10h18" />',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16" />',
        'logout' => '<path d="M10 17l5-5-5-5M15 12H3" /><path d="M21 19V5a2 2 0 0 0-2-2h-6" />',
        'user' => '<circle cx="12" cy="8" r="3.5" /><path d="M4.5 20a7.5 7.5 0 0 1 15 0" />',
        'shield' => '<path d="M12 3 20 6v5c0 5-3.4 8.3-8 10-4.6-1.7-8-5-8-10V6l8-3Z" /><path d="m9 12 2 2 4-4" />',
        'chevron-down' => '<path d="m6 9 6 6 6-6" />',
        default => '',
    };
@endphp

@if ($markup !== '')
    <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none"
        stroke="currentColor" stroke-width="{{ $strokeWidth }}" stroke-linecap="round" stroke-linejoin="round"
        aria-hidden="true">{!! $markup !!}</svg>
@endif
