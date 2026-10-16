<?php

declare(strict_types=1);

/*
 * The Vue app owns every non-API URL: Laravel serves the same shell for
 * the home page and any deep link (a bookmarked Insights tab, a reload
 * on Sites), while /api stays JSON-only.
 */

beforeEach(function () {
    $this->withoutVite();
});

test('the SPA shell is served for the home page and deep links', function (string $url) {
    $this->get($url)->assertOk()->assertViewIs('app');
})->with(['/', '/insights?tab=cost&range=7d', '/admin/sites?tab=resume', '/login']);

test('an unknown API route is a JSON 404, not the SPA shell', function () {
    $this->getJson('/api/does-not-exist')
        ->assertNotFound()
        ->assertJsonStructure(['correlationId', 'code', 'message']);
});
