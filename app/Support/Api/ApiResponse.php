<?php

namespace App\Support\Api;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use JsonSerializable;
use stdClass;
use UnitEnum;

class ApiResponse
{
    public static function success(
        string $message,
        mixed $data = null,
        array $meta = [],
        int $status = 200,
        ?Request $request = null,
    ): JsonResponse {
        $resolvedData = self::resolveData($data, $request);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $resolvedData ?? self::emptyObject(),
            'meta' => self::normalizeMap($meta),
        ], $status);
    }

    public static function error(
        string $message,
        array $errors = [],
        array $meta = [],
        int $status = 400,
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => self::normalizeMap($errors),
            'meta' => self::normalizeMap($meta),
        ], $status);
    }

    public static function paginationMeta(LengthAwarePaginator $paginator, array $meta = []): array
    {
        return [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'count' => $paginator->count(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more_pages' => $paginator->hasMorePages(),
                'path' => $paginator->path(),
            ],
            ...$meta,
        ];
    }

    public static function resolveData(mixed $data, ?Request $request = null): mixed
    {
        $request ??= request();

        if ($data instanceof JsonResource) {
            return $data->resolve($request);
        }

        if ($data instanceof Collection) {
            return $data
                ->map(fn (mixed $item): mixed => self::resolveData($item, $request))
                ->all();
        }

        if ($data instanceof Arrayable) {
            return self::resolveData($data->toArray(), $request);
        }

        if ($data instanceof JsonSerializable) {
            return self::resolveData($data->jsonSerialize(), $request);
        }

        if ($data instanceof UnitEnum) {
            return $data->value ?? $data->name;
        }

        if (is_array($data)) {
            $resolved = [];

            foreach ($data as $key => $value) {
                $resolved[$key] = self::resolveData($value, $request);
            }

            return $resolved;
        }

        return $data;
    }

    private static function normalizeMap(array $payload): array|stdClass
    {
        return $payload === []
            ? self::emptyObject()
            : $payload;
    }

    private static function emptyObject(): stdClass
    {
        return new stdClass();
    }
}
