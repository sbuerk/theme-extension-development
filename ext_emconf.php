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
            'php' => '8.2.0-8.5.99',
            'typo3' => '13.4.0-14.3.99',
            'core' => '13.4.0-14.3.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
