<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit\Core13\Site;

use TYPO3\CMS\Core\Cache\Frontend\NullFrontend;
use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * A real "SiteFinder" of TYPO3 v13 over a given site configuration.
 *
 * v13.4 declares "SiteFinder" a "readonly class", which PHPUnit 10.5 refuses
 * to double, and its constructor takes the runtime cache besides the
 * configuration; "getAllSites()" reads the configuration and not the cache.
 * See the v12 counterpart for why this is split.
 */
final class SiteFinderFactory
{
    public static function create(SiteConfiguration $siteConfiguration): SiteFinder
    {
        return new SiteFinder($siteConfiguration, new NullFrontend('runtime'));
    }
}
