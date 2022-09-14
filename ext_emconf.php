<?php declare(strict_types=1);

$EM_CONF[$_EXTKEY] = [
    'title' => 'typo3-docchecklogin',
    'description' => 'Integrate DocCheck Login with your Project',
    'category' => 'plugin',
    'author' => 'Sabrina Zwirner, Michael Paffrath',
    'author_email' => 'sabrina.zwirner@antwerpe.com, michael.paffrath@antwerpes.com',
    'state' => 'alpha',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.0-11.5.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];