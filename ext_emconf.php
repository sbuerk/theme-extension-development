<?php

$EM_CONF['theme_extension_development'] = [
    'title' => 'Frontend Theme for Extension Development',
    'description' => 'TYPO3 frontend theme for development purposes: extension development, DDEV based test instances and acceptance tests.',
    'version' => '1.0.0',
    'category' => 'fe',
    'state' => 'alpha',
    'author' => 'sbuerk',
    'author_email' => '',
    'author_company' => '',
    'constraints' => [
        'depends' => [
            'php' => '8.1.0-8.4.99',
            'typo3' => '12.4.22-13.4.99',
            'core' => '12.4.22-13.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
