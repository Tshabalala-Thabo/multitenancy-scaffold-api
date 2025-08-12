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
     * @return void
     */
    public function updateAccessControl(Tenant $tenant, array $validated): void
    {
        try {
            DB::beginTransaction();

            $tenant->update([
                'privacy_setting' => $validated['privacy_setting'],
                'two_factor_auth_required' => $validated['two_factor_auth_required'],
                'password_policy' => [
                    'min_length' => $validated['password_policy']['min_length'],
                    'requires_uppercase' => $validated['password_policy']['requires_uppercase'],
                    'requires_lowercase' => $validated['password_policy']['requires_lowercase'],
                    'requires_number' => $validated['password_policy']['requires_number'],
                    'requires_symbol' => $validated['password_policy']['requires_symbol'],
                ]
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
        }
    }

    /**
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
        $tenant->address()->updateOrCreate([], $addressData);
    }
}
