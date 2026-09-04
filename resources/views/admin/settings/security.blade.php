@php
    $user = auth()->user();
    $twoFactorEnabled = $user->hasEnabledTwoFactorAuthentication();
    $twoFactorPendingConfirmation = ! is_null($user->two_factor_secret) && ! $twoFactorEnabled;
    $twoFactorSecret = $twoFactorPendingConfirmation ? decrypt($user->two_factor_secret) : null;
@endphp

<x-layouts.auth :title="__('ui.security.title')" :heading="__('ui.security.title')">
    <x-slot:breadcrumbs>
        <x-admin.breadcrumbs :items="[['label' => __('ui.security.title')]]" />
    </x-slot:breadcrumbs>

    <div class="mx-auto max-w-4xl space-y-6">
        <x-admin.page-header :title="__('ui.security.account_title')"
            :description="__('ui.security.description')" />

        <x-auth.feedback />

        @if (! $twoFactorEnabled && ! $twoFactorPendingConfirmation)
            <section class="rounded-2xl border border-border bg-card shadow-sm shadow-border/40">
                <div class="flex flex-col gap-5 p-6 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex gap-4">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect width="14" height="18" x="5" y="3" rx="2"/><path d="M8 7h8M8 11h8M8 15h4"/></svg>
                        </span>
                        <div>
                            <h2 class="font-semibold text-card-foreground">{{ __('ui.security.two_factor') }}</h2>
                            <p class="mt-1 max-w-xl text-sm leading-6 text-muted-foreground">{{ __('ui.security.enable_description') }}</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('two-factor.enable') }}" class="shrink-0">
                        @csrf
                        <x-admin.button type="submit" icon="arrow-right">{{ __('ui.security.enable') }}</x-admin.button>
                    </form>
                </div>
            </section>
        @elseif ($twoFactorPendingConfirmation)
            <section class="rounded-2xl border border-warning/30 bg-card shadow-sm shadow-border/40">
                <div class="border-b border-warning/20 p-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-warning-foreground">{{ __('ui.security.finish_setup') }}</p>
                    <h2 class="mt-2 font-semibold text-card-foreground">{{ __('ui.security.scan_qr') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-muted-foreground">{{ __('ui.security.scan_description') }}</p>
                </div>
                <div class="grid gap-6 p-6 sm:grid-cols-[auto_1fr] sm:items-center">
                    {{-- Fortify generates this trusted inline SVG; it must stay unescaped for the QR image to render. --}}
                    <div class="flex size-52 items-center justify-center rounded-xl border border-border bg-card p-3">{!! $user->twoFactorQrCodeSvg() !!}</div>
                    <div>
                        <p class="text-sm font-semibold text-card-foreground">{{ __('ui.security.cannot_scan') }}</p>
                        <p class="mt-1 text-sm leading-6 text-muted-foreground">{{ __('ui.security.manual_key') }}</p>
                        @if ($twoFactorSecret)
                            <code class="mt-3 block break-all rounded-lg border border-border bg-muted px-3 py-2 text-sm font-semibold tracking-wider text-card-foreground">{{ $twoFactorSecret }}</code>
                        @endif
                        <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end">
                            @csrf
                            <div class="min-w-0 flex-1"><label for="two-factor-code" class="mb-2 block text-sm font-medium text-foreground">{{ __('ui.security.confirm') }}</label><input id="two-factor-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required class="block w-full rounded-lg border border-input bg-card px-3 py-2.5 text-sm tracking-[0.3em] text-card-foreground outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/15" placeholder="000000"></div>
                            <x-admin.button type="submit" icon="save">{{ __('ui.security.confirm') }}</x-admin.button>
                        </form>
                    </div>
                </div>
            </section>
        @else
            <section class="rounded-2xl border border-success/30 bg-card shadow-sm shadow-border/40">
                <div class="flex flex-col gap-5 p-6 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex gap-4">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-success/10 text-success"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3 4 6v5c0 5 3.4 8.5 8 10 4.6-1.5 8-5 8-10V6l-8-3Z"/><path d="m8.5 12 2.2 2.2 4.8-5"/></svg></span>
                        <div><h2 class="font-semibold text-card-foreground">{{ __('ui.security.enabled_title') }}</h2><p class="mt-1 text-sm leading-6 text-muted-foreground">{{ __('ui.security.enabled_description') }}</p></div>
                    </div>
                    <form method="POST" action="{{ route('two-factor.disable') }}" class="shrink-0">@csrf @method('DELETE')<x-admin.button type="submit" variant="danger" icon="trash">{{ __('ui.security.disable') }}</x-admin.button></form>
                </div>
                <div class="border-t border-success/20 p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"><div><h3 class="font-semibold text-card-foreground">{{ __('ui.security.recovery_codes') }}</h3><p class="mt-1 text-sm leading-6 text-muted-foreground">{{ __('ui.security.recovery_description') }}</p></div><form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">@csrf<x-admin.button type="submit" variant="secondary" icon="arrow-right">{{ __('ui.security.create_new_codes') }}</x-admin.button></form></div>
                    <div class="mt-4 grid gap-2 rounded-xl border border-border bg-muted p-4 font-mono text-sm text-card-foreground sm:grid-cols-2">@foreach ($user->recoveryCodes() as $recoveryCode)<code>{{ $recoveryCode }}</code>@endforeach</div>
                </div>
            </section>
        @endif
    </div>
</x-layouts.auth>
