<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Remembers that a staff member closed one of the plan banners, for the rest of their
 * sign-in session. Signing out clears it, so the banner returns next time.
 */
class DismissBannerController extends Controller
{
    public const SESSION_KEY = 'dismissed_banners';

    public const BANNERS = ['trial', 'upgrade'];

    public function __invoke(Request $request, string $banner): Response
    {
        $request->session()->put(self::SESSION_KEY.'.'.$banner, true);

        return response()->noContent();
    }
}
