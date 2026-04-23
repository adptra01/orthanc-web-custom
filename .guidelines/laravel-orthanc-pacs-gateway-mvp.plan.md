# Plan: Laravel-Orthanc PACS Gateway MVP

## Summary
Implement a production-ready Laravel-based Applicative Gateway that proxies Orthanc PACS API with authentication, RBAC, and PHI protection. Uses Spatie packages for permission-based authorization and audit logging, with Livewire 4 + Flux 2 for reactive UI.

## User Story
As a medical professional (Administrator, Radiologist, Physician),
I want a secure web interface to access and manage DICOM images through Orthanc,
So that I can view patient studies and perform radiology tasks without exposing the PACS server directly to the internet.

## Problem → Solution
**Current State**: Orthanc PACS server is accessible only on local network, requiring direct access or VPN. No role-based access control, no audit logging, PHI exposed in URLs/headers.

**Desired State**: Secure web gateway with Fortify authentication, Spatie Permission for RBAC, Spatie Activitylog for compliance audit logging, and PHI protection by storing only Orthanc IDs in Laravel database.

## Metadata
- **Complexity**: XL (25+ files, new subsystems, external API integration)
- **Source PRD**: `.guidelines/prd.md`
- **PRD Phase**: MVP Implementation
- **Estimated Files**: 32 files
- **Data Approach**: Arrays (no DTOs)
- **Core Packages**: spatie/laravel-permission, spatie/laravel-activitylog, jantinnerezo/livewire-alert
- **Dev Packages**: barryvdh/laravel-debugbar, barryvdh/laravel-ide-helper

---

## UX Design

### Before
```
┌─────────────────────────────────────────────────────────────┐
│  Current State                                              │
│  ┌────────────────────────────────────────────────────────┐  │
│  │  No web interface                                      │  │
│  │  Users must connect directly to Orthanc:8042          │  │
│  │  No authentication beyond Orthanc basic auth          │  │
│  │  No role-based access control                         │  │
│  │  No audit logging                                     │  │
│  └────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

### After
```
┌─────────────────────────────────────────────────────────────┐
│  New State with Gateway                                     │
│  ┌────────────────────────────────────────────────────────┐  │
│  │  Users login via Laravel/Fortify                      │  │
│  │  RBAC: Admin, Radiologist, Physician, Patient         │  │
│  │  All requests proxied through Laravel                 │  │
│  │  Orthanc accessible only via internal network         │  │
│  │  Full audit logging of all access                     │  │
│  │  PHI protected - only Orthanc IDs stored             │  │
│  └────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

### Interaction Changes

| Touchpoint | Before | After | Notes |
|---|---|---|---|
| Login | Basic auth to Orthanc | Fortify login with 2FA | Email/password, optional 2FA |
| Patient List | Direct Orthanc API | Proxy via Laravel + Spatie Permission | Filtered by user permissions |
| Study Access | Full access to all | RBAC-filtered | Physician sees only referrals |
| Audit | None | Spatie Activitylog | Full audit trail in activity_log table |
| Notifications | None | Livewire Alert | Flash messages for success/error |
| PHI Storage | In Orthanc (exposed) | Orthanc only | Laravel stores only IDs |

---

## Mandatory Reading

Files that MUST be read before implementing:

| Priority | File | Lines | Why |
|---|---|---|---|
| P0 (critical) | `.env` | 1-73 | Orthanc connection config |
| P0 (critical) | `config/fortify.php` | 1-158 | Authentication setup |
| P1 (important) | `app/Models/User.php` | 1-49 | User model for RBAC extension |
| P1 (important) | `routes/web.php` | 1-22 | Route structure pattern |
| P2 (reference) | `app/Http/Middleware/EnsureTeamMembership.php` | 1-35 | Middleware pattern reference |

## External Documentation

| Topic | Source | Key Takeaway |
|---|---||
| Orthanc REST API | https://book.orthanc-server.com/users/rest.html | Endpoints: /patients, /studies, /series, /instances, /tools/find |
| Stone Web Viewer | https://orthanc.uclouvain.be/book/users/web-viewer.html | Embed via iframe with study ID |
| Laravel Fortify | https://laravel.com/docs/11.x/fortify | Features: 2FA, email verification, password reset |
| Livewire 4 | https://livewire.laravel.com/docs/installation | Reactive components with wire: directives |
| Flux Components | https://fluxui.dev/components | Pre-built UI components |
| Spatie Permission | https://spatie.be/docs/laravel-permission/v6 | Roles, permissions, middleware: role, permission |
| Spatie Activitylog | https://spatie.be/docs/laravel-activitylog/v5 | Activity logging with activity() facade |
| Livewire Alert | https://github.com/jantinnerezo/livewire-alert | Flash messages for Livewire |

---

## Patterns to Mirror

Code patterns discovered in the codebase. Follow these exactly.

### NAMING_CONVENTION
```php
// SOURCE: app/Models/User.php:16-17
#[Fillable(['name', 'email', 'password', 'current_team_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
```
- Use PHP 8.3 attributes for Fillable and Hidden
- Array syntax for attribute values
- PascalCase for class names, camelCase for methods

### CONTROLLER_PATTERN
```php
// SOURCE: app/Http/Controllers/Controller.php:1-20
<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
```
- Extend BaseController
- Use traits for authorization and validation
- Namespace: `App\Http\Controllers`

### MIGRATION_PATTERN
```php
// SOURCE: database/migrations/0001_01_01_000000_create_users_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```
- Return anonymous class extending Migration
- Use `Schema::create()` with callback
- Use `Blueprint` for column definitions
- Return type `: void` for methods

### ROUTE_PATTERN
```php
// SOURCE: routes/web.php:11-15
Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');
    });
```
- Use `Route::prefix()` for grouping
- Apply middleware as array
- Use `Route::view()` for simple views
- Chain `->name()` for named routes

### SERVICE_LAYER_PATTERN (to follow)
```php
// Create new pattern for Orthanc service
// Use Dependency Injection in constructors
// Return arrays from external API calls (no DTOs)
// Use typed return declarations (array)
```

