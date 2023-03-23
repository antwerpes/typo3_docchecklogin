<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'typo3-docchecklogin',
    'description' => 'Integrate DocCheck Login with your Project',
    'category' => 'plugin',
    'author' => 'Sabrina Zwirner, Michael Paffrath',
    'author_email' => 'sabrina.zwirner@antwerpe.com, michael.paffrath@antwerpes.com',
    'state' => 'stable',
    'version' => '1.0.1',
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.0-11.5.99 || 12.2.0-12.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
