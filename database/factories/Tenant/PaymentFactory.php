<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Loan;
use App\Models\Tenant\Payment;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        static $seq = 1;
        $prefix = 'REC-'.now()->format('Ym').'-';

        return [
            'receipt_number'      => $prefix.str_pad($seq++, 5, '0', STR_PAD_LEFT),
            'loan_id'             => Loan::factory()->active(),
            'recorded_by'         => User::factory(),
            'amount'              => 1000.00,
            'principal_allocated' => 750.00,
            'interest_allocated'  => 250.00,
            'penalty_allocated'   => 0.00,
            'fee_allocated'       => 0.00,
            'payment_method'      => 'cash',
            'payment_date'        => now()->toDateString(),
            'source'              => 'manual',
            'is_overdue_payment'  => false,
        ];
    }
}
