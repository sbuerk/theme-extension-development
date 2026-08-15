<?php

return [
    'BE' => [
        'debug' => true,
        'installToolPassword' => '',
        'passwordHashing' => [
            'className' => 'TYPO3\\CMS\\Core\\Crypto\\PasswordHashing\\Argon2iPasswordHash',
            'options' => [],
        ],
    ],
    'DB' => [
        'Connections' => [
            'Default' => [
                'charset' => 'utf8',
                'driver' => 'pdo_sqlite',
                // Advisory only: "additional.php" recomputes this from __DIR__ on
                // every request, so the instance resolves its database the same
                // way inside a DDEV container and on a host stack.
                'path' => __DIR__ . '/../../var/sqlite/core-14.sqlite',
            ],
        ],
    ],
    'FE' => [
        'debug' => true,
        'disableNoCacheParameter' => true,
        'passwordHashing' => [
            'className' => 'TYPO3\\CMS\\Core\\Crypto\\PasswordHashing\\Argon2iPasswordHash',
            'options' => [],
        ],
    ],
    'GFX' => [
        'processor' => 'ImageMagick',
        'processor_enabled' => true,
        // Overridden per host in "config/system/additional/", which is git-ignored.
        'processor_path' => '/usr/bin/',
    ],
    'MAIL' => [
        'transport' => 'sendmail',
        'transport_sendmail_command' => '/usr/sbin/sendmail -t -i',
    ],
    'SYS' => [
        'UTF8filesystem' => true,
        'devIPmask' => '*',
        'displayErrors' => 1,
        'encryptionKey' => '',
        'exceptionalErrors' => 12290,
        'features' => [
            'security.system.enforceAllowedFileExtensions' => true,
        ],
        'sitename' => 'Theme Extension Development, TYPO3 v14',
        'systemMaintainers' => [1],
    ],
];
