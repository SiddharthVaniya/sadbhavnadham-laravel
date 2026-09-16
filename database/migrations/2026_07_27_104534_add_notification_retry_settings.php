<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $settings = [
            [
                'key' => 'reconcile_notification_retry_hours',
                'value' => '48',
                'label' => 'Missed notification window (hours)',
                'description' => 'How many hours after payment the system keeps auto-retrying missed receipt email, WhatsApp, and Google Sheet logs. Older donations are left alone.',
            ],
            [
                'key' => 'reconcile_notification_max_attempts',
                'value' => '5',
                'label' => 'Max auto-retry attempts',
                'description' => 'Maximum times each missed channel (email / WhatsApp / sheet) is re-queued for the same donation. After this, retries stop (including permanent skips like missing email).',
            ],
            [
                'key' => 'notification_job_tries',
                'value' => '3',
                'label' => 'Queue retries for temporary failures',
                'description' => 'If AiSensy, email, or Google Sheet briefly fails, how many times the queue worker retries that one send before giving up.',
            ],
            [
                'key' => 'notification_job_backoff_seconds',
                'value' => '60',
                'label' => 'Seconds between queue retries',
                'description' => 'Wait time (in seconds) between queue retries when a send fails temporarily.',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'label' => $setting['label'],
                    'description' => $setting['description'],
                    'group' => 'delivery_retry',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'reconcile_notification_retry_hours',
            'reconcile_notification_max_attempts',
            'notification_job_tries',
            'notification_job_backoff_seconds',
        ])->delete();
    }
};
