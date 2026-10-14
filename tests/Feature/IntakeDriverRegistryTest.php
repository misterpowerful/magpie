<?php

use App\Intake\IntakeDriverRegistry;
use App\Intake\LaraJobsDriver;
use App\Intake\RemoteOkDriver;
use App\Intake\RemotiveDriver;
use App\Intake\WeWorkRemotelyDriver;
use App\Models\Site;

beforeEach(function () {
    $this->registry = new IntakeDriverRegistry;
});

test('resolves each known adapter to its driver class', function (string $adapter, string $expectedClass) {
    $site = Site::factory()->create(['config' => ['adapter' => $adapter]]);

    expect($this->registry->resolve($site))->toBeInstanceOf($expectedClass);
})->with([
    ['we_work_remotely', WeWorkRemotelyDriver::class],
    ['larajobs', LaraJobsDriver::class],
    ['remotive', RemotiveDriver::class],
    ['remote_ok', RemoteOkDriver::class],
]);

test('an unknown adapter throws rather than silently doing nothing', function () {
    $site = Site::factory()->create(['config' => ['adapter' => 'something_made_up']]);

    $this->registry->resolve($site);
})->throws(RuntimeException::class);

test('a site with no adapter configured throws', function () {
    $site = Site::factory()->create(['config' => []]);

    $this->registry->resolve($site);
})->throws(RuntimeException::class);
