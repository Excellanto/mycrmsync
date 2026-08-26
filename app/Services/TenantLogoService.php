<?php

namespace App\Services;

use App\Models\Tenant;
use App\Services\Integrations\TenantStorageDiskService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class TenantLogoService
{
    public const WIDTH = 400;

    public const HEIGHT = 100;

    public static function validationRule(): string
    {
        return 'required|image|mimes:jpeg,jpg,png|max:5120|dimensions:width='.self::WIDTH.',height='.self::HEIGHT;
    }

    public function store(Tenant $tenant, UploadedFile $file): Tenant
    {
        $contents = file_get_contents($file->getRealPath());

        $disk = TenantStorageDiskService::diskForTenant((int) $tenant->id);

        if ($tenant->company_logo_path && $disk->exists($tenant->company_logo_path)) {
            $disk->delete($tenant->company_logo_path);
        }

        $path = 'Tenant-Profile-Images/'.$tenant->id.'/'.Str::uuid()->toString().'.jpg';
        $disk->put($path, $contents, ['visibility' => 'public']);

        $tenant->update(['company_logo_path' => $path]);

        return $tenant->refresh();
    }

    public function destroy(Tenant $tenant): void
    {
        $disk = TenantStorageDiskService::diskForTenant((int) $tenant->id);

        if ($tenant->company_logo_path && $disk->exists($tenant->company_logo_path)) {
            $disk->delete($tenant->company_logo_path);
        }

        $tenant->update(['company_logo_path' => null]);
    }
}
