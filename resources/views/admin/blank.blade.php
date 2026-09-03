<x-layouts.auth title="{{ __('ui.blank.title') }}" heading="{{ __('ui.blank.title') }}">
    <x-slot:breadcrumbs>
        <x-admin.breadcrumbs :items="[['label' => __('ui.blank.title')]]" />
    </x-slot:breadcrumbs>
    <div class="mx-auto max-w-7xl space-y-6">
        <x-admin.page-header title="{{ __('ui.blank.title') }}" description="{{ __('ui.blank.description') }}">
            <x-slot:actions>
                <x-admin.button variant="secondary" disabled>{{ __('ui.actions.no_actions') }}</x-admin.button>
            </x-slot:actions>
        </x-admin.page-header>

        <x-admin.blank-state title="{{ __('ui.blank.build_title') }}"
            description="{{ __('ui.blank.build_description') }}" />
    </div>
</x-layouts.auth>
