<?php

namespace App\Providers;

use App\Services\Admin\AdminBar;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\ViewErrorBag;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped y no un binding cualquiera: la pantalla pública escribe en él
        // el registro que está enseñando durante `mount()`, y el layout lo lee
        // al pintar la barra. Dos instancias serían una barra vacía.
        $this->app->scoped(AdminBar::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->shareDefaultErrorBag();
    }

    /**
     * Share an empty error bag so views never depend on the session having started.
     *
     * Error pages (404, 503…) are rendered by the exception handler outside the
     * `web` group when the router rejects the URL, so ShareErrorsFromSession never
     * runs. The public layout renders Flux fields, which read `$errors`, so the
     * missing variable makes those pages fall back to the framework's bare error
     * page. ShareErrorsFromSession overwrites this bag on regular requests.
     */
    protected function shareDefaultErrorBag(): void
    {
        View::share('errors', new ViewErrorBag);
    }
}
