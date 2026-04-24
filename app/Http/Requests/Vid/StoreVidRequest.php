<?php

namespace App\Http\Requests\Vid;

use App\Enums\VidStatus;
use App\Enums\VidType;
use App\Http\Requests\AdminPanelRequest;
use App\Models\RouterScope;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVidRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'vid_type' => $this->filled('vid_type') ? strtolower(trim((string) $this->input('vid_type'))) : null,
            'subnet_cidr' => $this->filled('subnet_cidr') ? trim((string) $this->input('subnet_cidr')) : null,
            'gateway_ip' => $this->filled('gateway_ip') ? trim((string) $this->input('gateway_ip')) : null,
            'pool_start_ip' => $this->filled('pool_start_ip') ? trim((string) $this->input('pool_start_ip')) : null,
            'pool_end_ip' => $this->filled('pool_end_ip') ? trim((string) $this->input('pool_end_ip')) : null,
            'sync_source' => $this->filled('sync_source') ? trim((string) $this->input('sync_source')) : null,
            'status' => $this->filled('status') ? strtolower(trim((string) $this->input('status'))) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'router_id' => ['required', 'integer', 'exists:routers,id'],
            'scope_id' => ['nullable', 'integer', 'exists:router_scopes,id'],
            'vid' => [
                'required',
                'integer',
                'between:1,4094',
                Rule::unique('vids', 'vid')->where(
                    fn ($query) => $query->where('router_id', $this->input('router_id'))
                ),
            ],
            'vid_type' => ['required', Rule::enum(VidType::class)],
            'subnet_cidr' => ['nullable', 'string', 'max:50'],
            'gateway_ip' => ['nullable', 'ip'],
            'pool_start_ip' => ['nullable', 'ip'],
            'pool_end_ip' => ['nullable', 'ip'],
            'pool_ip_count' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'rate_limit_mbps' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'sync_source' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::enum(VidStatus::class)],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'service_id' => ['nullable', 'integer', 'min:1'],
            'last_synced_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateScopeRouterConsistency($validator);
            $this->validateAssignedOwnership($validator);
            $this->validatePoolRange($validator);
            $this->validateSubnetCidr($validator);
        });
    }

    private function validateScopeRouterConsistency(Validator $validator): void
    {
        if (! $this->filled('router_id') || ! $this->filled('scope_id')) {
            return;
        }

        $scope = RouterScope::query()
            ->select(['id', 'router_id'])
            ->find($this->input('scope_id'));

        if ($scope !== null && (int) $scope->router_id !== (int) $this->input('router_id')) {
            $validator->errors()->add('scope_id', 'The selected scope does not belong to the selected router.');
        }
    }

    private function validateAssignedOwnership(Validator $validator): void
    {
        if ($this->input('status') !== VidStatus::Assigned->value) {
            return;
        }

        if (! $this->filled('customer_id') && ! $this->filled('service_id')) {
            $validator->errors()->add('customer_id', 'The customer_id or service_id field is required when status is assigned.');
        }
    }

    private function validatePoolRange(Validator $validator): void
    {
        if (! $this->filled('pool_start_ip') || ! $this->filled('pool_end_ip')) {
            return;
        }

        $poolStartIp = (string) $this->input('pool_start_ip');
        $poolEndIp = (string) $this->input('pool_end_ip');

        if (
            filter_var($poolStartIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) &&
            filter_var($poolEndIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) &&
            ip2long($poolStartIp) > ip2long($poolEndIp)
        ) {
            $validator->errors()->add('pool_end_ip', 'The pool end IP must be greater than or equal to the pool start IP.');
        }
    }

    private function validateSubnetCidr(Validator $validator): void
    {
        if (! $this->filled('subnet_cidr')) {
            return;
        }

        $subnetCidr = (string) $this->input('subnet_cidr');
        $segments = explode('/', $subnetCidr);

        if (count($segments) !== 2 || ! filter_var($segments[0], FILTER_VALIDATE_IP) || ! is_numeric($segments[1])) {
            $validator->errors()->add('subnet_cidr', 'The subnet CIDR format is invalid.');

            return;
        }

        $networkIp = $segments[0];
        $prefix = (int) $segments[1];
        $isIpv4 = (bool) filter_var($networkIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        $maxPrefix = $isIpv4 ? 32 : 128;

        if ($prefix < 0 || $prefix > $maxPrefix) {
            $validator->errors()->add('subnet_cidr', 'The subnet CIDR prefix is out of range.');
        }
    }
}