### MIDDLEWARE_PATTERN
```php
// SOURCE: app/Http/Middleware/EnsureTeamMembership.php:13-24
public function handle(Request $request, Closure $next): Response
{
    $team = $request->route('current_team');

    if (! $request->user()?->teams->contains($team)) {
        abort(403);
    }

    return $next($request);
}
```
- Type hint Request and Closure
- Use nullsafe operator `?->`
- Return typed Response
- Use `abort()` for authorization failures

### LIVEWIRE_COMPONENT_PATTERN
```php
// Create new pattern for Orthanc Livewire components
// Use #[Locked] for public properties that shouldn't be updated from client
// Use wire:model for two-way binding
// Use wire:click for actions
```

---

## Files to Change

| File | Action | Justification |
|---|---|---|
| `app/Services/Orthanc/OrthancService.php` | CREATE | Service layer for Orthanc API communication |
| `app/Services/Orthanc/OrthancPatientService.php` | CREATE | Patient-specific operations |
| `app/Services/Orthanc/OrthancStudyService.php` | CREATE | Study-specific operations |
| `app/Services/Orthanc/OrthancSeriesService.php` | CREATE | Series-specific operations |
| `app/Services/Orthanc/OrthancInstanceService.php` | CREATE | Instance-specific operations |
| `app/Services/OrthancAuditLogger.php` | CREATE | Helper service for activity logging |
| `database/seeders/OrthancRoleSeeder.php` | CREATE | Setup roles and permissions |
| `app/Http/Controllers/Orthanc/PatientController.php` | CREATE | Patient list and detail endpoints |
| `app/Http/Controllers/Orthanc/StudyController.php` | CREATE | Study list and detail endpoints |
| `app/Http/Controllers/Orthanc/SeriesController.php` | CREATE | Series list and detail endpoints |
| `app/Http/Controllers/Orthanc/InstanceController.php` | CREATE | Instance list and detail endpoints |
| `app/Http/Controllers/Orthanc/DicomUploadController.php` | CREATE | DICOM file upload handler |
| `app/Http/Requests/Orthanc/UploadDicomRequest.php` | CREATE | Form request for DICOM upload validation |
| `app/Livewire/Patients/PatientList.php` | CREATE | Livewire component for patient list |
| `app/Livewire/Patients/PatientDetail.php` | CREATE | Livewire component for patient detail |
| `app/Livewire/Studies/StudyList.php` | CREATE | Livewire component for study list |
| `app/Livewire/Studies/StudyViewer.php` | CREATE | Livewire component for Stone Web Viewer |
| `resources/views/livewire/patients/patient-list.blade.php` | CREATE | Patient list Blade template |
| `resources/views/livewire/patients/patient-detail.blade.php` | CREATE | Patient detail Blade template |
| `resources/views/livewire/studies/study-list.blade.php` | CREATE | Study list Blade template |
| `resources/views/livewire/studies/study-viewer.blade.php` | CREATE | Study viewer with Stone Web Viewer |
| `resources/views/orthanc/upload.blade.php` | CREATE | DICOM upload form |
| `config/orthanc.php` | CREATE | Orthanc configuration file |
| `app/Providers/OrthancServiceProvider.php` | CREATE | Service provider for Orthanc services |
| `app/Http/Kernel.php` | UPDATE | Add Spatie middleware aliases |
| `app/Models/User.php` | UPDATE | Add HasRoles and LogsActivity traits |
| `routes/web.php` | UPDATE | Add Orthanc routes with permission middleware |
| `config/app.php` | UPDATE | Register OrthancServiceProvider |
| `composer.json` | UPDATE | Add required packages |
| `.env` | UPDATE | Add debugbar enable/disable |

## Required Packages

### Core Packages (Wajib)

#### spatie/laravel-permission
- **Fungsi**: Manajemen role dan permission RBAC
- **Keuntungan**:
  - Middleware built-in: `@can`, `@role`, `role:admin|radiologist`
  - Cache untuk performa tinggi
  - Assign role/permission ke user dengan mudah
  - Permission-based access control (bukan hanya role-based)
- **Contoh**:
  ```php
  $user->assignRole('radiologist');
  $user->givePermissionTo('view studies', 'delete patients');
  $user->hasRole('administrator'); // bool
  $user->can('delete studies'); // bool
  ```
- **Middleware**: `role:administrator`, `permission:delete patients`

#### spatie/laravel-activitylog
- **Fungsi**: Audit trail otomatis untuk compliance medis
- **Keuntungan**:
  - Log otomatis untuk model Eloquent (before/after)
  - Custom event logging dengan properties
  - Menyimpan: who, what, when, properties, changes
- **Contoh**:
  ```php
  activity()
      ->causedBy(auth()->user())
      ->withProperties([
          'orthanc_id' => $id,
          'ip_address' => request()->ip(),
      ])
      ->log('viewed patient study');
  ```

### UI Packages

#### jantinnerezo/livewire-alert
- **Fungsi**: Alert/notifikasi UI berbasis Livewire
- **Keuntungan**:
  - Flash messages dengan berbagai tipe (success, error, warning, info)
  - Auto-dismiss dengan timer
  - Integrasi mudah dengan Livewire
- **Contoh**:
  ```php
  Alert::success('DICOM file uploaded successfully');
  Alert::error('Failed to delete study');
  ```
- **Usage**: `@livewire('livewire-alert')` di layout

### Development Packages (dev only)

#### barryvdh/laravel-debugbar
- **Fungsi**: Debug query database, performa, dan request
- **Keuntungan**:
  - Lihat semua query yang dijalankan
  - Cek performa dan memory usage
  - Debug request/response
- **Install**: `--dev`

#### barryvdh/laravel-ide-helper
- **Fungsi**: Auto-complete di IDE untuk facades dan models
- **Keuntungan**:
  - Generate helper files untuk PhpStorm/VSCode
  - Auto-complete untuk facade methods
- **Install**: `--dev`

### Optional Packages

#### spatie/laravel-query-builder
- **Fungsi**: Filter, sort, dan paginate API dengan mudah
- **Gunakan jika**: Butuh API endpoint untuk filter/sort yang kompleks

