<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\TenantUserBan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\TenantUserBanResource;

class TenantUserBanController extends Controller
{
    /**
     * @param $tenantId
     * @return Response
     */
    public function index($tenantId)
    {
        Log::info('Retrieving bans for tenant: ' . $tenantId);
        try {

            if (!$tenantId) {
                return $this->jsonServerError('Tenant not found.');
            }

            $bans = TenantUserBan::with('user', 'bannedBy')
                ->where('tenant_id', $tenantId)
                ->whereNull('unbanned_at')
                ->get();

            return $this->jsonResource(TenantUserBanResource::collection($bans));

        } catch (\Exception $e) {
            if (app()->environment('local')) {
                return $this->jsonServerError($e->getMessage());
            }

            return $this->jsonServerError('Failed to retrieve bans.');
        }
    }

    /**
     * @param Request $request
     * @param $tenantId
     * @param $userId
     * @return Response
     */
    public function banUserFromTenant(Request $request, $tenantId, $userId): Response
    {
        try {
            $request->validate([
                'reason' => 'nullable|string|max:500',
            ]);

            $tenant = Tenant::findOrFail($tenantId);
            $user = User::findOrFail($userId);

            $ban = TenantUserBan::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'user_id' => $userId,
                ],
                [
                    'reason' => $request->reason,
                    'banned_at' => now(),
                    'banned_by' => Auth::id(),
                    'unbanned_at' => null,
                    'unbanned_by' => null,
                    'unban_reason' => null,
                ]
            );

            $tenant->users()->detach($userId);

            $user->roles()
                ->wherePivot('tenant_id', $tenant->id)
                ->detach();

            return $this->json(['message' => 'User banned successfully', 'ban' => $ban]);
        } catch (\Exception $e) {
            if (app()->environment('local')) {
                return $this->jsonServerError($e->getMessage());
            }
            return $this->jsonServerError('Failed to ban user.');
        }

    }


    /**
     * @param Request $request
     * @param $tenantId
     * @param $userId
     * @return Response
     */
    public function unbanUserFromTenant(Request $request, $tenantId, $userId): Response
    {
        try {
            $request->validate([
                'reason' => 'nullable|string|max:500',
            ]);

            $tenant = Tenant::findOrFail($tenantId);

            $ban = TenantUserBan::where('tenant_id', $tenant->id)
                ->where('user_id', $userId)
                ->whereNull('unbanned_at')
                ->firstOrFail();

            $ban->update([
                'unbanned_at' => now(),
                'unbanned_by' => Auth::id(),
                'unban_reason' => $request->reason,
            ]);

            return $this->json(['message' => 'User unbanned successfully', 'ban' => $ban]);
        } catch (\Exception $e) {
            if (app()->environment('local')) {
                return $this->jsonServerError($e->getMessage());
            }
            return $this->jsonServerError('Failed to unban user.');
        }
    }

}
