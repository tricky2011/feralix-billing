<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\Settings\AppSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    public function __construct(
        private readonly AppSettingService $settingService,
    ) {}

    /**
     * Get all settings
     *
     * GET /v1/admin/settings
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeViewSettings($request->user());

        $sections = [
            'billing' => $this->settingService->getBillingSettings(),
            'mikrotik' => $this->settingService->getMikrotikSettings(),
            'genieacs' => $this->settingService->getGenieacsSettings(),
            'hotspot' => $this->settingService->getHotspotSettings(),
            'telegram' => $this->settingService->getTelegramSettings(),
        ];

        return response()->json([
            'success' => true,
            'data' => $sections,
        ]);
    }

    /**
     * Get settings by section
     *
     * GET /v1/admin/settings/{section}
     */
    public function show(Request $request, string $section): JsonResponse
    {
        $this->authorizeViewSettings($request->user());

        $data = match ($section) {
            'billing' => $this->settingService->getBillingSettings(),
            'mikrotik' => $this->settingService->getMikrotikSettings(),
            'genieacs' => $this->settingService->getGenieacsSettings(),
            'hotspot' => $this->settingService->getHotspotSettings(),
            'telegram' => $this->settingService->getTelegramSettings(),
            default => null,
        };

        if ($data === null) {
            return response()->json([
                'success' => false,
                'message' => 'Section not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Update settings
     *
     * PUT /v1/admin/settings
     * Only superadmin can update settings
     */
    public function update(Request $request): JsonResponse
    {
        $this->authorizeEditSettings($request->user());

        $validated = $request->validate([
            'billing_suspend_day' => 'nullable|integer|min:1|max:28',
            'billing_grace_days' => 'nullable|integer|min:0|max:30',
            'billing_invoice_lead_days' => 'nullable|integer|min:1|max:30',
            'billing_reminder_lead_days' => 'nullable|integer|min:1|max:14',
            'billing_reminder_time' => 'nullable|date_format:H:i',
            'billing_suspend_time' => 'nullable|date_format:H:i',
            'billing_ppn_percent' => 'nullable|numeric|min:0|max:100',
            'billing_payment_instruction' => 'nullable|string|max:1000',
            // MikroTik
            'mikrotik_vlan_bridge_name' => 'nullable|string|max:100',
            'mikrotik_pppoe_default_profile' => 'nullable|string|max:100',
            'mikrotik_isolation_profile' => 'nullable|string|max:100',
            'mikrotik_isolation_address_list' => 'nullable|string|max:100',
            // GenieACS
            'genieacs_nbi_url' => 'nullable|url|max:500',
            'genieacs_nbi_username' => 'nullable|string|max:100',
            'genieacs_nbi_password' => 'nullable|string|max:100',
            'genieacs_cwmp_url' => 'nullable|url|max:500',
            'genieacs_acs_username' => 'nullable|string|max:100',
            'genieacs_acs_password' => 'nullable|string|max:100',
            // Hotspot
            'hotspot_radius_coa_port' => 'nullable|integer|min:1|max:65535',
            'hotspot_radius_default_nas_secret' => 'nullable|string|max:60',
            'hotspot_radius_timeout' => 'nullable|integer|min:1|max:30',
            'hotspot_radclient_path' => 'nullable|string|max:255',
            // Telegram
            'telegram_bot_token' => 'nullable|string|max:200',
            'telegram_group_id' => 'nullable|string|max:50',
            'telegram_enabled' => 'nullable|boolean',
        ]);

        $this->settingService->setMany($validated);

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully.',
        ]);
    }

    /**
     * Update single setting
     *
     * PATCH /v1/admin/settings/{key}
     */
    public function updateSingle(Request $request, string $key): JsonResponse
    {
        $this->authorizeEditSettings($request->user());

        // Only allow specific keys
        $allowedKeys = [
            'billing_suspend_day', 'billing_grace_days', 'billing_invoice_lead_days',
            'billing_reminder_lead_days', 'billing_reminder_time', 'billing_suspend_time',
            'billing_ppn_percent', 'billing_payment_instruction',
            'mikrotik_vlan_bridge_name', 'mikrotik_pppoe_default_profile',
            'mikrotik_isolation_profile', 'mikrotik_isolation_address_list',
            'genieacs_nbi_url', 'genieacs_nbi_username', 'genieacs_nbi_password',
            'genieacs_cwmp_url', 'genieacs_acs_username', 'genieacs_acs_password',
            'hotspot_radius_coa_port', 'hotspot_radius_default_nas_secret',
            'hotspot_radius_timeout', 'hotspot_radclient_path',
            'telegram_bot_token', 'telegram_group_id', 'telegram_enabled',
        ];

        if (! in_array($key, $allowedKeys)) {
            return response()->json([
                'success' => false,
                'message' => 'Setting key not allowed.',
            ], 403);
        }

        $value = $request->input('value');
        $this->settingService->set($key, $value);

        return response()->json([
            'success' => true,
            'message' => 'Setting updated successfully.',
            'key' => $key,
            'value' => $value,
        ]);
    }

    /**
     * Check if user can view settings
     */
    private function authorizeViewSettings(?\App\Models\User $user): void
    {
        if ($user === null) {
            abort(401, 'Unauthorized');
        }

        // Admin and superadmin can view settings
        if (! in_array($user->role, [UserRole::Superadmin, UserRole::Admin])) {
            abort(403, 'Access denied.');
        }
    }

    /**
     * Check if user can edit settings
     */
    private function authorizeEditSettings(?\App\Models\User $user): void
    {
        if ($user === null) {
            abort(401, 'Unauthorized');
        }

        // Only superadmin can edit settings
        if ($user->role !== UserRole::Superadmin) {
            abort(403, 'Only superadmin can edit settings.');
        }
    }
}
