<?php

namespace App\Providers;

use App\Policies\BasePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        BasePolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // $this->registerPolicies();

        // Gate::define('create', [BasePolicy::class, 'create']);
        // Gate::define('read', [BasePolicy::class, 'read']);
        // Gate::define('update', [BasePolicy::class, 'update']);
        // Gate::define('delete', [BasePolicy::class, 'delete']);
        // Gate::define('import', [BasePolicy::class, 'import']);
        // Gate::define('export', [BasePolicy::class, 'export']);
        // Gate::define('print', [BasePolicy::class, 'print']);
        // Gate::define('upload', [BasePolicy::class, 'upload']);
    }
}
