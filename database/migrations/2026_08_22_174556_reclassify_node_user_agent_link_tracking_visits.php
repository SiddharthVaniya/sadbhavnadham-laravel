<?php

use App\Models\LinkTrackingVisit;
use App\Support\DeviceType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        LinkTrackingVisit::query()
            ->where(function ($query): void {
                $query->where('user_agent', 'node')
                    ->orWhere('user_agent', 'like', 'node/%')
                    ->orWhere('user_agent', 'like', '%undici%');
            })
            ->where('device_type', DeviceType::Desktop)
            ->update(['device_type' => DeviceType::Unknown]);
    }

    public function down(): void
    {
        LinkTrackingVisit::query()
            ->where(function ($query): void {
                $query->where('user_agent', 'node')
                    ->orWhere('user_agent', 'like', 'node/%')
                    ->orWhere('user_agent', 'like', '%undici%');
            })
            ->where('device_type', DeviceType::Unknown)
            ->update(['device_type' => DeviceType::Desktop]);
    }
};