#### laravel/sanctum
- **Fungsi**: Auth API ringan untuk mobile app / SPA
- **Gunakan jika**: Butuh API token authentication selain session-based

## Installation Commands

```bash
# Core packages
composer require spatie/laravel-permission
composer require spatie/laravel-activitylog
composer require jantinnerezo/livewire-alert

# Development packages
composer require --dev barryvdh/laravel-debugbar
composer require --dev barryvdh/laravel-ide-helper

# Run migrations and setup
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
php artisan permission:cache-reset

php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider"
php artisan migrate

php artisan vendor:publish --provider="Barryvdh\Debugbar\ServiceProvider"
```

## NOT Building

- **HL7 Integration**: Out of scope - requires separate plugin development
- **Long-term Archive**: No tape library or S3 archival in MVP
- **Modality Worklist**: DICOM modality worklists not in scope
- **Custom DICOM Anonymization**: Will use Orthanc's built-in /modify endpoint
- **Multi-tenant DICOM**: Single Orthanc instance in MVP
- **AI/ML Integration**: No machine learning for diagnosis in MVP

---

## Step-by-Step Tasks

### Task 0: Install Required Packages
- **ACTION**: Install all required packages via Composer
- **IMPLEMENT**: Run composer require commands for core and dev packages
- **MIRROR**: Follow Laravel package installation pattern
- **IMPORTS**: None needed
- **GOTCHA**: Run vendor:publish for Spatie packages after install
- **VALIDATE**: Check composer.json for packages, run php artisan migrate

```bash
# Core packages
composer require spatie/laravel-permission
composer require spatie/laravel-activitylog
composer require jantinnerezo/livewire-alert

# Development packages
composer require --dev barryvdh/laravel-debugbar
composer require --dev barryvdh/laravel-ide-helper

# Publish and migrate
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider"
php artisan migrate

# Setup IDE helper (optional)
php artisan ide-helper:generate
php artisan ide-helper:models
```

### Task 1: Create Orthanc Configuration
- **ACTION**: Create `config/orthanc.php` with Orthanc connection settings
- **IMPLEMENT**: Define base URL, credentials, timeout, and SSL verification settings
- **MIRROR**: Follow `config/fortify.php` structure for array-based config
- **IMPORTS**: None needed for config file
- **GOTCHA**: Ensure environment variables have fallbacks for development
- **VALIDATE**: Run `php artisan config:cache` and `php artisan config:show orthanc`

```php
<?php

return [
    'base_url' => env('ORTHANC_BASE_URL', 'http://localhost:8042'),
    'username' => env('ORTHANC_USERNAME', 'orthanc'),
    'password' => env('ORTHANC_PASSWORD', 'orthanc'),
    'timeout' => (int) env('ORTHANC_TIMEOUT', 10),
    'verify_ssl' => filter_var(env('ORTHANC_VERIFY_SSL', 'true'), FILTER_VALIDATE_BOOLEAN),
];
```

### Task 2: Setup Spatie Roles and Permissions
- **ACTION**: Create seeder to setup roles and permissions
- **IMPLEMENT**: Define Administrator, Radiologist, Physician roles with appropriate permissions
- **MIRROR**: Follow Laravel seeder pattern
- **IMPORTS**: `Spatie\Permission\Models\Role`, `Spatie\Permission\Models\Permission`, `Illuminate\Database\Seeder`
- **GOTCHA**: Run seeder after migrations, use `php artisan db:seed`
- **VALIDATE**: Check roles and permissions in database

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class OrthancRoleSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'view patients',
            'view studies',
            'view series',
            'view instances',
            'upload dicom',
            'delete patients',
            'delete studies',
            'annotate studies',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign permissions
        $admin = Role::firstOrCreate(['name' => 'Administrator']);
        $admin->givePermissionTo(Permission::all());

        $radiologist = Role::firstOrCreate(['name' => 'Radiologist']);
        $radiologist->givePermissionTo([
            'view patients',
            'view studies',
            'view series',
            'view instances',
            'upload dicom',
            'annotate studies',
        ]);

        $physician = Role::firstOrCreate(['name' => 'Physician']);
        $physician->givePermissionTo([
            'view patients',
            'view studies',
            'view series',
            'view instances',
        ]);

        Role::firstOrCreate(['name' => 'Patient']);
    }
}
```

### Task 3: Create Orthanc Service Layer
- **ACTION**: Create base `OrthancService.php` for API communication
- **IMPLEMENT**: Guzzle HTTP client with authentication, error handling, logging
- **MIRROR**: Follow service pattern with typed return declarations
- **IMPORTS**: `Illuminate\Support\Facades\Http`, `Illuminate\Support\Facades\Log`
- **GOTCHA**: Handle Orthanc API errors (404, 500) gracefully, log all API calls
- **VALIDATE**: Test with real Orthanc instance, verify authentication works

```php
<?php

namespace App\Services\Orthanc;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OrthancService
{
    protected string $baseUrl;
    protected string $username;
    protected string $password;
    protected int $timeout;
    protected bool $verifySsl;

    public function __construct()
    {
        $config = config('orthanc');
        $this->baseUrl = rtrim($config['base_url'], '/');
        $this->username = $config['username'];
        $this->password = $config['password'];
        $this->timeout = $config['timeout'];
        $this->verifySsl = $config['verify_ssl'];
    }

    protected function request(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        Log::debug('Orthanc API Request', [
            'method' => $method,
            'endpoint' => $endpoint,
        ]);

        $response = Http::timeout($this->timeout)
            ->withBasicAuth($this->username, $this->password)
            ->withoutVerifying(!$this->verifySsl)
            ->send($method, $url, $data);

        if (! $response->successful()) {
            Log::error('Orthanc API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException("Orthanc API request failed: {$response->status()}");
        }

        return $response->json();
    }

    public function get(string $endpoint): array
    {
        return $this->request('GET', $endpoint);
    }

    public function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $endpoint, $data);
    }

    public function delete(string $endpoint): void
    {
        $this->request('DELETE', $endpoint);
    }
}
```

### Task 5: Create Orthanc Sub-Services
- **ACTION**: Create PatientService, StudyService, SeriesService, InstanceService
- **IMPLEMENT**: Extend OrthancService, implement specific CRUD operations
- **MIRROR**: Follow base service pattern, return arrays (no DTOs)
- **IMPORTS**: `App\Services\Orthanc\OrthancService`
- **GOTCHA**: Use Orthanc's /tools/find for searches, /{resource}/{id} for details
- **VALIDATE**: Test each service method against Orthanc API documentation

```php
<?php

