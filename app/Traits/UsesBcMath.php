<?php

namespace App\Traits;

trait UsesBcMath
{
    /**
     * Round-half-up a BCMath string to $scale decimal places. Native bcmath
     * scale truncates rather than rounds, so this adds a half-unit at the
     * target precision before truncating.
     */
    private function bcround(string $num, int $scale = 2): string
    {
        $isNeg = str_starts_with($num, '-');
        $abs = $isNeg ? substr($num, 1) : $num;

        $increment = bcdiv('5', bcpow('10', (string) ($scale + 1)), $scale + 1);
        $rounded = bcadd($abs, $increment, $scale);

        return $isNeg && bccomp($rounded, '0', $scale) !== 0 ? '-'.$rounded : $rounded;
    }
}
