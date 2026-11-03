<?php

use Illuminate\Routing\Route;

/*
 * Laravel matches routes in registration order, so GET /loans/{loan} declared before
 * GET /loans/create answers /loans/create itself, treats "create" as a loan id and
 * 404s. That broke "New Loan" and the borrower app's repo cart and enquiries pages.
 * No GET route may be unreachable because an earlier wildcard route matches it.
 */

it('opens the new loan form', function () {
    $this->actingAs(actingAsAdmin())->get(route('loans.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('loans/Create'));
});

it('has no GET route hidden behind an earlier wildcard route', function () {
    $routes = array_values(array_filter(
        app('router')->getRoutes()->getRoutes(),
        fn (Route $r) => in_array('GET', $r->methods(), true),
    ));

    $shadowed = [];

    foreach ($routes as $j => $later) {
        $laterParts = explode('/', $later->uri());

        for ($i = 0; $i < $j; $i++) {
            $earlier = $routes[$i];
            $earlierParts = explode('/', $earlier->uri());

            if (count($earlierParts) !== count($laterParts)) {
                continue;
            }

            $matches = true;
            $throughWildcard = false;

            foreach ($earlierParts as $k => $part) {
                if ($part === $laterParts[$k]) {
                    continue;
                }

                $isWildcard = preg_match('/^\{(\w+)\}$/', $part, $m) === 1;
                $isLiteral = ! str_starts_with($laterParts[$k], '{');
                $constraint = $isWildcard ? ($earlier->wheres[$m[1]] ?? null) : null;

                if ($isWildcard && $isLiteral && ($constraint === null || preg_match('#^(?:'.$constraint.')$#', $laterParts[$k]))) {
                    $throughWildcard = true;

                    continue;
                }

                if ($isWildcard && ! $isLiteral) {
                    continue;
                }

                $matches = false;
                break;
            }

            if ($matches && $throughWildcard) {
                $shadowed[] = "{$later->uri()} is hidden by {$earlier->uri()}";
                break;
            }
        }
    }

    expect($shadowed)->toBe([]);
});
