<?php

/*
 * Closing the trial or upgrade banner keeps it closed on every page for the rest
 * of the sign-in, instead of it coming back on the next page.
 */

it('remembers a dismissed banner for the rest of the session', function () {
    $admin = actingAsAdmin();

    $this->actingAs($admin)->post(route('banners.dismiss', 'upgrade'))->assertNoContent();

    $this->actingAs($admin)->get(route('borrowers.index'))
        ->assertInertia(fn ($page) => $page->where('dismissed_banners', ['upgrade']));
});

it('starts with no banners dismissed', function () {
    $this->actingAs(actingAsAdmin())->get(route('borrowers.index'))
        ->assertInertia(fn ($page) => $page->where('dismissed_banners', []));
});

it('only accepts the known banners', function () {
    $this->actingAs(actingAsAdmin())->post('/banners/anything/dismiss')->assertNotFound();
});

it('needs a signed-in user', function () {
    $this->post(route('banners.dismiss', 'trial'))->assertRedirect(route('login'));
});
