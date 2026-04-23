# TODO — Laravel Orthanc PACS Gateway MVP (Bertahap)

Sumber rencana: `.guidelines/laravel-orthanc-pacs-gateway-mvp.plan.md`

## Tahap 1 — Fondasi Konfigurasi & Paket

- [x] Install core packages (spatie/laravel-permission, spatie/laravel-activitylog, jantinnerezo/livewire-alert)
- [x] Install dev packages (barryvdh/laravel-debugbar, barryvdh/laravel-ide-helper)
- [x] Publish vendor untuk Spatie Permission & Activitylog
- [x] Jalankan `php artisan migrate`
- [x] Buat `config/orthanc.php`
- [x] Tambahkan env keys Orthanc ke `.env.example` (+ sync ke `.env`)
- [x] Validasi: `php artisan config:show orthanc`

## Tahap 2 — RBAC & Audit Baseline

- [x] Buat `database/seeders/OrthancRoleSeeder.php`
- [x] Register seeder di `DatabaseSeeder`
- [x] Update `app/Models/User.php` (HasRoles + LogsActivity + getActivitylogOptions)
- [x] Registrasi middleware alias Spatie via `bootstrap/app.php` mengikuti struktur Laravel saat ini (bukan `app/Http/Kernel.php`) dan dokumentasi resmi spatie/laravel-permission:
      `->withMiddleware(function (Middleware $middleware) { $middleware->alias([...Spatie middlewares...]); })`
- [x] Seed & validasi role/permission (4 roles, 8 permissions)

## Tahap 3 — Service Layer Orthanc

- [x] `app/Services/Orthanc/OrthancService.php` (base: basic auth, timeout, SSL, error handling, logging)
- [x] `OrthancPatientService`, `OrthancStudyService` (delete via `deleteResource()` — bug rekursi plan dihindari)
- [x] `app/Services/OrthancAuditLogger.php` (log name `orthanc`, metadata IP/UA, PHI-free)
- [x] Register `OrthancServiceProvider` di `bootstrap/providers.php` (Laravel 11+/13 style)

## Tahap 4 — Endpoint HTTP Minimal

- [x] `PatientController` (index/show), `StudyController` (show/viewer), `DicomUploadController` (store)
- [x] `UploadDicomRequest` (validasi file + max size dari config)
- [x] Route `/orthanc` + middleware `auth` + `permission:<name>` (5 routes)
- [x] Validasi via `php artisan route:list --path=orthanc`

## Tahap 5 — UI Livewire Bertahap

- [x] `PatientList` Livewire + Blade (`app/Livewire/Orthanc/PatientList.php`, view `resources/views/livewire/orthanc/patient-list.blade.php`)
- [x] Halaman pembungkus `resources/views/pages/orthanc/patients.blade.php` + route `orthanc.ui.patients` (guarded `permission:view patients`)
- [x] Menu sidebar "Orthanc PACS → Patients" (guarded `@can('view patients')`)
- [x] Integrasi `jantinnerezo/livewire-alert` (config published, facade OK)
- [ ] `PatientDetail`, `StudyList`, `StudyViewer` (iframe), `DicomUploadForm` (iterasi berikutnya)

## Tahap 6 — Testing & Hardening

- [ ] Feature test auth/permission
- [ ] Unit test service layer (HTTP mocked)
- [ ] Edge case & validasi manual
