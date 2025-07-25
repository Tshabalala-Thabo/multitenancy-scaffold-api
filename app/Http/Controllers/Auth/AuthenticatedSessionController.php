<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    /**
     * @param LoginRequest $request
     * @return Response
     * @throws ValidationException
     */
    public function store(LoginRequest $request): Response
    {
        $request->authenticate();
        $request->session()->regenerate();

        $user = $request->user();

        if ($user->current_tenant_id && $user->tenants->contains('id', $user->current_tenant_id)) {
            $tenant = $user->tenants()->find($user->current_tenant_id);

            if ($tenant) {
                $tenant->makeCurrent();
                $request->session()->put('tenant_id', $tenant->id);
            }
        } else {
            $user->update(['current_tenant_id' => null]);
            $request->session()->forget('tenant_id');
        }

        return response()->noContent();
    }


    /**
     * @param Request $request
     * @return Response
     */
    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
