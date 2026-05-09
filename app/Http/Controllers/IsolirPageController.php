<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IsolirPageController extends Controller
{
    public function __invoke(Request $request): View
    {
        $clientIp = $request->ip();
        $ip = $request->query('ip', $clientIp);

        $service = $this->findServiceByIp($ip);

        $invoice = null;
        $customer = null;

        if ($service) {
            $customer = $service->customer;
            $invoice = Invoice::where('service_id', $service->id)
                ->whereIn('payment_status', ['unpaid', 'issued', 'overdue', 'partially_paid'])
                ->orderByDesc('due_date')
                ->with(['service'])
                ->first();
        }

        $company = [
            'name'    => config('app.company_name', config('app.name')),
            'address' => config('app.company_address'),
            'phone'   => config('app.company_phone'),
            'email'   => config('app.company_email'),
        ];

        return view('isolir', compact('service', 'customer', 'invoice', 'company', 'ip'));
    }

    private function findServiceByIp(string $ip): ?Service
    {
        if (empty($ip)) {
            return null;
        }

        $service = Service::with(['customer', 'router'])
            ->where('static_ip_address', $ip)
            ->whereIn('overall_status', ['isolated', 'suspended'])
            ->first();

        if ($service) {
            return $service;
        }

        return null;
    }
}