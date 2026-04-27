<?php

namespace App\Http\Controllers\Api\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\IndexTelegramGroupRequest;
use App\Http\Requests\Settings\StoreTelegramGroupRequest;
use App\Http\Requests\Settings\TestTelegramGroupRequest;
use App\Http\Requests\Settings\UpdateTelegramGroupRequest;
use App\Http\Resources\TelegramGroupResource;
use App\Models\TelegramGroup;
use App\Services\Settings\TelegramService;
use Illuminate\Http\JsonResponse;

class TelegramGroupController extends Controller
{
    public function __construct(private readonly TelegramService $telegramService) {}

    public function index(IndexTelegramGroupRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $query = TelegramGroup::query()
            ->with([
                'bot:id,router_id,bot_name,status',
                'router:id,router_code,router_name,router_role,is_active',
            ])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('group_name', 'like', "%{$search}%")
                        ->orWhere('chat_id', 'like', "%{$search}%");
                });
            })
            ->when($filters['telegram_bot_id'] ?? null, fn ($query, int $botId) => $query->where('telegram_bot_id', $botId))
            ->when($filters['router_id'] ?? null, fn ($query, int $routerId) => $query->where('router_id', $routerId))
            ->when($filters['group_type'] ?? null, fn ($query, string $type) => $query->where('group_type', $type))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status));

        $groups = $query
            ->orderBy('group_name')
            ->paginate($perPage)
            ->withQueryString();

        return $this->paginatedResponse(
            $groups,
            TelegramGroupResource::class,
            'Telegram groups retrieved successfully.',
            ['filters' => $filters],
        );
    }

    public function store(StoreTelegramGroupRequest $request): JsonResponse
    {
        $group = TelegramGroup::query()->create($request->validated());

        return $this->createdResponse(
            'Telegram group created successfully.',
            new TelegramGroupResource($group->load([
                'bot:id,router_id,bot_name,status',
                'router:id,router_code,router_name,router_role,is_active',
            ])),
        );
    }

    public function show(TelegramGroup $telegramGroup): JsonResponse
    {
        return $this->successResponse(
            'Telegram group retrieved successfully.',
            new TelegramGroupResource($telegramGroup->load([
                'bot:id,router_id,bot_name,status',
                'router:id,router_code,router_name,router_role,is_active',
            ])),
        );
    }

    public function update(UpdateTelegramGroupRequest $request, TelegramGroup $telegramGroup): JsonResponse
    {
        $telegramGroup->update($request->validated());

        return $this->successResponse(
            'Telegram group updated successfully.',
            new TelegramGroupResource($telegramGroup->refresh()->load([
                'bot:id,router_id,bot_name,status',
                'router:id,router_code,router_name,router_role,is_active',
            ])),
        );
    }

    public function destroy(TelegramGroup $telegramGroup): JsonResponse
    {
        $telegramGroup->delete();

        return $this->successResponse('Telegram group deleted successfully.');
    }

    public function test(TestTelegramGroupRequest $request, TelegramGroup $telegramGroup): JsonResponse
    {
        $result = $this->telegramService->sendGroupTestMessage(
            $telegramGroup,
            (string) $request->validated('message'),
        );

        return $this->successResponse('Telegram group test message sent.', $result);
    }
}
