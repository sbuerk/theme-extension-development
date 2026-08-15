<?php

$EM_CONF['theme_extension_development'] = [
    'title' => 'Theme Extension Development',
    'description' => 'TYPO3 CMS extension theme_extension_development.',
    'version' => '1.0.0',
    'category' => 'misc',
    'state' => 'alpha',
    'author' => 'sbuerk',
    'author_email' => '',
    'author_company' => '',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.5.99',
            'typo3' => '13.4.0-14.3.99',
            'core' => '13.4.0-14.3.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
