<?php

declare(strict_types=1);

use App\Intake\IntakeDriver;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Run a site's URL through a driver the way SiteIntakeService does: one
 * request with the driver's headers, then parse(). Drivers don't fetch.
 *
 * @return array<int, array<string, mixed>>
 */
function parseSite(IntakeDriver $driver, Site $site): array
{
    return $driver->parse($site, Http::withHeaders($driver->headers())->get($site->url)->throw());
}
