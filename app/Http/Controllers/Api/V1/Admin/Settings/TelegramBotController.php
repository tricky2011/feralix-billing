<?php

namespace App\Http\Controllers\Api\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\IndexTelegramBotRequest;
use App\Http\Requests\Settings\StoreTelegramBotRequest;
use App\Http\Requests\Settings\TestTelegramBotRequest;
use App\Http\Requests\Settings\UpdateTelegramBotRequest;
use App\Http\Resources\TelegramBotResource;
use App\Models\TelegramBot;
use App\Services\Settings\TelegramService;
use Illuminate\Http\JsonResponse;

class TelegramBotController extends Controller
{
    public function __construct(private readonly TelegramService $telegramService) {}

    public function index(IndexTelegramBotRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $query = TelegramBot::query()
            ->with('router:id,router_code,router_name,router_role,is_active')
            ->withCount('groups')
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where('bot_name', 'like', "%{$search}%"))
            ->when($filters['router_id'] ?? null, fn ($query, int $routerId) => $query->where('router_id', $routerId))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status));

        $bots = $query
            ->orderBy('bot_name')
            ->paginate($perPage)
            ->withQueryString();

        return $this->paginatedResponse(
            $bots,
            TelegramBotResource::class,
            'Telegram bots retrieved successfully.',
            ['filters' => $filters],
        );
    }

    public function store(StoreTelegramBotRequest $request): JsonResponse
    {
        $bot = TelegramBot::query()->create($request->validated());

        return $this->createdResponse(
            'Telegram bot created successfully.',
            new TelegramBotResource($bot->load('router:id,router_code,router_name,router_role,is_active')),
        );
    }

    public function show(TelegramBot $telegramBot): JsonResponse
    {
        return $this->successResponse(
            'Telegram bot retrieved successfully.',
            new TelegramBotResource($telegramBot->load('router:id,router_code,router_name,router_role,is_active')),
        );
    }

    public function update(UpdateTelegramBotRequest $request, TelegramBot $telegramBot): JsonResponse
    {
        $payload = $request->validated();

        if (($payload['token'] ?? null) === null) {
            unset($payload['token']);
        }

        $telegramBot->update($payload);

        return $this->successResponse(
            'Telegram bot updated successfully.',
            new TelegramBotResource($telegramBot->refresh()->load('router:id,router_code,router_name,router_role,is_active')),
        );
    }

    public function destroy(TelegramBot $telegramBot): JsonResponse
    {
        $telegramBot->delete();

        return $this->successResponse('Telegram bot deleted successfully.');
    }

    public function test(TestTelegramBotRequest $request, TelegramBot $telegramBot): JsonResponse
    {
        $result = $this->telegramService->sendTestMessage(
            $telegramBot,
            (string) $request->validated('chat_id'),
            (string) $request->validated('message'),
        );

        return $this->successResponse('Telegram test message sent.', $result);
    }
}
