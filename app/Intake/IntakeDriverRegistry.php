<?php

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
class IntakeDriverRegistry
{
    /** @var array<string, class-string<IntakeDriver>> */
    private const ADAPTERS = [
        'we_work_remotely' => WeWorkRemotelyDriver::class,
        'larajobs' => LaraJobsDriver::class,
        'remotive' => RemotiveDriver::class,
        'remote_ok' => RemoteOkDriver::class,
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
        $adapter = $site->config['adapter'] ?? null;

        if ($adapter === null || ! isset(self::ADAPTERS[$adapter])) {
            throw new RuntimeException("Site \"{$site->name}\" has no known adapter configured (config.adapter = ".json_encode($adapter).')');
        }

        return app(self::ADAPTERS[$adapter]);
    }
}
