@csrf
<div class="grid gap-5 sm:grid-cols-2">
    <div><label for="name" class="mb-2 block text-sm font-semibold">{{ __('booking.admin.name') }}</label><input
            id="name" name="name" value="{{ old('name', $resource->name) }}" required
            class="ui-input w-full">@error('name')
            <p class="mt-1 text-xs text-destructive">{{ $message }}</p>@enderror
    </div>
    <div><label for="slug" class="mb-2 block text-sm font-semibold">{{ __('booking.admin.slug') }}</label><input
            id="slug" name="slug" value="{{ old('slug', $resource->slug) }}" required
            class="ui-input w-full">@error('slug')
            <p class="mt-1 text-xs text-destructive">{{ $message }}</p>@enderror
    </div>
</div>
<div><label for="timezone" class="mb-2 block text-sm font-semibold">{{ __('booking.admin.timezone') }}</label><input
        id="timezone" name="timezone" value="{{ old('timezone', $resource->timezone) }}" required
        class="ui-input w-full">@error('timezone')
        <p class="mt-1 text-xs text-destructive">{{ $message }}</p>@enderror
</div>
<div><label for="description"
        class="mb-2 block text-sm font-semibold">{{ __('booking.admin.description') }}</label><textarea id="description"
        name="description" rows="4"
        class="ui-input w-full">{{ old('description', $resource->description) }}</textarea>@error('description')
        <p class="mt-1 text-xs text-destructive">{{ $message }}</p>@enderror
</div>
<label class="flex items-center gap-3 text-sm"><input type="checkbox" name="is_active" value="1"
        @checked(old('is_active', $resource->exists ? $resource->is_active : true))
        class="rounded border-border text-primary focus:ring-primary">{{ __('booking.admin.active') }}</label>
<div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><x-admin.button
        :href="route('admin.resources.index')"
        variant="secondary">{{ __('ui.actions.cancel') }}</x-admin.button><x-admin.button type="submit"
        icon="save">{{ $submitLabel }}</x-admin.button></div>
