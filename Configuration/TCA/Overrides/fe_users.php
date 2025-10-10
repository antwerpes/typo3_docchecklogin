<?php declare(strict_types=1);

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$selectColumns = [];
$numberColumns = [];
$tempColumns = [];

$selectColumns = [
    'tx_typo3docchecklogin_gender' => [
        'LLL:EXT:typo3_docchecklogin/Resources/Private/Language/locallang_backend.xlf:user.tx_typo3docchecklogin_gender.male' => 'm',
        'LLL:EXT:typo3_docchecklogin/Resources/Private/Language/locallang_backend.xlf:user.tx_typo3docchecklogin_gender.female' => 'f',
        'LLL:EXT:typo3_docchecklogin/Resources/Private/Language/locallang_backend.xlf:user.tx_typo3docchecklogin_gender.company' => 'c',
        'LLL:EXT:typo3_docchecklogin/Resources/Private/Language/locallang_backend.xlf:user.tx_typo3docchecklogin_gender.other' => 'o',
        'LLL:EXT:typo3_docchecklogin/Resources/Private/Language/locallang_backend.xlf:user.tx_typo3docchecklogin_gender.unknown' => 'u',
    ],
];

$numberColumns = [
    'tx_typo3docchecklogin_profession',
    'tx_typo3docchecklogin_profession_parent',
    'tx_typo3docchecklogin_discipline',
];

foreach ($numberColumns as $column) {
    $tempColumns[$column] = [
        'exclude' => 1,
        'label' => 'LLL:EXT:typo3_docchecklogin/Resources/Private/Language/locallang_backend.xlf:user.'.$column,
        'config' => [
            'type' => 'input',
            'eval' => 'num',
            'default' => 0,
        ],
    ];
}

foreach ($selectColumns as $columnKey => $column) {
    $tempColumns[$columnKey] = [
        'exclude' => 1,
        'label' => 'LLL:EXT:typo3_docchecklogin/Resources/Private/Language/locallang_backend.xlf:user.'.$columnKey,
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
        ],
    ];

    foreach ($column as $key => $value) {
        $versionInfo = GeneralUtility::makeInstance(Typo3Version::class);
        if (version_compare($versionInfo->getBranch(), '12.0', '>=')) {
            $tempColumns[$columnKey]['config']['items'][] = ['label' => $key, 'value' => $value];
        } else {
            $tempColumns[$columnKey]['config']['items'][] = [$key, $value];
        }
    }
}

ExtensionManagementUtility::addTCAcolumns('fe_users', $tempColumns);
ExtensionManagementUtility::addToAllTCAtypes(
    'fe_users',
    '--div--;LLL:EXT:typo3_docchecklogin/Resources/Private/Language/locallang_backend.xlf:user.tabs.dochecklogin, tx_typo3docchecklogin_gender, tx_typo3docchecklogin_profession, tx_typo3docchecklogin_profession_parent, tx_typo3docchecklogin_discipline ',
    '',
    'after:image'
);
