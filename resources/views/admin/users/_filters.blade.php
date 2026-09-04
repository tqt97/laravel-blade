<form method="GET" action="{{ route('admin.users.index') }}"
    class="grid gap-3 border-b border-border bg-muted/50 p-5 sm:grid-cols-2 xl:grid-cols-[minmax(16rem,1.5fr)_repeat(4,minmax(9rem,1fr))_auto]">
    <div class="sm:col-span-2 xl:col-span-1">
        <label for="users-search" class="mb-1.5 block text-xs font-medium text-muted-foreground">{{ __('ui.users.search') }}</label>
        <input id="users-search" type="search" name="search" value="{{ request('search') }}"
            placeholder="{{ __('ui.users.search_placeholder') }}"
            class="block w-full rounded-md border border-input bg-card px-3 py-2 text-sm text-card-foreground outline-none transition placeholder:text-muted-foreground focus:border-primary focus:ring-4 focus:ring-primary/15">
    </div>

    <div>
        <label for="users-verification" class="mb-1.5 block text-xs font-medium text-muted-foreground">{{ __('ui.users.verification') }}</label>
        <select id="users-verification" name="verification" class="ui-action w-full rounded-md border border-input bg-card px-3 py-2 text-sm text-card-foreground outline-none focus:border-primary focus:ring-4 focus:ring-primary/15">
            <option value="all">{{ __('ui.users.all_verification') }}</option>
            <option value="verified" @selected(request('verification') === 'verified')>{{ __('ui.users.verified') }}</option>
            <option value="unverified" @selected(request('verification') === 'unverified')>{{ __('ui.users.unverified') }}</option>
        </select>
    </div>

    <div>
        <label for="users-role" class="mb-1.5 block text-xs font-medium text-muted-foreground">{{ __('ui.users.role') }}</label>
        <select id="users-role" name="role" class="ui-action w-full rounded-md border border-input bg-card px-3 py-2 text-sm text-card-foreground outline-none focus:border-primary focus:ring-4 focus:ring-primary/15">
            <option value="all">{{ __('ui.users.all_roles') }}</option>
            <option value="admin" @selected(request('role') === 'admin')>{{ __('ui.users.admin') }}</option>
            <option value="user" @selected(request('role') === 'user')>{{ __('ui.users.user') }}</option>
        </select>
    </div>

    <div>
        <label for="users-two-factor" class="mb-1.5 block text-xs font-medium text-muted-foreground">{{ __('ui.users.two_factor') }}</label>
        <select id="users-two-factor" name="two_factor" class="ui-action w-full rounded-md border border-input bg-card px-3 py-2 text-sm text-card-foreground outline-none focus:border-primary focus:ring-4 focus:ring-primary/15">
            <option value="all">{{ __('ui.users.all_2fa') }}</option>
            <option value="enabled" @selected(request('two_factor') === 'enabled')>{{ __('ui.users.enabled') }}</option>
            <option value="disabled" @selected(request('two_factor') === 'disabled')>{{ __('ui.users.disabled') }}</option>
        </select>
    </div>

    <div>
        <label for="users-status" class="mb-1.5 block text-xs font-medium text-muted-foreground">{{ __('ui.users.status') }}</label>
        <select id="users-status" name="status" class="ui-action w-full rounded-md border border-input bg-card px-3 py-2 text-sm text-card-foreground outline-none focus:border-primary focus:ring-4 focus:ring-primary/15">
            <option value="active" @selected(request('status', 'active') === 'active')>{{ __('ui.users.active') }}</option>
            <option value="all" @selected(request('status') === 'all')>{{ __('ui.users.all_statuses') }}</option>
            <option value="deleted" @selected(request('status') === 'deleted')>{{ __('ui.users.deleted') }}</option>
        </select>
    </div>

    <div>
        <label for="users-per-page" class="mb-1.5 block text-xs font-medium text-muted-foreground">{{ __('ui.users.per_page_label') }}</label>
        <select id="users-per-page" name="per_page" class="ui-action w-full rounded-md border border-input bg-card px-3 py-2 text-sm text-card-foreground outline-none focus:border-primary focus:ring-4 focus:ring-primary/15">
            @foreach ([15, 30, 50] as $pageSize)
                <option value="{{ $pageSize }}" @selected((int) request('per_page', 15) === $pageSize)>{{ __('ui.users.per_page', ['count' => $pageSize]) }}</option>
            @endforeach
        </select>
    </div>

    <x-admin.button type="submit" icon="arrow-right" compact class="self-end">{{ __('ui.users.apply') }}</x-admin.button>
</form>
