<?php

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;

it('logs queue job failures to laravel log', function () {
    Log::spy();

    $job = Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('resolveName')->andReturn('App\Jobs\SendDonationReceiptJob');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('uuid')->andReturn('test-job-uuid');
    $job->shouldReceive('attempts')->andReturn(3);

    event(new JobFailed(
        'database',
        $job,
        new RuntimeException('SMTP connection failed'),
    ));

    Log::shouldHaveReceived('error')
        ->once()
        ->with('Queue job failed', Mockery::on(function (array $context): bool {
            return $context['job'] === 'App\Jobs\SendDonationReceiptJob'
                && $context['exception'] === 'SMTP connection failed'
                && $context['uuid'] === 'test-job-uuid';
        }));

    Log::shouldHaveReceived('debug')
        ->once()
        ->with('Queue job failure trace', Mockery::type('array'));
});
