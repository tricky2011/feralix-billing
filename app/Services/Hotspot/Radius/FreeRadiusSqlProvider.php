<?php

namespace App\Services\Hotspot\Radius;

use App\Contracts\Hotspot\HotspotRadiusProvider;
use App\Data\Hotspot\HotspotRadiusAccountingRequest;
use App\Data\Hotspot\HotspotRadiusAccountingResult;
use App\Data\Hotspot\HotspotRadiusAuthorizeRequest;
use App\Data\Hotspot\HotspotRadiusAuthorizeResult;
use App\Models\HotspotVoucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
class FreeRadiusSqlProvider implements HotspotRadiusProvider
{
    public function __construct(private readonly HotspotRadiusService $hotspotRadiusService) {}

    public function name(): string
    {
        return 'freeradius-sql';
    }

    /**
     * Authorize via RADIUS packet — delegates to HotspotRadiusService then syncs FreeRADIUS.
     *
     * HotspotRadiusService handles voucher state (expiry, MAC lock, data limit, event logging).
     * After accept, ensure radcheck/radreply are up-to-date for this session.
     */
    public function authorize(HotspotRadiusAuthorizeRequest $request): HotspotRadiusAuthorizeResult
    {
        $result = $this->hotspotRadiusService->authorize($request);

        if ($result->accepted) {
            $voucher = HotspotVoucher::query()
                ->with('hotspotProfile')
                ->where('username', $request->username)
                ->first();

            if ($voucher !== null) {
                try {
                    $this->syncVoucher($voucher);
                } catch (\Throwable $e) {
                    Log::warning('FreeRADIUS sync failed during authorization', [
                        'username' => $request->username,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $result;
    }

    /**
     * Account — update hotspot billing state then write accounting to radacct.
     *
     * HotspotRadiusService updates bytes_used, session records, and voucher status.
     * upsertRadAcct() mirrors the session to FreeRADIUS's own radacct table.
     */
    public function account(HotspotRadiusAccountingRequest $request): HotspotRadiusAccountingResult
    {
        $result = $this->hotspotRadiusService->account($request);

        try {
            $this->upsertRadAcct($request);
        } catch (\Throwable $e) {
            Log::warning('FreeRADIUS radacct upsert failed', [
                'username' => $request->username,
                'acct_session_id' => $request->acctSessionId,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

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
        DB::statement('DELETE FROM radcheck WHERE username = ? AND attribute = ?', [$username, 'Cleartext-Password']);
    }

    /**
     * Restore voucher auth in FreeRADIUS (e.g., after payment/top-up).
     */
    public function enableVoucher(HotspotVoucher $voucher): void
    {
        $this->syncVoucher($voucher);
    }

    private function insertReplyAttributes(string $username, ?\App\Models\HotspotProfile $profile): void
    {
        $rows = [];

        // Session timeout from profile validity
        if ($profile !== null && $profile->usesTimeExpiry() && $profile->validity_days !== null) {
            $sessionTimeoutSeconds = $profile->validity_days * 86400;
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
     * Priority:
     * 1. password_plain (stored unencrypted at voucher creation time)
     * 2. Decrypt the encrypted password field
     * 3. Fail fast — never silently pass an encrypted blob to FreeRADIUS
     */
    private function resolvePlaintextPassword(HotspotVoucher $voucher): string
    {
        $voucher->refresh();

        // Priority 1: password_plain stores the raw plaintext at voucher creation
        $plain = $voucher->getRawOriginal('password_plain');
        if ($plain !== null && trim((string) $plain) !== '') {
            return $plain;
        }

        // Priority 2: decrypt the encrypted password field
        $encrypted = $voucher->getRawOriginal('password');
        if ($encrypted !== null && trim((string) $encrypted) !== '') {
            // Skip if the raw value is already plaintext (not a Laravel Crypt payload)
            if (! str_starts_with((string) $encrypted, 'eyJp')) {
                return $encrypted;
            }

            try {
                return \Illuminate\Support\Facades\Crypt::decryptString($encrypted);
            } catch (\Throwable $e) {
                Log::error('Failed to decrypt voucher password for FreeRADIUS sync', [
                    'voucher_id' => $voucher->id,
                    'username' => $voucher->username,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Priority 3: password attribute (already decrypted by Eloquent cast)
        $passwordAttr = $voucher->getAttribute('password');
        if ($passwordAttr !== null && (string) $passwordAttr !== '') {
            return (string) $passwordAttr;
        }

        throw new \RuntimeException(
            "No plaintext password available for voucher ID {$voucher->id} (username: {$voucher->username}). "
            . 'Ensure password_plain is populated at voucher creation.',
        );
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
            $updateFields = [
                'acctupdatetime' => $now,
                'acctsessiontime' => $request->acctSessionTime ?? $existing->acctsessiontime,
                'inputoctets' => $inputOctets,
                'outputoctets' => $outputOctets,
                'callingstationid' => $request->callingStationId,
                'framedipaddress' => $request->framedIpAddress,
            ];

            if ($request->acctStatusType->value === 'stop') {
                $updateFields['acctstoptime'] = $now;
                $updateFields['acctterminatecause'] = $request->terminateCause ?? 'User-Request';
            }

            DB::table('radacct')
                ->where('acctuniqueid', $acctUniqueId)
                ->update($updateFields);
        } else {
            $acctStartTime = $request->acctStatusType->value === 'stop' ? $now : $request->eventAt;

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
            ]);
        }
    }
}
