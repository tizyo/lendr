<?php

namespace App\Jobs;

use App\Mail\StaffWelcome;
use App\Models\Tenant\User;
use App\Services\Mail\TenantMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWelcomeEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly User $staff,
        public readonly string $temporaryPassword,
    ) {}

    public function handle(TenantMailService $mailer): void
    {
        $mailer->send($this->staff->email, new StaffWelcome($this->staff, $this->temporaryPassword));
    }
}
