<?php

namespace App\View\Components\Cinema;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Illuminate\View\Component;
use Illuminate\View\View;

final class PublicMenu extends Component
{
    /** @return array<int, array{key:string, href:string, label:string, description:string}> */
    private function buildNavigationItems(): array
    {
        if (! config('cinema.public_navigation.enabled', true)) {
            return [];
        }

        $prefix = (string) config('cinema.public_navigation.route_prefix', 'cinema.');
        $suffix = (string) config('cinema.public_navigation.index_suffix', '.index');
        $excluded = config('cinema.public_navigation.excluded_features', []);
        $features = [];

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            $name = $route->getName();
            if (! is_string($name) || ! str_starts_with($name, $prefix) || ! str_ends_with($name, $suffix) || ! in_array('GET', $route->methods(), true)) {
                continue;
            }
            $feature = Str::before(Str::after($name, $prefix), $suffix);
            if ($feature === '' || in_array($feature, $excluded, true)) {
                continue;
            }
            $features[$feature] = $this->feature($feature, $route);
        }

        uasort($features, fn (array $first, array $second): int => $first['label'] <=> $second['label']);

        return array_values($features);
    }

    /** @return array{key:string, href:string, label:string, description:string} */
    private function feature(string $key, Route $route): array
    {
        $labelKey = 'cinema.public.menu.'.$key;
        $descriptionKey = $labelKey.'_description';

        return ['key' => $key, 'href' => route($route->getName()), 'label' => __($labelKey) !== $labelKey ? __($labelKey) : Str::headline($key), 'description' => __($descriptionKey) !== $descriptionKey ? __($descriptionKey) : ''];
    }

    public function render(): View
    {
        return view('components.cinema.public-menu', ['entries' => $this->buildNavigationItems()]);
    }
}
