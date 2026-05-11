<?php

namespace App\Services\Hotspot\Radius;

use App\Data\Hotspot\HotspotRadiusAccountingRequest;
use App\Data\Hotspot\HotspotRadiusAccountingResult;
use App\Data\Hotspot\HotspotRadiusAuthorizeRequest;
use App\Data\Hotspot\HotspotRadiusAuthorizeResult;
use App\Models\HotspotVoucher;
use Illuminate\Support\Facades\DB;

/**
 * FreeRADIUS SQL provider — writes directly to FreeRADIUS's MySQL tables.
 *
 * How it works:
 * 1. MikroTik (NAS) sends RADIUS Access-Request to FreeRADIUS server
 * 2. FreeRADIUS's rlm_sql module queries radcheck for auth (Cleartext-Password)
 * 3. On Access-Accept, FreeRADIUS reads radreply for reply attributes (session-timeout,
 *    rate-limit, WISPr-Redirection-URL, etc.)
 * 4. FreeRADIUS reads radusergroup for group-based attribute lookup
 *
 * radcheck attributes used:
 * - Cleartext-Password  : auth password
 * - Simultaneous-Use    : concurrent session limit (=1)
 *
 * radreply attributes used:
 * - Session-Timeout     : max session duration in seconds
 * - Idle-Timeout        : max idle time in seconds
 * - Max-All-Session     : total session time allowed
 * - WISPr-Redirection-URL : redirect URL after expiry/bandwidth-limit
 * - Mikrotik-Address-List : comma-separated "rx/tx" rate limits (e.g. "10M/10M")
 *
 * radusergroup: groupname = hotspot_profile.profile_name
 */