namespace App\Services\Orthanc;

class OrthancPatientService extends OrthancService
{
    /**
     * Get all patients
     *
     * @return array<int, array{orthancId: string, patientId: string, patientName: string, ...}>
     */
    public function getAll(): array
    {
        $patientIds = $this->get('/patients');

        return array_map(
            fn (string $id) => $this->getById($id),
            $patientIds
        );
    }

    /**
     * Get patient by Orthanc ID
     *
     * @return array{orthancId: string, patientId: string, patientName: string, ...}
     */
    public function getById(string $orthancId): array
    {
        $data = $this->get("/patients/{$orthancId}");
        $tags = $data['MainDicomTags'] ?? [];

        return [
            'orthancId' => $orthancId,
            'patientId' => $tags['PatientID'] ?? '',
            'patientName' => $tags['PatientName'] ?? 'Unknown',
            'birthDate' => $tags['PatientBirthDate'] ?? null,
            'sex' => $tags['PatientSex'] ?? null,
            'studiesCount' => count($data['Studies'] ?? []),
            'studies' => $data['Studies'] ?? [],
            'mainDicomTags' => $tags,
        ];
    }

    /**
     * Search patients by name (supports wildcard)
     *
     * @return array<int, array{orthancId: string, patientId: string, patientName: string, ...}>
     */
    public function search(string $query): array
    {
        $results = $this->post('/tools/find', [
            'Level' => 'Patient',
            'Query' => [
                'PatientName' => $query,
            ],
        ]);

        return array_map(
            fn (string $id) => $this->getById($id),
            $results
        );
    }

    /**
     * Get studies for a patient
     *
     * @return array<int, array{orthancId: string, studyDescription: string, ...}>
     */
    public function getStudies(string $orthancId): array
    {
        $data = $this->get("/patients/{$orthancId}");

        return array_map(
            fn (string $studyId) => app(OrthancStudyService::class)->getById($studyId),
            $data['Studies'] ?? []
        );
    }

    public function delete(string $orthancId): void
    {
        $this->delete("/patients/{$orthancId}");
    }
}
```

### Task 6: Create Orthanc Audit Log Helper
- **ACTION**: Create helper service for consistent audit logging using spatie/laravel-activitylog
- **IMPLEMENT**: Create OrthancAuditLogger service with methods for common actions
- **MIRROR**: Follow service pattern with static methods for convenience
- **IMPORTS**: `Spatie\Activitylog\Traits\LogsActivity`, `Illuminate\Support\Facades\Auth`, `Illuminate\Support\Facades\Request`
- **GOTCHA**: Use activity() facade globally, store IP and user agent for compliance
- **VALIDATE**: Test that logs appear in activity_log table

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Spatie\Activitylog\Contracts\Activity;

class OrthancAuditLogger
{
    public static function log(string $action, string $resourceType, ?string $orthancId = null, array $metadata = []): Activity
    {
        return activity()
            ->causedBy(Auth::user())
            ->withProperties(array_merge($metadata, [
                'orthanc_id' => $orthancId,
                'resource_type' => $resourceType,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]))
            ->log($action);
    }

    public static function logViewPatient(string $orthancId, array $patientData): void
    {
        self::log('viewed patient', 'patient', $orthancId, [
            'patient_name' => $patientData['patientName'] ?? null,
            'patient_id' => $patientData['patientId'] ?? null,
        ]);
    }

    public static function logViewStudy(string $orthancId, array $studyData): void
    {
        self::log('viewed study', 'study', $orthancId, [
            'study_description' => $studyData['studyDescription'] ?? null,
            'study_date' => $studyData['studyDate'] ?? null,
        ]);
    }

    public static function logUploadDicom(string $studyId, string $filename): void
    {
        self::log('uploaded DICOM', 'instance', $studyId, [
            'filename' => $filename,
        ]);
    }

    public static function logDeletePatient(string $orthancId): void
    {
        self::log('deleted patient', 'patient', $orthancId);
    }
}
```

### Task 7: Create RBAC Middleware (using Spatie)
- **ACTION**: Create CheckOrthancPermission middleware for permission-based access
- **IMPLEMENT**: Use spatie/laravel-permission's built-in middleware or create custom for Orthanc
- **MIRROR**: Spatie provides built-in `role` and `permission` middleware
- **IMPORTS**: `Illuminate\Http\Request`, `Closure`, `Illuminate\Http\Response`
- **GOTCHA**: Use `permission:permission-name` or `role:role-name` syntax
- **VALIDATE**: Test that each role can only access permitted resources

**NOTE**: Spatie/laravel-permission already provides built-in middleware:
- `role:admin|radiologist` - Check if user has any of these roles
- `permission:view studies` - Check if user has this permission

**Usage in routes**:
```php
Route::middleware(['permission:view patients'])->group(function () {
    // Routes that require 'view patients' permission
});

Route::middleware(['role:Administrator'])->group(function () {
    // Routes that require Administrator role only
});
```

**No custom middleware needed** - use Spatie's built-in middleware. Register in `app/Http/Kernel.php`:

```php
protected $middlewareAliases = [
    'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
    'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
    'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
];
```

```

### Task 8: Create Controllers
- **ACTION**: Create PatientController, StudyController, SeriesController, InstanceController, DicomUploadController
- **IMPLEMENT**: Proxy requests to Orthanc services, apply RBAC, log access
- **MIRROR**: Follow Controller pattern with AuthorizesRequests trait
- **IMPORTS**: `App\Http\Controllers\Controller`, `App\Services\Orthanc\*`
- **GOTCHA**: Log all access attempts, handle Orthanc errors gracefully
- **VALIDATE**: Test each endpoint with different user roles

```php
<?php

namespace App\Http\Controllers\Orthanc;

