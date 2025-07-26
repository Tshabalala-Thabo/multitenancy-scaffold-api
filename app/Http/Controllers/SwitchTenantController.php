<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class SwitchTenantController extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse|Response
     */
    public function switch(Request $request): Response|JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
        ]);

        $user = Auth::user();
        $tenant = Tenant::find($request->tenant_id);

        if ($user->tenants->contains('id', $tenant->id)) {
            $user->update(['current_tenant_id' => $tenant->id]);
            $tenant->makeCurrent();
            $request->session()->put('tenant_id', $tenant->id);

            return response()->noContent();
        }

        $user->update(['current_tenant_id' => null]);
        $request->session()->forget('tenant_id');

        return response()->json([
            'message' => 'You do not have access to this tenant.'
        ], 403);
    }
}
