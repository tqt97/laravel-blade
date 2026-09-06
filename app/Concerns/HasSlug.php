<?php

namespace App\Concerns;

use Illuminate\Support\Str;

trait HasSlug
{
    protected static function bootHasSlug(): void
    {
        static::saving(function (self $model): void {
            $model->generateSlug();
        });
    }

    protected function generateSlug(): void
    {
        if (! blank($this->{$this->slugColumn()})) {
            return;
        }

        $source = $this->{$this->slugSourceColumn()};

        if (blank($source)) {
            return;
        }

        $this->{$this->slugColumn()} = $this->uniqueSlug($source);
    }

    protected function uniqueSlug(string $value): string
    {
        $base = Str::slug($value);
        $slug = $base;
        $counter = 2;

        while ($this->slugExists($slug)) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    protected function slugExists(string $slug): bool
    {
        return static::query()
            ->when(
                $this->exists,
                fn ($query) => $query->whereKeyNot($this->getKey())
            )
            ->where($this->slugColumn(), $slug)
            ->exists();
    }

    protected function slugColumn(): string
    {
        return 'slug';
    }

    protected function slugSourceColumn(): string
    {
        return 'title';
    }
}