use App\Http\Controllers\Controller;
use App\Services\Orthanc\OrthancPatientService;
use App\Services\OrthancAuditLogger;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    public function __construct(
        private OrthancPatientService $patientService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('view patients');

        $search = $request->query('search');

        if ($search) {
            $patients = $this->patientService->search($search);
        } else {
            $patients = $this->patientService->getAll();
        }

        // Log access
        OrthancAuditLogger::log(
            $search ? 'searched patients' : 'listed patients',
            'patient',
            null,
            ['search' => $search]
        );

        return response()->json($patients);
    }

    public function show(string $orthancId)
    {
        $this->authorize('view patients');

        $patient = $this->patientService->getById($orthancId);

        OrthancAuditLogger::logViewPatient($orthancId, $patient);

        return response()->json($patient);
    }
}
```

### Task 9: Create DicomUploadController
- **ACTION**: Create controller for DICOM file uploads
- **IMPLEMENT**: Handle multipart form data, stream to Orthanc /instances endpoint
- **MIRROR**: Follow Controller pattern with validation
- **IMPORTS**: `App\Http\Controllers\Controller`, `Illuminate\Http\Request`, `Illuminate\Support\Facades\Http`
- **GOTCHA**: Large files - use streaming to avoid memory issues
- **VALIDATE**: Test with real DICOM files, verify they appear in Orthanc

```php
<?php

namespace App\Http\Controllers\Orthanc;

use App\Http\Controllers\Controller;
use App\Http\Requests\Orthanc\UploadDicomRequest;
use App\Services\OrthancAuditLogger;
use Illuminate\Support\Facades\Http;

class DicomUploadController extends Controller
{
    public function store(UploadDicomRequest $request)
    {
        $this->authorize('upload dicom');

        $file = $request->file('dicom');

        if (! $file) {
            return response()->json(['error' => 'No file provided'], 400);
        }

        $config = config('orthanc');
        $url = rtrim($config['base_url'], '/') . '/instances';

        $response = Http::timeout($config['timeout'])
            ->withBasicAuth($config['username'], $config['password'])
            ->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
            ->post($url);

        if (! $response->successful()) {
            return response()->json([
                'error' => 'Upload failed',
                'details' => $response->body(),
            ], $response->status());
        }

        OrthancAuditLogger::logUploadDicom(
            $response->json('ParentStudy'),
            $file->getClientOriginalName()
        );

        return response()->json([
            'success' => true,
            'orthanc_id' => $response->json('ID'),
            'parent_study' => $response->json('ParentStudy'),
        ]);
    }
}
```

### Task 10: Create UploadDicomRequest
- **ACTION**: Create form request for DICOM upload validation
- **IMPLEMENT**: Validate file type, size, extension
- **MIRROR**: Follow Laravel FormRequest pattern
- **IMPORTS**: `Illuminate\Foundation\Http\FormRequest`
- **GOTCHA**: DICOM files can be large, set appropriate max file size
- **VALIDATE**: Test with valid and invalid files

```php
<?php

namespace App\Http\Requests\Orthanc;

use Illuminate\Foundation\Http\FormRequest;

class UploadDicomRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Use Spatie permission check
        return $this->user()?->can('upload dicom') ?? false;
    }

    public function rules(): array
    {
        return [
            'dicom' => [
                'required',
                'file',
                'mimes:dcm,application/dicom',
                'max:102400', // 100MB max
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'dicom.required' => 'A DICOM file is required',
            'dicom.mimes' => 'The file must be a DICOM file (.dcm)',
            'dicom.max' => 'The file may not be larger than 100MB',
        ];
    }
}
```

### Task 11: Create Livewire Components
- **ACTION**: Create PatientList, PatientDetail, StudyList, StudyViewer components
- **IMPLEMENT**: Use Livewire for reactive UI, wire:model for search/filter
- **MIRROR**: Follow Livewire 4 pattern with typed properties
- **IMPORTS**: `Livewire\Attributes\Locked`, `Livewire\Component`
- **GOTCHA**: Use #[Locked] for properties that shouldn't be modified from client
- **VALIDATE**: Test reactivity, search, and navigation

```php
<?php

namespace App\Livewire\Patients;

