<?php

namespace App\Jobs;

use App\Services\SMS\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOtpSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 10; // seconds between retries

    public function __construct(
        public readonly string $phone,
        public readonly string $otp,
        public readonly int    $ttlMinutes = 5,
    ) {}

    public function handle(SmsService $sms): void
    {
        $sms->sendOtp($this->phone, $this->otp, $this->ttlMinutes);
    }
}
