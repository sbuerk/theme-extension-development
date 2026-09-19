<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit\Core12\Site;

use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * A real "SiteFinder" of TYPO3 v12 over a given site configuration.
 *
 * A unit test cannot double "SiteFinder" on both cores: v13.4 declares it a
 * "readonly class", which PHPUnit 10.5 refuses to double. It doubles the
 * "SiteConfiguration" instead and builds the finder around it, and the
 * constructor of the finder differs per core - v12.4 takes the configuration
 * alone, v13.4 the runtime cache as well. Picked by the major version of the
 * running core, as "Tests/Functional/ThemeSiteTrait" picks its delivery.
 */
final class SiteFinderFactory
{
    public static function create(SiteConfiguration $siteConfiguration): SiteFinder
    {
        return new SiteFinder($siteConfiguration);
    }
}