use App\Services\Orthanc\OrthancPatientService;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PatientList extends Component
{
    public string $search = '';

    #[Locked]
    public array $patients = [];

    public function mount(OrthancPatientService $patientService): void
    {
        $this->loadPatients($patientService);
    }

    public function updatedSearch(OrthancPatientService $patientService): void
    {
        $this->loadPatients($patientService);
    }

    private function loadPatients(OrthancPatientService $patientService): void
    {
        if (empty($this->search)) {
            $this->patients = $patientService->getAll();
        } else {
            $this->patients = $patientService->search($this->search);
        }
    }

    public function render()
    {
        return view('livewire.patients.patient-list');
    }
}
```

### Task 12: Create Blade Templates
- **ACTION**: Create Blade templates for all Livewire components
- **IMPLEMENT**: Use Flux components for consistent UI, TailwindCSS for styling
- **MIRROR**: Follow existing `resources/views/dashboard.blade.php` structure
- **IMPORTS**: None needed in Blade files
- **GOTCHA**: Use wire:key for lists to ensure proper reactivity
- **VALIDATE**: Test rendering, search, and navigation in browser

```blade
{{-- resources/views/livewire/patients/patient-list.blade.php --}}
<div>
    <flux:header>
        <flux:heading level="1">Patients</flux:heading>
    </flux:header>

    <flux:card>
        <flux:input
            wire:model.live="search"
            placeholder="Search patients (e.g., DOE*)"
            type="search"
        />
    </flux:card>

    @if(empty($patients))
        <flux:empty-state>
            <flux:empty-state.heading>No patients found</flux:empty-state.heading>
            <flux:empty-state.description>Try adjusting your search criteria.</flux:empty-state.description>
        </flux:empty-state>
    @else
        <flux:table>
            <flux:table.header>
                <flux:table.row>
                    <flux:table.cell>Patient Name</flux:table.cell>
                    <flux:table.cell>Patient ID</flux:table.cell>
                    <flux:table.cell>Birth Date</flux:table.cell>
                    <flux:table.cell>Sex</flux:table.cell>
                    <flux:table.cell>Studies</flux:table.cell>
                    <flux:table.cell>Actions</flux:table.cell>
                </flux:table.row>
            </flux:table.header>
            <flux:table.body>
                @foreach($patients as $patient)
                    <flux:table.row wire:key="{{ $patient->orthancId }}">
                        <flux:table.cell>{{ $patient->patientName }}</flux:table.cell>
                        <flux:table.cell>{{ $patient->patientId }}</flux:table.cell>
                        <flux:table.cell>{{ $patient->birthDate ?? 'N/A' }}</flux:table.cell>
                        <flux:table.cell>{{ $patient->sex ?? 'N/A' }}</flux:table.cell>
                        <flux:table.cell>{{ $patient->studiesCount }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button
                                href="{{ route('patients.show', $patient->orthancId) }}"
                                size="sm"
                                variant="primary"
                            >
                                View
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.body>
        </flux:table>
    @endif
</div>
```

### Task 13: Create StudyViewer with Stone Web Viewer
- **ACTION**: Create Livewire component with embedded Stone Web Viewer
- **IMPLEMENT**: Use iframe pointing to Orthanc's web-viewer or Stone Web Viewer plugin
- **MIRROR**: Follow Livewire pattern with #[Locked] properties
- **IMPORTS**: `Livewire\Component`
- **GOTCHA**: Stone Web Viewer requires study ID in URL, need to proxy or whitelist
- **VALIDATE**: Test with real DICOM studies, verify viewer loads correctly

```blade
{{-- resources/views/livewire/studies/study-viewer.blade.php --}}
<div>
    <flux:header>
        <flux:heading level="1">Study Viewer</flux:heading>
        <flux:heading level="2">{{ $study->studyDescription }}</flux:heading>
    </flux:header>

    <flux:card class="h-[80vh]">
        <iframe
            src="{{ config('orthanc.base_url') }}/web-viewer/app.html?study={{ $study->orthancId }}"
            class="w-full h-full border-0"
            allow="fullscreen"
        ></iframe>
    </flux:card>
</div>
```

### Task 14: Register Routes
- **ACTION**: Add Orthanc routes to routes/web.php
- **IMPLEMENT**: Create route group with middleware, register all endpoints
- **MIRROR**: Follow existing route pattern with prefix and middleware
- **IMPORTS**: `App\Http\Controllers\Orthanc\*`
- **GOTCHA**: Apply RBAC middleware to appropriate routes
- **VALIDATE**: Run `php artisan route:list` and verify all routes are registered

```php
// Add to routes/web.php
use App\Http\Controllers\Orthanc\DicomUploadController;
use App\Http\Controllers\Orthanc\InstanceController;
use App\Http\Controllers\Orthanc\PatientController;
use App\Http\Controllers\Orthanc\SeriesController;
use App\Http\Controllers\Orthanc\StudyController;

Route::middleware(['auth', 'verified'])->prefix('orthanc')->name('orthanc.')->group(function () {
    // Require 'view patients' permission (Radiologist, Physician, Admin)
    Route::middleware('permission:view patients')->group(function () {
        Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
        Route::get('/patients/{orthancId}', [PatientController::class, 'show'])->name('patients.show');
    });

    // Require 'view studies' permission
    Route::middleware('permission:view studies')->group(function () {
        Route::get('/studies', [StudyController::class, 'index'])->name('studies.index');
        Route::get('/studies/{orthancId}', [StudyController::class, 'show'])->name('studies.show');
    });

    // Require 'view series' permission
    Route::middleware('permission:view series')->group(function () {
        Route::get('/series', [SeriesController::class, 'index'])->name('series.index');
        Route::get('/series/{orthancId}', [SeriesController::class, 'show'])->name('series.show');
    });

    // Require 'view instances' permission
    Route::middleware('permission:view instances')->group(function () {
        Route::get('/instances', [InstanceController::class, 'index'])->name('instances.index');
        Route::get('/instances/{orthancId}', [InstanceController::class, 'show'])->name('instances.show');
    });

    // Require 'upload dicom' permission (Radiologist, Admin)
    Route::post('/upload', [DicomUploadController::class, 'store'])
        ->middleware('permission:upload dicom')
        ->name('upload');

    // Require 'delete patients' permission (Admin only)
    Route::delete('/patients/{orthancId}', [PatientController::class, 'destroy'])
        ->middleware('permission:delete patients')
        ->name('patients.destroy');
});
```

### Task 15: Update User Model
- **ACTION**: Add role methods and relationships to User model
- **IMPLEMENT**: Add role attribute, accessor for UserRole enum, access methods
- **MIRROR**: Follow existing User model pattern with attributes
- **IMPORTS**: `App\Enums\UserRole`
- **GOTCHA**: Use accessor for type-safe role enum
- **VALIDATE**: Test role checks and permissions

```php
// Update app/Models/User.php

// Add these traits at the top
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\Traits\LogsActivity;

// Add traits to class
class User extends Authenticatable
{
    use HasFactory, HasTeams, Notifiable, TwoFactorAuthenticatable, HasRoles, LogsActivity;

    // Optional: configure what attributes to log
    protected static $logAttributes = ['name', 'email'];
    protected static $logOnlyDirty = true;

    // Helper method for checking Orthanc-specific permissions
    public function canUploadDicom(): bool
    {
        return $this->can('upload dicom');
    }

    public function canDeleteOrthancData(): bool
    {
        return $this->can('delete patients') || $this->can('delete studies');
    }
}
```

### Task 16: Create Service Provider
- **ACTION**: Create OrthancServiceProvider for service registration
- **IMPLEMENT**: Register Orthanc services in container
- **MIRROR**: Follow Laravel service provider pattern
- **IMPORTS**: `Illuminate\Support\ServiceProvider`
- **GOTCHA**: Register services as singletons or scoped as needed
- **VALIDATE**: Test that services are injectable in controllers

```php
<?php

namespace App\Providers;

use App\Services\Orthanc\OrthancInstanceService;
use App\Services\Orthanc\OrthancPatientService;
use App\Services\Orthanc\OrthancSeriesService;
use App\Services\Orthanc\OrthancStudyService;
use Illuminate\Support\ServiceProvider;

class OrthancServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OrthancPatientService::class);
        $this->app->singleton(OrthancStudyService::class);
        $this->app->singleton(OrthancSeriesService::class);
        $this->app->singleton(OrthancInstanceService::class);
    }

    public function boot(): void
    {
        //
    }
}
```

### Task 17: Register Service Provider
- **ACTION**: Add OrthancServiceProvider to config/app.php
- **IMPLEMENT**: Add to providers array
- **MIRROR**: Follow existing service provider registration
- **IMPORTS**: None needed in config
- **GOTCHA**: Run `php artisan config:clear` after changes
- **VALIDATE**: Run `php artisan tinker` and verify services are available

```php
// Add to config/app.php providers array
App\Providers\OrthancServiceProvider::class,
```

### Task 18: Setup Livewire Alert in Layout
- **ACTION**: Add @livewire-alert directive to main layout
- **IMPLEMENT**: Include alert component in layout Blade template
- **MIRROR**: Follow Livewire component inclusion pattern
- **IMPORTS**: None needed in Blade
- **GOTCHA**: Add to base layout so alerts work across all pages
- **VALIDATE**: Test alert messages display correctly

```blade
{{-- Add to resources/views/layouts/app.blade.php or main layout --}}
<x-slot:scripts>
    @vite(['resources/js/app.js', 'resources/css/app.css'])
    @livewire('livewire-alert')
