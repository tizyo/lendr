<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_configs', function (Blueprint $table) {
            $table->id();
            $table->enum('plan', ['starter', 'growth', 'enterprise'])->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->decimal('price_zmw', 10, 2)->default(0);
            $table->boolean('is_custom_price')->default(false); // shows "Custom" instead of amount
            $table->json('features');                           // all feature values for this plan
            $table->timestamps();
        });

        // ── Seed default plan configurations ────────────────────────────────
        $now = now();

        DB::table('plan_configs')->insert([
            [
                'plan'            => 'starter',
                'label'           => 'Starter',
                'description'     => 'Perfect for small MFIs getting started. No credit card required.',
                'price_zmw'       => 0,
                'is_custom_price' => false,
                'features'        => json_encode([
                    'max_users'                 => 3,
                    'max_branches'              => 1,
                    'max_loan_products'         => 2,
                    'max_borrowers'             => 100,
                    'pwa'                       => false,
                    'custom_domain'             => false,
                    'bulk_operations'           => false,
                    'advanced_reports'          => false,
                    'collection_management'     => false,
                    'marketplace'               => false,
                    'disbursement_mobile_money' => false,
                    'tenant_website'            => false,
                    'api_access'                => false,
                    'exchange_rates'            => false,
                    'two_factor_auth'           => true,
                    'audit_log'                 => false,
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'plan'            => 'growth',
                'label'           => 'Growth',
                'description'     => 'For growing microfinance institutions that need more power.',
                'price_zmw'       => 1499,
                'is_custom_price' => false,
                'features'        => json_encode([
                    'max_users'                 => 20,
                    'max_branches'              => 5,
                    'max_loan_products'         => 10,
                    'max_borrowers'             => 1000,
                    'pwa'                       => true,
                    'custom_domain'             => true,
                    'bulk_operations'           => true,
                    'advanced_reports'          => true,
                    'collection_management'     => true,
                    'marketplace'               => true,
                    'disbursement_mobile_money' => true,
                    'tenant_website'            => false,
                    'api_access'                => false,
                    'exchange_rates'            => true,
                    'two_factor_auth'           => true,
                    'audit_log'                 => true,
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'plan'            => 'enterprise',
                'label'           => 'Enterprise',
                'description'     => 'Unlimited scale with dedicated support and full feature access.',
                'price_zmw'       => 0,
                'is_custom_price' => true,
                'features'        => json_encode([
                    'max_users'                 => -1,   // -1 = unlimited
                    'max_branches'              => -1,
                    'max_loan_products'         => -1,
                    'max_borrowers'             => -1,
                    'pwa'                       => true,
                    'custom_domain'             => true,
                    'bulk_operations'           => true,
                    'advanced_reports'          => true,
                    'collection_management'     => true,
                    'marketplace'               => true,
                    'disbursement_mobile_money' => true,
                    'tenant_website'            => true,
                    'api_access'                => true,
                    'exchange_rates'            => true,
                    'two_factor_auth'           => true,
                    'audit_log'                 => true,
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_configs');
    }
};
