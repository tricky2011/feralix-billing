<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Service\IndexServiceRequest;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Services\Provisioning\FtthServiceManager;
use Illuminate\Http\JsonResponse;

class ServiceController extends Controller
{
    public function __construct(private readonly FtthServiceManager $ftthServiceManager) {}

    public function index(IndexServiceRequest $request)
    {
        $services = $this->ftthServiceManager->paginate($request->validated());

        return ServiceResource::collection($services)->additional([
            'message' => 'Services retrieved successfully.',
            'filters' => $request->validated(),
        ]);
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = $this->ftthServiceManager->create($request->validated());

        return response()->json([
            'message' => 'Service created successfully.',
            'data' => new ServiceResource($service),
        ], 201);
    }

    public function show(Service $service): JsonResponse
    {
        return response()->json([
            'message' => 'Service retrieved successfully.',
            'data' => new ServiceResource($service->load([
                'customer:id,customer_code,full_name',
                'package:id,package_name,monthly_price,ip_pool_count,rate_limit_mbps',
                'router:id,router_code,router_name',
                'olt:id,olt_code,olt_name',
                'ont:id,olt_id,ont_sn,ont_name,status',
                'vid:id,router_id,vid,status,subnet_cidr,gateway_ip,customer_id,service_id',
            ])),
        ]);
    }

    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        $service = $this->ftthServiceManager->update($service, $request->validated());

        return response()->json([
            'message' => 'Service updated successfully.',
            'data' => new ServiceResource($service),
        ]);
    }

    public function destroy(Service $service): JsonResponse
    {
        $this->ftthServiceManager->delete($service);

        return response()->json([
            'message' => 'Service archived successfully.',
        ]);
    }
}