</x-slot:scripts>
```

### Task 19: Create Main Navigation View
- **ACTION**: Create navigation component for Orthanc section
- **IMPLEMENT**: Add Orthanc links to main navigation
- **MIRROR**: Follow existing navigation pattern
- **IMPORTS**: None needed in Blade
- **GOTCHA**: Show/hide links based on user role
- **VALIDATE**: Test navigation with different user roles

### Task 20: Create Dashboard with Quick Access
- **ACTION**: Update dashboard to show Orthanc statistics and quick access
- **IMPLEMENT**: Show patient count, recent studies, quick upload link
- **MIRROR**: Follow existing dashboard pattern
- **IMPORTS**: None needed in Blade
- **GOTCHA**: Cache statistics to avoid excessive Orthanc calls
- **VALIDATE**: Test dashboard loads with statistics

---

## Testing Strategy

### Unit Tests

| Test | Input | Expected Output | Edge Case? |
|---|---|---|---|
| OrthancService::request | Valid endpoint | Array response | ✅ Network failure |
| PatientService::getAll | None | Array of patient arrays | ✅ Empty Orthanc |
| PatientService::search | "DOE*" | Filtered patients | ✅ No results |
| UploadDicomRequest::validate | Invalid file | Validation error | ✅ Oversized file |
| User has permission | User->can('view patients') | bool based on role |
| Activity log entry created | OrthancAuditLogger::log() | Entry in activity_log |

### Feature Tests

| Test | Description | Success Criteria |
|---|---|---|
| Authenticated user can view patient list | Login, GET /orthanc/patients | 200 response, JSON data |
| Unauthenticated user cannot access | GET /orthanc/patients without auth | Redirect to login |
| Radiologist can upload DICOM | POST /orthanc/upload as radiologist | 201 response, study created |
| Physician cannot upload | POST /orthanc/upload as physician | 403 response |
| Admin can delete patient | DELETE /orthanc/patients/{id} as admin | 200 response |
| Non-admin cannot delete | DELETE as physician | 403 response |
| Permission middleware works | Route with permission middleware | 403 for unauthorized |
| Activity log created on access | Any Orthanc endpoint | Entry in activity_log |
| Search with wildcard | GET /orthanc/patients?search=DOE* | Filtered results |
| Study viewer loads | GET /orthanc/studies/{id}/viewer | Stone viewer iframe renders |
| Alert messages display | Use Livewire Alert | Alert visible in UI |

### Edge Cases Checklist
- [ ] Empty Orthanc (no patients)
- [ ] Very large patient list (>1000)
- [ ] Special characters in patient names
- [ ] Network timeout to Orthanc
- [ ] Orthanc authentication failure
- [ ] Invalid DICOM file upload
- [ ] Concurrent uploads
- [ ] User role change mid-session
- [ ] CORS errors for iframe
- [ ] Large DICOM files (>50MB)

---

## Validation Commands

### Static Analysis
```bash
# Run Laravel Pint
vendor/bin/pint --format agent

