<?php

namespace App\Http\Requests\Service;

use App\Enums\ServiceAccessMode;
use App\Enums\ServiceBillingStatus;
use App\Enums\ServiceIsolationMethod;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
use App\Enums\VidType;
use App\Http\Requests\AdminPanelRequest;
use App\Models\Ont;
use App\Models\Package;
use App\Models\Service;
use App\Models\Vid;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class ServicePayloadRequest extends AdminPanelRequest
{
    abstract protected function currentServiceId(): ?int;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'service_code' => $this->filled('service_code') ? Str::upper(trim((string) $this->input('service_code'))) : null,
            'monitor_pppoe_username' => $this->filled('monitor_pppoe_username') ? strtolower(trim((string) $this->input('monitor_pppoe_username'))) : null,
            'monitor_pppoe_password' => $this->has('monitor_pppoe_password') ? (string) $this->input('monitor_pppoe_password') : null,
            'access_mode' => $this->filled('access_mode') ? strtolower(trim((string) $this->input('access_mode'))) : null,
            'pppoe_username' => $this->filled('pppoe_username') ? strtolower(trim((string) $this->input('pppoe_username'))) : null,
            'pppoe_password' => $this->has('pppoe_password') ? (string) $this->input('pppoe_password') : null,
            'pppoe_isolation_profile' => $this->filled('pppoe_isolation_profile') ? trim((string) $this->input('pppoe_isolation_profile')) : null,
            'subnet_cidr' => $this->filled('subnet_cidr') ? trim((string) $this->input('subnet_cidr')) : null,
            'gateway_ip' => $this->filled('gateway_ip') ? trim((string) $this->input('gateway_ip')) : null,
            'dhcp_pool_start' => $this->filled('dhcp_pool_start') ? trim((string) $this->input('dhcp_pool_start')) : null,
            'dhcp_pool_end' => $this->filled('dhcp_pool_end') ? trim((string) $this->input('dhcp_pool_end')) : null,
            'static_ip_address' => $this->filled('static_ip_address') ? trim((string) $this->input('static_ip_address')) : null,
            'static_mac_address' => $this->filled('static_mac_address')
                ? strtoupper(str_replace('-', ':', trim((string) $this->input('static_mac_address'))))
                : null,
            'static_queue_name' => $this->filled('static_queue_name') ? trim((string) $this->input('static_queue_name')) : null,
            'isolation_method' => $this->filled('isolation_method') ? strtolower(trim((string) $this->input('isolation_method'))) : null,
            'address_list_name' => $this->filled('address_list_name') ? trim((string) $this->input('address_list_name')) : null,
            'billing_status' => $this->filled('billing_status') ? strtolower(trim((string) $this->input('billing_status'))) : null,
            'network_status' => $this->filled('network_status') ? strtolower(trim((string) $this->input('network_status'))) : null,
            'overall_status' => $this->filled('overall_status') ? strtolower(trim((string) $this->input('overall_status'))) : null,
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'router_id' => ['required', 'integer', 'exists:routers,id'],
            'olt_id' => ['nullable', 'integer', 'exists:olts,id'],
            'ont_id' => ['nullable', 'integer', 'exists:onts,id'],
            'vid_id' => ['nullable', 'integer', 'exists:vids,id'],
            'service_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('services', 'service_code')->ignore($this->currentServiceId()),
            ],
            'monitor_vid' => ['required', 'integer', 'between:1,4094'],
            'monitor_pppoe_username' => [
                'required',
                'string',
                'max:100',
                Rule::unique('services', 'monitor_pppoe_username')->ignore($this->currentServiceId()),
            ],
            'monitor_pppoe_password' => ['required', 'string', 'max:255'],
            'internet_vid' => ['required', 'integer', 'between:1,4094'],
            'access_mode' => ['nullable', Rule::enum(ServiceAccessMode::class)],
            'pppoe_username' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('services', 'pppoe_username')->ignore($this->currentServiceId()),
            ],
            'pppoe_password' => ['nullable', 'string', 'max:255'],
            'pppoe_isolation_profile' => ['nullable', 'string', 'max:100'],
            'subnet_cidr' => ['required', 'string', 'max:50'],
            'gateway_ip' => ['nullable', 'ip'],
            'dhcp_pool_start' => ['required', 'ip'],
            'dhcp_pool_end' => ['required', 'ip'],
            'static_ip_address' => ['nullable', 'ip'],
            'static_mac_address' => ['nullable', 'regex:/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/'],
            'static_queue_name' => ['nullable', 'string', 'max:100'],
            'ip_pool_count' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'rate_limit_mbps' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'isolation_method' => ['nullable', Rule::enum(ServiceIsolationMethod::class)],
            'address_list_name' => [
                'nullable',
                'string',
                'max:100',
                Rule::requiredIf(function (): bool {
                    return $this->input('overall_status') === ServiceOverallStatus::Isolated->value
                        && $this->input('isolation_method', ServiceIsolationMethod::AddressList->value) === ServiceIsolationMethod::AddressList->value;
                }),
            ],
            'billing_status' => ['required', Rule::enum(ServiceBillingStatus::class)],
            'network_status' => ['required', Rule::enum(ServiceNetworkStatus::class)],
            'overall_status' => ['required', Rule::enum(ServiceOverallStatus::class)],
            'activation_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateSubnetCidr($validator);
            $this->validateDhcpPoolRange($validator);
            $this->validateOntConsistency($validator);
            $this->validateActiveVidRequired($validator);
            $this->validateVidConsistency($validator);
            $this->validateActiveCustomerRule($validator);
            $this->validateActiveInternetVidUniqueness($validator);
            $this->validateActiveOntUniqueness($validator);
            $this->validateActiveServiceMonthlyPrice($validator);
        });
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

    private function validateDhcpPoolRange(Validator $validator): void
    {
        if (! $this->filled('dhcp_pool_start') || ! $this->filled('dhcp_pool_end')) {
            return;
        }

        $poolStart = (string) $this->input('dhcp_pool_start');
        $poolEnd = (string) $this->input('dhcp_pool_end');

        if (
            filter_var($poolStart, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) &&
            filter_var($poolEnd, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) &&
            ip2long($poolStart) > ip2long($poolEnd)
        ) {
            $validator->errors()->add('dhcp_pool_end', 'The DHCP pool end IP must be greater than or equal to the DHCP pool start IP.');
        }
    }

    private function validateOntConsistency(Validator $validator): void
    {
        if (! $this->filled('ont_id')) {
            return;
        }

        $ont = Ont::query()
            ->select(['id', 'olt_id'])
            ->find($this->input('ont_id'));

        if ($ont === null) {
            return;
        }

        if ($this->filled('olt_id') && (int) $ont->olt_id !== (int) $this->input('olt_id')) {
            $validator->errors()->add('ont_id', 'The selected ONT does not belong to the selected OLT.');
        }
    }

    private function validateVidConsistency(Validator $validator): void
    {
        if (! $this->filled('vid_id')) {
            return;
        }

        $vid = Vid::query()
            ->select(['id', 'router_id', 'vid', 'vid_type', 'service_id'])
            ->find($this->input('vid_id'));

        if ($vid === null) {
            return;
        }

        if ($vid->vid_type !== VidType::CustomerInternet) {
            $validator->errors()->add('vid_id', 'The selected VID must be a customer internet VID.');
        }

        if ((int) $vid->router_id !== (int) $this->input('router_id')) {
            $validator->errors()->add('router_id', 'The selected router must match the selected VID router.');
        }

        if ((int) $vid->vid !== (int) $this->input('internet_vid')) {
            $validator->errors()->add('internet_vid', 'The internet_vid must match the selected VID.');
        }

        if (! $this->requestedOverallStatusUsesResources()) {
            return;
        }

        if ($vid->service_id !== null && (int) $vid->service_id !== (int) ($this->currentServiceId() ?? 0)) {
            $assignedToActiveService = Service::query()
                ->active()
                ->whereKey($vid->service_id)
                ->exists();

            if ($assignedToActiveService) {
                $validator->errors()->add('vid_id', 'The selected VID is already assigned to another active service.');
            }
        }

        $query = Service::query()
            ->active()
            ->where('vid_id', $vid->id);

        if ($this->currentServiceId() !== null) {
            $query->whereKeyNot($this->currentServiceId());
        }

        if ($query->exists()) {
            $validator->errors()->add('vid_id', 'The selected VID is already used by another active service.');
        }
    }

    private function validateActiveVidRequired(Validator $validator): void
    {
        if ($this->requestedOverallStatusUsesResources() && ! $this->filled('vid_id')) {
            $validator->errors()->add('vid_id', 'An active service must have a dedicated customer internet VID.');
        }
    }

    private function validateActiveCustomerRule(Validator $validator): void
    {
        if (! $this->requestedOverallStatusUsesResources() || ! $this->filled('customer_id')) {
            return;
        }

        $query = Service::query()
            ->active()
            ->where('customer_id', $this->input('customer_id'));

        if ($this->currentServiceId() !== null) {
            $query->whereKeyNot($this->currentServiceId());
        }

        if ($query->exists()) {
            $validator->errors()->add('customer_id', 'The customer already has another active FTTH service.');
        }
    }

    private function validateActiveInternetVidUniqueness(Validator $validator): void
    {
        if (
            ! $this->requestedOverallStatusUsesResources() ||
            ! $this->filled('router_id') ||
            ! $this->filled('internet_vid')
        ) {
            return;
        }

        $query = Service::query()
            ->active()
            ->where('router_id', $this->input('router_id'))
            ->where('internet_vid', $this->input('internet_vid'));

        if ($this->currentServiceId() !== null) {
            $query->whereKeyNot($this->currentServiceId());
        }

        if ($query->exists()) {
            $validator->errors()->add('internet_vid', 'The internet VID is already used by another active service on this router.');
        }
    }

    private function validateActiveOntUniqueness(Validator $validator): void
    {
        if (! $this->requestedOverallStatusUsesResources() || ! $this->filled('ont_id')) {
            return;
        }

        $query = Service::query()
            ->active()
            ->where('ont_id', $this->input('ont_id'));

        if ($this->currentServiceId() !== null) {
            $query->whereKeyNot($this->currentServiceId());
        }

        if ($query->exists()) {
            $validator->errors()->add('ont_id', 'The selected ONT is already used by another active service.');
        }
    }

    private function requestedOverallStatusUsesResources(): bool
    {
        $status = $this->filled('overall_status')
            ? ServiceOverallStatus::tryFrom((string) $this->input('overall_status'))
            : null;

        return $status?->usesDedicatedResources() ?? false;
    }

    private function requestedOverallStatusIsBillable(): bool
    {
        $status = $this->filled('overall_status')
            ? ServiceOverallStatus::tryFrom((string) $this->input('overall_status'))
            : null;

        if ($status === null) {
            return false;
        }

        return in_array($status, [
            ServiceOverallStatus::Active,
            ServiceOverallStatus::Down,
            ServiceOverallStatus::Suspended,
            ServiceOverallStatus::Isolated,
        ], true);
    }

    private function validateActiveServiceMonthlyPrice(Validator $validator): void
    {
        if (! $this->requestedOverallStatusIsBillable() || ! $this->filled('package_id')) {
            return;
        }

        $package = Package::query()->find($this->input('package_id'));

        if ($package === null) {
            return;
        }

        $packagePrice = $package->monthly_price !== null ? (float) $package->monthly_price : null;

        if ($packagePrice === null || $packagePrice <= 0) {
            $validator->errors()->add(
                'package_id',
                'The selected package must have a monthly price greater than 0 for billable services.',
            );
        }
    }
}
