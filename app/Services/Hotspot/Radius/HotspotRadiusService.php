<?php

namespace App\Services\Hotspot\Radius;

use App\Data\Hotspot\HotspotRadiusAccountingRequest;
use App\Data\Hotspot\HotspotRadiusAccountingResult;
use App\Data\Hotspot\HotspotRadiusAuthorizeRequest;
use App\Data\Hotspot\HotspotRadiusAuthorizeResult;
use App\Enums\HotspotRadiusAccountingType;
use App\Enums\HotspotRadiusEventType;
use App\Enums\HotspotRadiusRejectReason;
use App\Enums\HotspotVoucherSessionStatus;
use App\Exceptions\Hotspot\HotspotVoucherAccessDeniedException;
use App\Models\HotspotRadiusEvent;
use App\Models\HotspotVoucher;
use App\Models\HotspotVoucherSession;
use App\Services\Hotspot\HotspotVoucherStateService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class HotspotRadiusService
{
    public function __construct(private readonly HotspotVoucherStateService $hotspotVoucherStateService) {}

    public function authorize(HotspotRadiusAuthorizeRequest $request): HotspotRadiusAuthorizeResult
    {
        return DB::transaction(function () use ($request): HotspotRadiusAuthorizeResult {
            $hotspotVoucher = HotspotVoucher::query()
                ->with('hotspotProfile')
                ->where('username', $request->username)
                ->lockForUpdate()
                ->first();

            if ($hotspotVoucher === null || $hotspotVoucher->password !== $request->password) {
                $message = 'Voucher username or password is invalid.';

                $this->recordEvent(
                    hotspotVoucher: null,
                    session: null,
                    eventType: HotspotRadiusEventType::AuthReject,
                    outcome: 'rejected',
                    username: $request->username,
                    acctSessionId: null,
                    nasIdentifier: $request->nasIdentifier,
                    nasIpAddress: $request->nasIpAddress,
                    calledStationId: $request->calledStationId,
                    callingStationId: $request->callingStationId,
                    framedIpAddress: null,
                    occurredAt: $request->requestedAt,
                    reason: HotspotRadiusRejectReason::InvalidCredentials,
                    message: $message,
                    payload: $this->authorizationPayload($request),
                );

                return $this->rejectAuthorization(
                    hotspotVoucher: null,
                    request: $request,
                    reason: HotspotRadiusRejectReason::InvalidCredentials,
                    message: $message,
                );
            }

            try {
                $hotspotVoucher = $this->hotspotVoucherStateService->prepareForAccess(
                    $hotspotVoucher,
                    $request->callingStationId,
                    $request->requestedAt,
                );
            } catch (HotspotVoucherAccessDeniedException $exception) {
                $this->recordEvent(
                    hotspotVoucher: $hotspotVoucher,
                    session: null,
                    eventType: HotspotRadiusEventType::AuthReject,
                    outcome: 'rejected',
                    username: $request->username,
                    acctSessionId: null,
                    nasIdentifier: $request->nasIdentifier,
                    nasIpAddress: $request->nasIpAddress,
                    calledStationId: $request->calledStationId,
                    callingStationId: $request->callingStationId,
                    framedIpAddress: null,
                    occurredAt: $request->requestedAt,
                    reason: $exception->reason,
                    message: $exception->getMessage(),
                    payload: $this->authorizationPayload($request),
                );

                return $this->rejectAuthorization(
                    hotspotVoucher: $hotspotVoucher,
                    request: $request,
                    reason: $exception->reason,
                    message: $exception->getMessage(),
                );
            }

            $message = 'Voucher authorized successfully.';

            $this->recordEvent(
                hotspotVoucher: $hotspotVoucher,
                session: null,
                eventType: HotspotRadiusEventType::AuthAccept,
                outcome: 'accepted',
                username: $request->username,
                acctSessionId: null,
                nasIdentifier: $request->nasIdentifier,
                nasIpAddress: $request->nasIpAddress,
                calledStationId: $request->calledStationId,
                callingStationId: $request->callingStationId,
                framedIpAddress: null,
                occurredAt: $request->requestedAt,
                message: $message,
                payload: $this->authorizationPayload($request),
            );

            return $this->acceptAuthorization($hotspotVoucher, $request, $message);
        });
    }

    public function account(HotspotRadiusAccountingRequest $request): HotspotRadiusAccountingResult
    {
        return DB::transaction(function () use ($request): HotspotRadiusAccountingResult {
            $hotspotVoucher = HotspotVoucher::query()
                ->with('hotspotProfile')
                ->where('username', $request->username)
                ->lockForUpdate()
                ->first();

            if ($hotspotVoucher === null) {
                $message = 'Voucher not found for accounting packet.';

                $this->recordEvent(
                    hotspotVoucher: null,
                    session: null,
                    eventType: HotspotRadiusEventType::AccountingIgnored,
                    outcome: 'ignored',
                    username: $request->username,
                    acctSessionId: $request->acctSessionId,
                    nasIdentifier: $request->nasIdentifier,
                    nasIpAddress: $request->nasIpAddress,
                    calledStationId: $request->calledStationId,
                    callingStationId: $request->callingStationId,
                    framedIpAddress: $request->framedIpAddress,
                    occurredAt: $request->eventAt,
                    sessionTimeSeconds: $request->acctSessionTime,
                    inputOctets: $request->inputOctets,
                    outputOctets: $request->outputOctets,
                    totalOctets: $this->resolveTotalOctets($request->inputOctets, $request->outputOctets),
                    reason: HotspotRadiusRejectReason::UnknownVoucher,
                    message: $message,
                    payload: $this->accountingPayload($request),
                );

                return $this->ignoredAccounting(
                    hotspotVoucher: null,
                    session: null,
                    request: $request,
                    reason: HotspotRadiusRejectReason::UnknownVoucher,
                    message: $message,
                );
            }

            $reason = $this->hotspotVoucherStateService->resolveAccessDenialReason(
                $hotspotVoucher,
                $request->callingStationId,
                $request->eventAt,
            );

            if ($reason === HotspotRadiusRejectReason::MacMismatch) {
                $message = 'Accounting packet MAC does not match the locked voucher MAC.';

                $this->recordEvent(
                    hotspotVoucher: $hotspotVoucher,
                    session: null,
                    eventType: HotspotRadiusEventType::AccountingIgnored,
                    outcome: 'ignored',
                    username: $request->username,
                    acctSessionId: $request->acctSessionId,
                    nasIdentifier: $request->nasIdentifier,
                    nasIpAddress: $request->nasIpAddress,
                    calledStationId: $request->calledStationId,
                    callingStationId: $request->callingStationId,
                    framedIpAddress: $request->framedIpAddress,
                    occurredAt: $request->eventAt,
                    sessionTimeSeconds: $request->acctSessionTime,
                    inputOctets: $request->inputOctets,
                    outputOctets: $request->outputOctets,
                    totalOctets: $this->resolveTotalOctets($request->inputOctets, $request->outputOctets),
                    reason: $reason,
                    message: $message,
                    payload: $this->accountingPayload($request),
                );

                return $this->ignoredAccounting(
                    hotspotVoucher: $hotspotVoucher,
                    session: null,
                    request: $request,
                    reason: $reason,
                    message: $message,
                );
            }

            $sessionMutation = $this->upsertSession($hotspotVoucher, $request);
            $session = $sessionMutation['session'];
            $action = $sessionMutation['created'] ? 'created' : 'updated';

            $bytesUsed = (int) HotspotVoucherSession::query()
                ->where('hotspot_voucher_id', $hotspotVoucher->id)
                ->sum('total_octets');

            $hotspotVoucher = $this->hotspotVoucherStateService->updateBytesUsed(
                $hotspotVoucher,
                $bytesUsed,
                $request->eventAt,
            );

            $postAccountingReason = $this->hotspotVoucherStateService->resolveAccessDenialReason(
                $hotspotVoucher,
                $request->callingStationId,
                $request->eventAt,
            );

            $disconnectRequired = $this->shouldDisconnect($postAccountingReason, $request->acctStatusType);
            $message = $this->accountingMessage($request->acctStatusType, $postAccountingReason);

            $this->recordEvent(
                hotspotVoucher: $hotspotVoucher,
                session: $session,
                eventType: $request->acctStatusType->toEventType(),
                outcome: 'processed',
                username: $request->username,
                acctSessionId: $request->acctSessionId,
                nasIdentifier: $request->nasIdentifier,
                nasIpAddress: $request->nasIpAddress,
                calledStationId: $request->calledStationId,
                callingStationId: $request->callingStationId,
                framedIpAddress: $request->framedIpAddress,
                occurredAt: $request->eventAt,
                sessionTimeSeconds: (int) $session->session_time_seconds,
                inputOctets: (int) $session->input_octets,
                outputOctets: (int) $session->output_octets,
                totalOctets: (int) $session->total_octets,
                reason: $postAccountingReason,
                message: $message,
                payload: $this->accountingPayload($request),
            );

            return new HotspotRadiusAccountingResult(
                processed: true,
                action: $action,
                voucherId: $hotspotVoucher->id,
                sessionRecordId: $session->id,
                acctSessionId: $request->acctSessionId,
                username: $hotspotVoucher->username,
                voucherStatus: $hotspotVoucher->status?->value,
                sessionStatus: $session->status?->value,
                reason: $postAccountingReason,
                message: $message,
                bytesUsed: (int) $hotspotVoucher->bytes_used,
                bytesRemaining: $this->hotspotVoucherStateService->resolveRemainingBytes($hotspotVoucher),
                disconnectRequired: $disconnectRequired,
                redirectUrl: $disconnectRequired ? $this->resolveRedirectUrl($hotspotVoucher, $postAccountingReason) : null,
                radiusAttributes: $this->radiusAttributes(
                    replyMessage: $message,
                    redirectUrl: $disconnectRequired ? $this->resolveRedirectUrl($hotspotVoucher, $postAccountingReason) : null,
                ),
                context: $this->requestContext(
                    nasIdentifier: $request->nasIdentifier,
                    nasIpAddress: $request->nasIpAddress,
                    calledStationId: $request->calledStationId,
                    callingStationId: $request->callingStationId,
                    framedIpAddress: $request->framedIpAddress,
                ),
            );
        });
    }

    /**
     * @return array{session: HotspotVoucherSession, created: bool}
     */
    private function upsertSession(
        HotspotVoucher $hotspotVoucher,
        HotspotRadiusAccountingRequest $request,
    ): array {
        $session = HotspotVoucherSession::query()
            ->where('session_key', $request->sessionKey())
            ->lockForUpdate()
            ->first();

        $created = false;

        if ($session === null) {
            $created = true;
            $session = new HotspotVoucherSession([
                'hotspot_voucher_id' => $hotspotVoucher->id,
                'acct_session_id' => $request->acctSessionId,
                'session_key' => $request->sessionKey(),
            ]);
        }

        $inputOctets = $request->inputOctets ?? (int) $session->input_octets;
        $outputOctets = $request->outputOctets ?? (int) $session->output_octets;
        $sessionTimeSeconds = $request->acctSessionTime ?? (int) $session->session_time_seconds;

        $session->fill([
            'hotspot_voucher_id' => $hotspotVoucher->id,
            'acct_session_id' => $request->acctSessionId,
            'session_key' => $request->sessionKey(),
            'nas_identifier' => $request->nasIdentifier,
            'nas_ip_address' => $request->nasIpAddress,
            'called_station_id' => $request->calledStationId,
            'calling_station_id' => $request->callingStationId,
            'framed_ip_address' => $request->framedIpAddress,
            'session_time_seconds' => max(0, $sessionTimeSeconds),
            'input_octets' => max(0, $inputOctets),
            'output_octets' => max(0, $outputOctets),
            'total_octets' => max(0, $inputOctets) + max(0, $outputOctets),
            'terminate_cause' => $request->terminateCause,
            'last_payload' => $this->accountingPayload($request),
        ]);

        match ($request->acctStatusType) {
            HotspotRadiusAccountingType::Start => $session->fill([
                'status' => HotspotVoucherSessionStatus::Open->value,
                'started_at' => $session->started_at ?? $request->eventAt,
                'stopped_at' => null,
            ]),
            HotspotRadiusAccountingType::InterimUpdate => $session->fill([
                'status' => HotspotVoucherSessionStatus::Open->value,
                'started_at' => $session->started_at ?? $request->eventAt,
                'last_interim_at' => $request->eventAt,
                'stopped_at' => null,
            ]),
            HotspotRadiusAccountingType::Stop => $session->fill([
                'status' => HotspotVoucherSessionStatus::Closed->value,
                'started_at' => $session->started_at ?? $request->eventAt,
                'stopped_at' => $request->eventAt,
            ]),
        };

        $session->save();

        return [
            'session' => $session->refresh(),
            'created' => $created,
        ];
    }

    private function acceptAuthorization(
        HotspotVoucher $hotspotVoucher,
        HotspotRadiusAuthorizeRequest $request,
        string $message,
    ): HotspotRadiusAuthorizeResult {
        return new HotspotRadiusAuthorizeResult(
            accepted: true,
            voucherId: $hotspotVoucher->id,
            username: $hotspotVoucher->username,
            voucherStatus: $hotspotVoucher->status?->value,
            reason: null,
            message: $message,
            lockedMac: $hotspotVoucher->locked_mac,
            firstLoginAt: $hotspotVoucher->first_login_at,
            expiresAt: $hotspotVoucher->expires_at,
            bytesUsed: (int) $hotspotVoucher->bytes_used,
            bytesRemaining: $this->hotspotVoucherStateService->resolveRemainingBytes($hotspotVoucher),
            sessionTimeoutSeconds: $this->hotspotVoucherStateService->resolveRemainingSessionTimeout(
                $hotspotVoucher,
                $request->requestedAt,
            ),
            redirectUrl: null,
            radiusAttributes: $this->radiusAttributes(
                replyMessage: $message,
                sessionTimeout: $this->hotspotVoucherStateService->resolveRemainingSessionTimeout(
                    $hotspotVoucher,
                    $request->requestedAt,
                ),
            ),
            context: $this->requestContext(
                nasIdentifier: $request->nasIdentifier,
                nasIpAddress: $request->nasIpAddress,
                calledStationId: $request->calledStationId,
                callingStationId: $request->callingStationId,
            ),
        );
    }

    private function rejectAuthorization(
        ?HotspotVoucher $hotspotVoucher,
        HotspotRadiusAuthorizeRequest $request,
        HotspotRadiusRejectReason $reason,
        string $message,
    ): HotspotRadiusAuthorizeResult {
        $redirectUrl = $this->resolveRedirectUrl($hotspotVoucher, $reason);

        return new HotspotRadiusAuthorizeResult(
            accepted: false,
            voucherId: $hotspotVoucher?->id,
            username: $request->username,
            voucherStatus: $hotspotVoucher?->status?->value,
            reason: $reason,
            message: $message,
            lockedMac: $hotspotVoucher?->locked_mac,
            firstLoginAt: $hotspotVoucher?->first_login_at,
            expiresAt: $hotspotVoucher?->expires_at,
            bytesUsed: (int) ($hotspotVoucher?->bytes_used ?? 0),
            bytesRemaining: $hotspotVoucher !== null
                ? $this->hotspotVoucherStateService->resolveRemainingBytes($hotspotVoucher)
                : null,
            sessionTimeoutSeconds: null,
            redirectUrl: $redirectUrl,
            radiusAttributes: $this->radiusAttributes(
                replyMessage: $message,
                redirectUrl: $redirectUrl,
            ),
            context: $this->requestContext(
                nasIdentifier: $request->nasIdentifier,
                nasIpAddress: $request->nasIpAddress,
                calledStationId: $request->calledStationId,
                callingStationId: $request->callingStationId,
            ),
        );
    }

    private function ignoredAccounting(
        ?HotspotVoucher $hotspotVoucher,
        ?HotspotVoucherSession $session,
        HotspotRadiusAccountingRequest $request,
        HotspotRadiusRejectReason $reason,
        string $message,
    ): HotspotRadiusAccountingResult {
        $disconnectRequired = $this->shouldDisconnect($reason, $request->acctStatusType);
        $redirectUrl = $disconnectRequired ? $this->resolveRedirectUrl($hotspotVoucher, $reason) : null;

        return new HotspotRadiusAccountingResult(
            processed: false,
            action: 'ignored',
            voucherId: $hotspotVoucher?->id,
            sessionRecordId: $session?->id,
            acctSessionId: $request->acctSessionId,
            username: $request->username,
            voucherStatus: $hotspotVoucher?->status?->value,
            sessionStatus: $session?->status?->value,
            reason: $reason,
            message: $message,
            bytesUsed: (int) ($hotspotVoucher?->bytes_used ?? 0),
            bytesRemaining: $hotspotVoucher !== null
                ? $this->hotspotVoucherStateService->resolveRemainingBytes($hotspotVoucher)
                : null,
            disconnectRequired: $disconnectRequired,
            redirectUrl: $redirectUrl,
            radiusAttributes: $this->radiusAttributes(
                replyMessage: $message,
                redirectUrl: $redirectUrl,
            ),
            context: $this->requestContext(
                nasIdentifier: $request->nasIdentifier,
                nasIpAddress: $request->nasIpAddress,
                calledStationId: $request->calledStationId,
                callingStationId: $request->callingStationId,
                framedIpAddress: $request->framedIpAddress,
            ),
        );
    }

    private function shouldDisconnect(
        ?HotspotRadiusRejectReason $reason,
        HotspotRadiusAccountingType $acctStatusType,
    ): bool {
        return $reason !== null &&
            $reason !== HotspotRadiusRejectReason::UnknownVoucher &&
            $acctStatusType !== HotspotRadiusAccountingType::Stop;
    }

    private function accountingMessage(
        HotspotRadiusAccountingType $acctStatusType,
        ?HotspotRadiusRejectReason $reason,
    ): string {
        return match ($reason) {
            HotspotRadiusRejectReason::Expired => 'Voucher expired during accounting update.',
            HotspotRadiusRejectReason::Depleted => 'Voucher data quota is depleted.',
            HotspotRadiusRejectReason::ProfileInactive => 'Voucher profile is inactive.',
            HotspotRadiusRejectReason::MacMismatch => 'Accounting packet MAC does not match the locked voucher MAC.',
            default => match ($acctStatusType) {
                HotspotRadiusAccountingType::Start => 'Accounting start processed successfully.',
                HotspotRadiusAccountingType::InterimUpdate => 'Accounting interim update processed successfully.',
                HotspotRadiusAccountingType::Stop => 'Accounting stop processed successfully.',
            },
        };
    }

    private function radiusAttributes(
        string $replyMessage,
        ?int $sessionTimeout = null,
        ?string $redirectUrl = null,
    ): array {
        return array_filter([
            'Reply-Message' => $replyMessage,
            'Session-Timeout' => $sessionTimeout,
            'WISPr-Redirection-URL' => $redirectUrl,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function requestContext(
        ?string $nasIdentifier = null,
        ?string $nasIpAddress = null,
        ?string $calledStationId = null,
        ?string $callingStationId = null,
        ?string $framedIpAddress = null,
    ): array {
        return array_filter([
            'nas_identifier' => $nasIdentifier,
            'nas_ip_address' => $nasIpAddress,
            'called_station_id' => $calledStationId,
            'calling_station_id' => $callingStationId,
            'framed_ip_address' => $framedIpAddress,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function resolveRedirectUrl(
        ?HotspotVoucher $hotspotVoucher,
        ?HotspotRadiusRejectReason $reason,
    ): ?string {
        if (! in_array($reason, [HotspotRadiusRejectReason::Expired, HotspotRadiusRejectReason::Depleted], true)) {
            return null;
        }

        $template = trim((string) config('hotspot.radius.expired_redirect_url', ''));

        if ($template === '') {
            return null;
        }

        return str_replace(
            ['{username}', '{voucher_code}', '{reason}'],
            [
                $hotspotVoucher?->username ?? '',
                $hotspotVoucher?->voucher_code ?? '',
                $reason?->value ?? '',
            ],
            $template,
        );
    }

    private function authorizationPayload(HotspotRadiusAuthorizeRequest $request): array
    {
        return [
            'nas_identifier' => $request->nasIdentifier,
            'nas_ip_address' => $request->nasIpAddress,
            'called_station_id' => $request->calledStationId,
            'calling_station_id' => $request->callingStationId,
            'requested_at' => $request->requestedAt->toISOString(),
            'context' => $request->context,
        ];
    }

    private function accountingPayload(HotspotRadiusAccountingRequest $request): array
    {
        return [
            'acct_status_type' => $request->acctStatusType->value,
            'acct_session_id' => $request->acctSessionId,
            'nas_identifier' => $request->nasIdentifier,
            'nas_ip_address' => $request->nasIpAddress,
            'called_station_id' => $request->calledStationId,
            'calling_station_id' => $request->callingStationId,
            'framed_ip_address' => $request->framedIpAddress,
            'event_at' => $request->eventAt->toISOString(),
            'acct_session_time' => $request->acctSessionTime,
            'input_octets' => $request->inputOctets,
            'output_octets' => $request->outputOctets,
            'terminate_cause' => $request->terminateCause,
            'payload' => $request->payload,
        ];
    }

    private function resolveTotalOctets(?int $inputOctets, ?int $outputOctets): ?int
    {
        if ($inputOctets === null && $outputOctets === null) {
            return null;
        }

        return max(0, (int) $inputOctets) + max(0, (int) $outputOctets);
    }

    private function recordEvent(
        ?HotspotVoucher $hotspotVoucher,
        ?HotspotVoucherSession $session,
        HotspotRadiusEventType $eventType,
        string $outcome,
        string $username,
        ?string $acctSessionId,
        ?string $nasIdentifier,
        ?string $nasIpAddress,
        ?string $calledStationId,
        ?string $callingStationId,
        ?string $framedIpAddress,
        Carbon $occurredAt,
        ?int $sessionTimeSeconds = null,
        ?int $inputOctets = null,
        ?int $outputOctets = null,
        ?int $totalOctets = null,
        ?HotspotRadiusRejectReason $reason = null,
        ?string $message = null,
        array $payload = [],
    ): HotspotRadiusEvent {
        return HotspotRadiusEvent::query()->create([
            'hotspot_voucher_id' => $hotspotVoucher?->id,
            'hotspot_voucher_session_id' => $session?->id,
            'event_type' => $eventType->value,
            'outcome' => $outcome,
            'username' => $username,
            'acct_session_id' => $acctSessionId,
            'nas_identifier' => $nasIdentifier,
            'nas_ip_address' => $nasIpAddress,
            'called_station_id' => $calledStationId,
            'calling_station_id' => $callingStationId,
            'framed_ip_address' => $framedIpAddress,
            'session_time_seconds' => $sessionTimeSeconds,
            'input_octets' => $inputOctets,
            'output_octets' => $outputOctets,
            'total_octets' => $totalOctets,
            'reason_code' => $reason?->value,
            'message' => $message,
            'occurred_at' => $occurredAt,
            'payload' => $payload,
        ]);
    }
}