# Check for syntax errors
php -l app/Services/Orthanc/*.php
php -l app/Http/Controllers/Orthanc/*.php
```
EXPECT: Zero syntax errors, code formatted according to PSR-12

### Unit Tests
```bash
# Run tests
php artisan test --filter=Orthanc
```
EXPECT: All tests pass

### Full Test Suite
```bash
# Run complete test suite
php artisan test --compact
```
EXPECT: No regressions

### Database Validation
```bash
# Verify migrations (includes Spatie tables)
php artisan migrate:fresh --seed

# Check Spatie tables
php artisan tinker --execute 'echo \Schema::getColumnListing("roles");'
php artisan tinker --execute 'echo \Schema::getColumnListing("permissions");'
php artisan tinker --execute 'echo \Schema::getColumnListing("activity_log");'

# Verify roles and permissions are seeded
php artisan tinker --execute 'echo \Spatie\Permission\Models\Role::count();'
php artisan tinker --execute 'echo \Spatie\Permission\Models\Permission::count();'
```
EXPECT: Schema includes Spatie tables (roles, permissions, activity_log)

### Package Validation
```bash
# Verify Spatie packages installed
php artisan tinker --execute 'echo class_exists(\Spatie\Permission\Permission::class) ? "OK" : "FAIL";'
php artisan tinker --execute 'echo class_exists(\Spatie\Activitylog\Models\Activity::class) ? "OK" : "FAIL";'
php artisan tinker --execute 'echo class_exists(\Jantinnerezo\LivewireAlert\LivewireAlert::class) ? "OK" : "FAIL";'
```
EXPECT: All packages installed and classes exist

### Browser Validation
```bash
# Start dev server
php artisan serve

# Visit in browser
# http://localhost:8000
# http://localhost:8000/orthanc/patients
```
EXPECT: Pages load, login works, patient list displays, debugbar visible

### Manual Validation
- [ ] Login as Administrator, verify all permissions
- [ ] Login as Radiologist, verify upload and view permissions
- [ ] Login as Physician, verify view-only permissions
- [ ] Upload a DICOM file, verify it appears in Orthanc
- [ ] Search for patient with wildcard, verify results
- [ ] Open study viewer, verify images load
- [ ] Check activity_log table, verify entries created with full context
- [ ] Try to access unauthorized resource, verify 403
- [ ] Test alert messages display with Livewire Alert
- [ ] Log out, verify redirect to login
- [ ] Test with large DICOM file (>50MB)
- [ ] Verify debugbar shows queries for debugging

---

## Acceptance Criteria
- [ ] All tasks completed
- [ ] All validation commands pass
- [ ] Tests written and passing (80%+ coverage)
- [ ] No type errors
- [ ] No lint errors
- [ ] Matches UX design
- [ ] Orthanc API integration working
- [ ] Spatie Permission RBAC functioning correctly
- [ ] Spatie Activitylog audit logging active
- [ ] Livewire Alert notifications working
- [ ] PHI protection verified (only IDs in Laravel)

## Completion Checklist
- [ ] Code follows discovered patterns
- [ ] Error handling matches codebase style
- [ ] Logging follows codebase conventions
- [ ] Tests follow test patterns
- [ ] No hardcoded values (use config/env)
- [ ] Documentation updated
- [ ] No unnecessary scope additions
- [ ] Self-contained — no questions needed during implementation
- [ ] User model has HasRoles and LogsActivity traits
- [ ] Spatie roles and permissions seeded
- [ ] Service provider registered
- [ ] Routes use Spatie permission middleware
- [ ] Livewire components use Flux UI
- [ ] Livewire Alert integrated in layout
- [ ] Stone Web Viewer accessible
- [ ] DICOM upload working
- [ ] Search with wildcard working
- [ ] Activity logs created on all Orthanc access

## Risks

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Orthanc API changes break integration | Medium | High | Version pin Orthanc, document API version |
| Large file uploads timeout | Medium | Medium | Implement chunked upload, increase timeout |
| CORS issues with Stone Web Viewer iframe | High | Medium | Configure Orthanc CORS, use proxy if needed |
| PHI accidentally stored in Laravel | Low | Critical | Code review, audit database schema |
| Performance with large patient lists | Medium | Medium | Implement pagination, caching |
| Session hijacking | Low | High | Use HTTPS, secure cookie settings, 2FA |
| Spatie permission cache not cleared | Low | Medium | Use php artisan permission:cache-reset on changes |
| Activity log table grows large | Medium | Medium | Implement log pruning/cleanup job |
| Permission check on every request | Low | Low | Cache is enabled by default in Spatie |

## Notes

### Architecture Decisions
- **Service Layer**: Dedicated Orthanc services encapsulate API communication
- **Data Transfer**: Using arrays for simplicity (no DTOs) - services return structured arrays
- **RBAC**: Using `spatie/laravel-permission` - permission-based authorization with role support
  - Roles: Administrator, Radiologist, Physician, Patient
  - Permissions: view patients, view studies, upload dicom, delete patients, annotate studies
  - Middleware: `permission:view patients`, `role:Administrator`, etc.
- **Audit Logging**: Using `spatie/laravel-activitylog` - comprehensive audit trail for compliance
  - Custom OrthancAuditLogger helper for consistent logging format
  - Logs IP, user agent, orthanc_id, and action details
- **PHI Protection**: Laravel stores only Orthanc IDs, never PHI
- **Viewer**: Stone Web Viewer embedded via iframe (Orthanc serves images)
- **UI Alerts**: Using `jantinnerezo/livewire-alert` for user notifications
- **Debug Tools**: `barryvdh/laravel-debugbar` for development, `barryvdh/laravel-ide-helper` for IDE support

### Database Schema Notes
- **Spatie tables** (auto-created by migrations):
  - `roles` - Role definitions
  - `permissions` - Permission definitions
  - `role_has_permissions` - Role-Permission relationships
  - `model_has_roles` - User-Role relationships
  - `model_has_permissions` - User-Permission direct assignments
  - `activity_log` - Audit trail entries with subject_id, subject_type, properties
- **Laravel default**: `users`, `sessions`, `cache`, `jobs`
- No PHI stored in Laravel database (only Orthanc IDs in activity_log properties)

### Role & Permission Structure

**Roles:**
- Administrator - All permissions
- Radiologist - view + upload + annotate
- Physician - view only
- Patient - limited view (own data only - future enhancement)

**Permissions:**
- `view patients` - List and view patient details
- `view studies` - List and view study details
- `view series` - List and view series details
- `view instances` - List and view instance details
- `upload dicom` - Upload DICOM files to Orthanc
- `delete patients` - Delete patient records
- `delete studies` - Delete study records
- `annotate studies` - Add annotations to studies (future)

### Orthanc API Endpoints Used
- `GET /patients` - List all patients
- `GET /patients/{id}` - Get patient details
- `POST /tools/find` - Search patients
- `GET /studies/{id}` - Get study details
- `POST /instances` - Upload DICOM file
- `DELETE /patients/{id}` - Delete patient (admin only)
- `/web-viewer/app.html?study={id}` - Stone Web Viewer

### Security Considerations
- Orthanc credentials stored in .env, never in code
- All Orthanc routes require authentication
- RBAC middleware enforces role permissions
- Audit logging for compliance
- CSRF protection on state-changing requests
- HTTPS required in production

### Performance Considerations
- Implement pagination for large lists
- Cache frequently accessed data (patient lists)
- Use queue for large DICOM uploads
- Optimize Orthanc queries with MainDicomTags

### Known Limitations
- Single Orthanc instance (no federation)
- No DICOM anonymization beyond Orthanc's /modify
- No HL7 integration
- No long-term archival
- Stone Web Viewer requires plugin installation on Orthanc

*Generated: 2026-04-23*
*Status: DRAFT - ready for implementation*
