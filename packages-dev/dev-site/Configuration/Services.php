<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (
    ContainerConfigurator $configurator,
    ContainerBuilder $builder,
): void {
    $services = $configurator->services();

    // Autowire and autoconfigure, keep services private. The console command
    // is registered through its "#[AsCommand]" attribute, not here.
    $services->defaults()
        ->autowire()
        ->autoconfigure()
        ->private();

    $services->load(
        'TESTS\\DevSite\\',
        __DIR__ . '/../Classes/*',
    );
};
