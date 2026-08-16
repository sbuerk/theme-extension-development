<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Dependency injection wiring of the fixture extension.
 *
 * Deliberately generic, mirroring "tests/example-fixture"'s own file: it does
 * nothing but register the classes of the extension so the Extbase dispatcher
 * can fetch "PluginController" from the container, exactly like a real
 * extension's own "Configuration/Services.php" would.
 */
return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure()
        ->private();

    $services->load(
        'TESTS\\PluginFixture\\',
        __DIR__ . '/../Classes/*',
    );
};
