<?php

declare(strict_types=1);

namespace Hmmdlthf\FilamentRefineFilter;

use Illuminate\Support\ServiceProvider;

/**
 * RefineFilter is a plain Filament\Tables\Filters\Filter subclass, so unlike
 * a Panel plugin it does not need ->plugins([...]) registration. This
 * provider only exists to autoload translations. If v2 adds published
 * views/assets (e.g. a "View All" modal), they get registered here too.
 */
class RefineFilterServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'filament-refine-filter');

        $this->publishes([
            __DIR__ . '/../resources/lang' => $this->app->langPath('vendor/filament-refine-filter'),
        ], 'filament-refine-filter-translations');
    }
}
