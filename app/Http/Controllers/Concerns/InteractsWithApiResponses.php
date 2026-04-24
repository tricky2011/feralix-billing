<?php

namespace App\Http\Controllers\Concerns;

use App\Support\Api\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

trait InteractsWithApiResponses
{
    protected function successResponse(
        string $message,
        mixed $data = null,
        array $meta = [],
        int $status = 200,
    ): JsonResponse {
        return ApiResponse::success($message, $data, $meta, $status, request());
    }

    protected function createdResponse(string $message, mixed $data = null, array $meta = []): JsonResponse
    {
        return $this->successResponse($message, $data, $meta, 201);
    }

    /**
     * @param  class-string<JsonResource>  $resourceClass
     */
    protected function paginatedResponse(
        LengthAwarePaginator $paginator,
        string $resourceClass,
        string $message,
        array $meta = [],
    ): JsonResponse {
        return $this->successResponse(
            $message,
            $resourceClass::collection($paginator->getCollection()),
            ApiResponse::paginationMeta($paginator, $meta),
        );
    }

    /**
     * @param  class-string<JsonResource>  $resourceClass
     */
    protected function collectionResponse(
        Collection|array $items,
        string $resourceClass,
        string $message,
        array $meta = [],
        int $status = 200,
    ): JsonResponse {
        $collection = $items instanceof Collection
            ? $items
            : collect($items);

        return $this->successResponse(
            $message,
            $resourceClass::collection($collection),
            $meta,
            $status,
        );
    }
}
