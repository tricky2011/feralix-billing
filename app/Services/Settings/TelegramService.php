<?php

namespace App\Services\Settings;

use App\Models\TelegramBot;
use App\Models\TelegramGroup;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TelegramService
{
    /**
     * @return array{ok:bool,status:int|null,response:mixed}
     */
    public function sendTestMessage(TelegramBot $bot, string $chatId, string $message): array
    {
        if ($bot->status !== 'active') {
            throw new RuntimeException('Telegram bot is inactive.');
        }

        $response = Http::timeout(10)
            ->acceptJson()
            ->post(sprintf('https://api.telegram.org/bot%s/sendMessage', $bot->token), [
                'chat_id' => $chatId,
                'text' => $message,
                'disable_web_page_preview' => true,
            ]);

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'response' => $response->json() ?? $response->body(),
        ];
    }

    /**
     * @return array{ok:bool,status:int|null,response:mixed}
     */
    public function sendGroupTestMessage(TelegramGroup $group, string $message): array
    {
        $group->loadMissing('bot');

        if ($group->status !== 'active') {
            throw new RuntimeException('Telegram group is inactive.');
        }

        return $this->sendTestMessage($group->bot, $group->chat_id, $message);
    }
}
