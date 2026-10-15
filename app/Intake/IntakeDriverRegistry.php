<?php

declare(strict_types=1);

namespace App\Intake;

use App\Models\Site;
use RuntimeException;

/**
 * Site.driver (spec section 4) is the coarse category — email/rss/
 * api/html/browser. Within "rss" and "api" there's real per-source
 * parsing logic, selected by site.config['adapter']. A brand-new site
 * added later needs a matching adapter class registered here (or, for
 * plain HTML pages, the html driver's selector-based config instead).
 */
final class IntakeDriverRegistry
{
    /** @var array<string, class-string<IntakeDriver>> */
    private const ADAPTERS = [
        'we_work_remotely' => WeWorkRemotelyDriver::class,
        'larajobs' => LaraJobsDriver::class,
        'remotive' => RemotiveDriver::class,
        'remote_ok' => RemoteOkDriver::class,
        'html' => HtmlIntakeDriver::class,
    ];

    /**
     * @return array<int, string>
     */
    public static function knownAdapters(): array
    {
        return array_keys(self::ADAPTERS);
    }

    public function resolve(Site $site): IntakeDriver
    {
        // html has exactly one adapter, so the driver alone is enough —
        // a site saved from the form with just its selectors still runs.
        $adapter = $site->config['adapter'] ?? ($site->driver === 'html' ? 'html' : null);

        if ($adapter === null || ! isset(self::ADAPTERS[$adapter])) {
            throw new RuntimeException("Site \"{$site->name}\" has no known adapter configured (config.adapter = ".json_encode($adapter).')');
        }

        return app(self::ADAPTERS[$adapter]);
    }
}
