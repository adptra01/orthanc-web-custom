<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class OrthancRoleSeeder extends Seeder
{
    /**
     * Seed Orthanc roles and permissions.
     *
     * Roles:
     *  - Administrator  : semua permission
     *  - Radiologist    : view + upload + annotate
     *  - Physician      : view only
     *  - Patient        : tanpa permission default (future: view own data)
     */
    public function run(): void
    {
        // Reset cached roles and permissions (per dokumentasi resmi Spatie).
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'web';

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
            Permission::findOrCreate($permission, $guard);
        }

        $administrator = Role::findOrCreate('Administrator', $guard);
        $administrator->syncPermissions(Permission::where('guard_name', $guard)->get());

        $radiologist = Role::findOrCreate('Radiologist', $guard);
        $radiologist->syncPermissions([
            'view patients',
            'view studies',
            'view series',
            'view instances',
            'upload dicom',
            'annotate studies',
        ]);

        $physician = Role::findOrCreate('Physician', $guard);
        $physician->syncPermissions([
            'view patients',
            'view studies',
            'view series',
            'view instances',
        ]);

        Role::findOrCreate('Patient', $guard);

        // Reset cache sekali lagi agar perubahan langsung tersedia.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
