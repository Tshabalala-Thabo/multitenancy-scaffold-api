<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SwitchTenantController extends Controller
{
    public function switch(Request $request)
    {
        $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
        ]);

        $user = Auth::user();
        $tenant = Tenant::find($request->tenant_id);

        if ($user->tenants->contains($tenant)) {
            $user->current_tenant_id = $tenant->id;
            $user->save();

            $tenant->makeCurrent();

            return response()->noContent();
        }

        return response()->json(['message' => 'You do not have access to this tenant.'], 403);
    }
}
