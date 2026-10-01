<?php

declare(strict_types=1);

namespace Focal\Sales;

use Focal\Core\Models\Company;
use Focal\Core\Models\Contact;
use Focal\Sales\Console\Commands\ExpireStaleQuotesCommand;
use Focal\Sales\Console\Commands\ProcessCadencesCommand;
use Focal\Sales\Models\Deal;
use Focal\Sales\Models\SalesSequenceEnrollment;
use Illuminate\Support\ServiceProvider;

class SalesServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/focal-sales.php',
            'focal-sales'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'focal-sales');
        if (config('focal-sales.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        if (class_exists(Contact::class)) {
            Contact::resolveRelationUsing('deals', function (Contact $contact) {
                return $contact->belongsToMany(
                    Deal::class,
                    config('focal-core.tables.associations', 'focal_associations'),
                    'child_id',
                    'parent_id'
                )
                    ->wherePivot('child_type', $contact->getMorphClass())
                    ->wherePivot('parent_type', (new Deal)->getMorphClass())
                    ->withPivot(['id', 'type'])
                    ->withTimestamps();
            });

            Contact::resolveRelationUsing('salesSequenceEnrollments', function (Contact $contact) {
                return $contact->hasMany(SalesSequenceEnrollment::class, 'contact_id')->orderBy('created_at', 'desc');
            });
        }

        if (class_exists(Company::class)) {
            Company::resolveRelationUsing('deals', function (Company $company) {
                return $company->belongsToMany(
                    Deal::class,
                    config('focal-core.tables.associations', 'focal_associations'),
                    'child_id',
                    'parent_id'
                )
                    ->wherePivot('child_type', $company->getMorphClass())
                    ->wherePivot('parent_type', (new Deal)->getMorphClass())
                    ->withPivot(['id', 'type'])
                    ->withTimestamps();
            });
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessCadencesCommand::class,
                ExpireStaleQuotesCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/focal-sales.php' => config_path('focal-sales.php'),
            ], 'focal-sales-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'focal-sales-migrations');
        }
    }
}
