<?php

namespace App\Multitenancy;

use Illuminate\Http\Request;
use Spatie\Multitenancy\Models\Tenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

class DomainTenantFinder extends TenantFinder
{
    public function findForRequest(Request $request): ?Tenant
    {
        $host = $request->getHost();
        
        // Extract subdomain from host
        $parts = explode('.', $host);
        $subdomain = count($parts) >= 3 ? $parts[0] : null;
        
        if (!$subdomain) {
            return null; // No subdomain found, not a tenant request
        }
        
        // Find tenant by slug (subdomain)
        return Tenant::query()
            ->where('slug', $subdomain)
            ->first();
    }
}