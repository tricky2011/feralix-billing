<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityLogger
{
    public function record(
        ?User $user,
        string $action,
        string $module,
        string $description,
        ?Request $request = null,
    ): ActivityLog {
        $request ??= request();

        return ActivityLog::query()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
