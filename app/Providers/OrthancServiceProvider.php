<?php

namespace App\Providers;

use App\Services\Orthanc\OrthancPatientService;
use App\Services\Orthanc\OrthancService;
use App\Services\Orthanc\OrthancStudyService;
use Illuminate\Support\ServiceProvider;

class OrthancServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Base service sebagai singleton — aman karena stateless setelah
        // konstruktor membaca config sekali.
        $this->app->singleton(OrthancService::class);
        $this->app->singleton(OrthancPatientService::class);
        $this->app->singleton(OrthancStudyService::class);
    }

    public function boot(): void
    {
        //
    }
}
