<?php

namespace App\Services\Helpdesk;

use App\Models\Technician;

class TechnicianAutoAssignmentService
{
    public function assign(): ?Technician
    {
        $technician = Technician::query()
            ->active()
            ->orderByRaw('case when last_assigned_at is null then 0 else 1 end')
            ->orderBy('last_assigned_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($technician === null) {
            return null;
        }

        $technician->forceFill([
            'last_assigned_at' => now(),
        ])->save();

        return $technician->refresh();
    }
}
