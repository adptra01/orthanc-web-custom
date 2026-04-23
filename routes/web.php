<?php

use App\Http\Controllers\Orthanc\DicomUploadController;
use App\Http\Controllers\Orthanc\PatientController;
use App\Http\Controllers\Orthanc\StudyController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::view('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');
    });

Route::middleware(['auth'])->group(function () {
    Route::livewire('invitations/{invitation}/accept', 'pages::teams.accept-invitation')->name('invitations.accept');
});

/*
|--------------------------------------------------------------------------
| Orthanc Gateway Routes
|--------------------------------------------------------------------------
|
| MVP: mengembalikan JSON. Tahap 5 akan menambahkan view Livewire.
| Otorisasi menggunakan middleware alias Spatie `permission:<name>`
| yang telah didaftarkan di bootstrap/app.php.
|
*/
Route::middleware(['auth'])
    ->prefix('orthanc')
    ->name('orthanc.')
    ->group(function () {
        Route::get('patients', [PatientController::class, 'index'])
            ->middleware('permission:view patients')
            ->name('patients.index');

        Route::get('patients/{orthancId}', [PatientController::class, 'show'])
            ->middleware('permission:view patients')
            ->whereAlphaNumeric('orthancId')
            ->name('patients.show');

        Route::get('studies/{orthancId}', [StudyController::class, 'show'])
            ->middleware('permission:view studies')
            ->name('studies.show');

        Route::get('studies/{orthancId}/viewer', [StudyController::class, 'viewer'])
            ->middleware('permission:view studies')
            ->name('studies.viewer');

        Route::post('instances', [DicomUploadController::class, 'store'])
            ->middleware('permission:upload dicom')
            ->name('instances.store');

        // UI routes (Livewire) — terpisah dari JSON gateway.
        Route::view('ui/patients', 'pages.orthanc.patients')
            ->middleware('permission:view patients')
            ->name('ui.patients');
    });

require __DIR__.'/settings.php';