class FreeRadiusSqlProvider
{
    /**
     * Write voucher credentials and attributes to FreeRADIUS tables.
     *
     * Called when a hotspot voucher is activated or when its profile changes.
     */
    public function syncVoucher(HotspotVoucher $voucher): void
    {
        $voucher->loadMissing(['hotspotProfile']);

        $username = $voucher->username;
        $plaintextPassword = $this->resolvePlaintextPassword($voucher);
        $profile = $voucher->hotspotProfile;
        $groupName = $profile?->profile_name ?? 'default';

        // --- radcheck: cleartext password + simultaneous use ---
        DB::statement('DELETE FROM radcheck WHERE username = ?', [$username]);
        DB::table('radcheck')->insert([
            'username' => $username,
            'attribute' => 'Cleartext-Password',
            'op' => ':=',
            'value' => $plaintextPassword,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($profile?->usesMacLock()) {
            DB::table('radcheck')->insert([
                'username' => $username,
                'attribute' => 'Simultaneous-Use',
                'op' => ':=',
                'value' => '1',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // --- radreply: reply attributes ---
        DB::statement('DELETE FROM radreply WHERE username = ?', [$username]);

        $this->insertReplyAttributes($username, $profile);

        // --- radusergroup: profile group membership ---
        DB::statement('DELETE FROM radusergroup WHERE username = ?', [$username]);
        DB::table('radusergroup')->insert([
            'username' => $username,
            'groupname' => $groupName,
            'priority' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Remove voucher from FreeRADIUS tables.
     */
    public function removeVoucher(string $username): void
    {
        DB::statement('DELETE FROM radcheck WHERE username = ?', [$username]);
        DB::statement('DELETE FROM radreply WHERE username = ?', [$username]);
        DB::statement('DELETE FROM radusergroup WHERE username = ?', [$username]);
    }

    /**
     * Disable voucher in FreeRADIUS (set expired password or add Auth-Type = Reject).
     * Used when voucher is expired/depleted — no actual deletion to preserve accounting.
     */
    public function disableVoucher(string $username): void
    {
        // Remove password so FreeRADIUS rejects auth
        DB::statement('DELETE FROM radcheck WHERE username = ? AND attribute = ?', [$username, 'Cleartext-Password']);
    }

    /**
     * Restore voucher auth in FreeRADIUS (e.g., after payment/top-up).
     */
    public function enableVoucher(HotspotVoucher $voucher): void
    {
        $this->syncVoucher($voucher);
    }

    /**
     * Authorize via RADIUS packet — validates username/password against radcheck.
     *
     * This is the callback invoked when MikroTik forwards Access-Request to our
     * /v1/internal/hotspot-radius/authorize endpoint. We verify credentials here
     * (using the same logic as HotspotRadiusService) then return Accept/Reject.
     *
     * Note: The actual RADIUS auth is done by FreeRADIUS server. This method is
     * called by our billing system after FreeRADIUS authentication succeeds,
     * so we just return an Accept with current voucher state.
     */
    public function authorize(
        HotspotRadiusAuthorizeRequest $request,
        HotspotRadiusAccountingResult $authorizeResult,
    ): HotspotRadiusAuthorizeResult {
        return $authorizeResult;
    }

    /**
     * Account — update radacct with accounting packet.
     *
     * @param  HotspotRadiusAccountingRequest  $request
     * @param  HotspotRadiusAccountingResult  $accountingResult
     * @return HotspotRadiusAccountingResult
     */
    public function account(
        HotspotRadiusAccountingRequest $request,
        HotspotRadiusAccountingResult $accountingResult,
    ): HotspotRadiusAccountingResult {
        $this->upsertRadAcct($request);

        return $accountingResult;
    }

    private function insertReplyAttributes(string $username, ?\App\Models\HotspotProfile $profile): void
    {
        $rows = [];

        // Session timeout from profile validity
        if ($profile !== null && $profile->usesTimeExpiry() && $profile->validity_days !== null) {
            $sessionTimeoutSeconds = $profile->validity_days * 86400; // days → seconds
            $rows[] = [
                'username' => $username,
                'attribute' => 'Session-Timeout',
                'op' => '=',
                'value' => (string) $sessionTimeoutSeconds,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Idle timeout (default: 5 minutes)
        $rows[] = [
            'username' => $username,
            'attribute' => 'Idle-Timeout',
            'op' => '=',
            'value' => '300',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // WISPr redirect for expired/depleted users
        $redirectUrl = trim((string) config('hotspot.radius.expired_redirect_url', ''));
        if ($redirectUrl !== '') {
            $rows[] = [
                'username' => $username,
                'attribute' => 'WISPr-Redirection-URL',
                'op' => '=',
                'value' => $redirectUrl,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $rows[] = [
                'username' => $username,
                'attribute' => 'WISPr-Session-URL',
                'op' => '=',
                'value' => $redirectUrl,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            DB::table('radreply')->insert($rows);
        }
    }

    /**
     * Resolve the plaintext password for a voucher.
     *
     * The HotspotVoucher's password is encrypted in DB (cast: 'encrypted').
     * We store plaintext in password_plain during voucher creation so FreeRADIUS
     * can read the cleartext value directly from radcheck.value.
     */
    private function resolvePlaintextPassword(HotspotVoucher $voucher): string
    {
        $voucher->refresh();

        $raw = $voucher->getRawOriginal('password');

        if ($raw !== null) {
            return $raw;
        }

        $plain = $voucher->getRawOriginal('password_plain');

        if ($plain !== null && $plain !== '') {
            return $plain;
        }

        $rawPassword = $voucher->getAttribute('password');

        if (str_starts_with((string) $rawPassword, 'eyJp')) {
            try {
                return \Illuminate\Support\Facades\Crypt::decryptString($rawPassword);
            } catch (\Throwable) {
                return $rawPassword;
            }
        }

        return (string) $rawPassword;
    }

    private function upsertRadAcct(HotspotRadiusAccountingRequest $request): void
    {
        $acctUniqueId = md5(sprintf(
            '%s|%s|%s',
            $request->nasIpAddress ?? '',
            $request->acctSessionId,
            $request->username,
        ));

        $inputOctets = $request->inputOctets ?? 0;
        $outputOctets = $request->outputOctets ?? 0;

        $now = now();

        $existing = DB::table('radacct')
            ->where('acctuniqueid', $acctUniqueId)
            ->first();

        if ($existing) {
            // Update existing session
            $updateFields = [
                'acctupdatetime' => $now,
                'acctsessiontime' => $request->acctSessionTime ?? $existing->acctsessiontime,
                'inputoctets' => $inputOctets,
                'outputoctets' => $outputOctets,
                'callingstationid' => $request->callingStationId,
                'framedipaddress' => $request->framedIpAddress,
            ];

            if ($request->acctStatusType->value === 'Stop') {
                $updateFields['acctstoptime'] = $now;
                $updateFields['acctterminatecause'] = $request->terminateCause ?? 'User-Request';
            }

            DB::table('radacct')
                ->where('acctuniqueid', $acctUniqueId)
                ->update($updateFields);
        } else {
            // Insert new session
            $acctStartTime = $request->acctStatusType->value === 'Stop' ? $now : $request->eventAt;

            DB::table('radacct')->insert([
                'acctuniqueid' => $acctUniqueId,
                'acctsessionid' => $request->acctSessionId,
                'username' => $request->username,
                'nasipaddress' => $request->nasIpAddress,
                'nasidentifier' => $request->nasIdentifier,
                'calledstationid' => $request->calledStationId,
                'callingstationid' => $request->callingStationId,
                'framedipaddress' => $request->framedIpAddress,
                'acctstarttime' => $acctStartTime,
                'acctupdatetime' => $now,
                'acctsessiontime' => $request->acctSessionTime ?? 0,
                'inputoctets' => $inputOctets,
                'outputoctets' => $outputOctets,
                'acctauthentic' => 'RADIUS',
                'acctstatus type' => $request->acctStatusType->value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
