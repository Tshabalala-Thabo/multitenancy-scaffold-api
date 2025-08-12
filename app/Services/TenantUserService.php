<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantUserService
{
    /**
     * @param Tenant $tenant
     * @param array $validated
     * @param UploadedFile|null $logoFile
     * @param bool $removeLogo
     * @return array
     * @throws \Exception
     */
    public function updateOrganizationInfo(
        Tenant $tenant,
        array $validated,
        ?UploadedFile $logoFile = null,
        bool $removeLogo = false
    ): array {
        $this->handleLogoOperations($tenant, $logoFile, $removeLogo);

        $tenant->update([
            'name' => $validated['name'],
            'domain' => $validated['domain'],
        ]);

        $this->updateOrCreateAddress($tenant, $validated['address']);

        $tenant->load('address');
        $tenantData = $tenant->toArray();
        $tenantData['logo_url'] = $tenant->getLogoUrl();

        return $tenantData;
    }

    /**
     * @param Tenant $tenant
     * @param UploadedFile|null $logoFile
     * @param bool $removeLogo
     * @return void
     */
    protected function handleLogoOperations(Tenant $tenant, ?UploadedFile $logoFile = null, bool $removeLogo = false): void
    {
        if ($removeLogo) {
            Log::info('Removing tenant logo', ['tenant_id' => $tenant->id]);
            $tenant->deleteLogo();
            $tenant->logo_path = null;
            $tenant->save();
        } elseif ($logoFile) {
            Log::info('Uploading tenant logo', ['tenant_id' => $tenant->id]);
            $tenant->storeLogo($logoFile);
        }
    }

    /**
     * 
     * @param Tenant $tenant
     * @param array $addressData
     * @return void
     */
    protected function updateOrCreateAddress(Tenant $tenant, array $addressData): void
    {
        if ($tenant->address) {
            $tenant->address()->update($addressData);
        } else {
            $tenant->address()->create($addressData);
        }
    }
}
