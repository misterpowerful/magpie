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
    /**
     * Every adapter: its parser, the site driver it belongs to, and (for
     * a source-specific adapter) the host it reads — which is how the Add
     * Site URL check recognizes a source Magpie can already handle.
     *
     * @var array<string, array{class: class-string<IntakeDriver>, driver: string, host: ?string}>
     */
    private const ADAPTERS = [
        'we_work_remotely' => ['class' => WeWorkRemotelyDriver::class, 'driver' => 'rss', 'host' => 'weworkremotely.com'],
        'larajobs' => ['class' => LaraJobsDriver::class, 'driver' => 'rss', 'host' => 'larajobs.com'],
        'remotive' => ['class' => RemotiveDriver::class, 'driver' => 'api', 'host' => 'remotive.com'],
        'remote_ok' => ['class' => RemoteOkDriver::class, 'driver' => 'api', 'host' => 'remoteok.com'],
        'html' => ['class' => HtmlIntakeDriver::class, 'driver' => 'html', 'host' => null],
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

        return app(self::ADAPTERS[$adapter]['class']);
    }

    /**
     * The built-in adapter for a URL's host (ignoring "www."), if any.
     *
     * @return array{driver: string, adapter: string}|null
     */
    public function forUrl(string $url): ?array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        foreach (self::ADAPTERS as $adapter => $definition) {
            if ($definition['host'] === $host) {
                return ['driver' => $definition['driver'], 'adapter' => $adapter];
            }
        }

        return null;
    }
}
