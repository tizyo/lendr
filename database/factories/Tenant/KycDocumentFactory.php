<?php

namespace Database\Factories\Tenant;

use App\Enums\KycStatus;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\KycDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

class KycDocumentFactory extends Factory
{
    protected $model = KycDocument::class;

    public function definition(): array
    {
        return [
            'borrower_id' => Borrower::factory(),
            'document_type' => fake()->randomElement(['national_id_front', 'national_id_back', 'passport', 'utility_bill', 'selfie']),
            'file_path' => 'kyc/test/sample.jpg',
            'file_name' => 'sample.jpg',
            'file_size' => 102400,
            'mime_type' => 'image/jpeg',
            'status' => KycStatus::Pending->value,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => KycStatus::Pending->value]);
    }

    public function verified(): static
    {
        return $this->state(['status' => KycStatus::Verified->value]);
    }

    public function rejected(): static
    {
        return $this->state([
            'status' => KycStatus::Rejected->value,
            'rejection_reason' => 'Document is blurry or unreadable.',
        ]);
    }
}
