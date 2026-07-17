<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class ProvisionRate extends Model
{
    protected $fillable = [
        'stage_label',
        'stage',
        'dpd_from',
        'dpd_to',
        'provision_rate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'stage'          => 'integer',
            'dpd_from'       => 'integer',
            'dpd_to'         => 'integer',
            'provision_rate' => 'decimal:4',
            'is_active'      => 'boolean',
        ];
    }

    /**
     * Find the matching provision rate for a given days-past-due value.
     */
    public static function forDpd(int $dpd): ?self
    {
        return self::where('is_active', true)
            ->where('dpd_from', '<=', $dpd)
            ->where('dpd_to', '>=', $dpd)
            ->orderByDesc('dpd_from')
            ->first();
    }

    /**
     * Seed the standard IFRS9 three-stage bands if none exist.
     */
    public static function seedDefaults(): void
    {
        if (self::count() > 0) {
            return;
        }

        $defaults = [
            ['stage_label' => 'Stage 1 — Performing',       'stage' => 1, 'dpd_from' => 0,   'dpd_to' => 29,   'provision_rate' => 1.00],
            ['stage_label' => 'Stage 2 — Under-performing',  'stage' => 2, 'dpd_from' => 30,  'dpd_to' => 89,   'provision_rate' => 10.00],
            ['stage_label' => 'Stage 3 — Non-performing',    'stage' => 3, 'dpd_from' => 90,  'dpd_to' => 9999, 'provision_rate' => 50.00],
        ];

        foreach ($defaults as $row) {
            self::create($row);
        }
    }
}
