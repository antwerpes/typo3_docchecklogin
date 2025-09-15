<?php declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') || exit;

ExtensionUtility::registerPlugin(
    'Typo3Docchecklogin',
    'DocCheckAuthentication',
    'LLL:EXT:typo3_docchecklogin/Resources/Private/Language/locallang_backend.xlf:plugin.name'
);

// Add flexForms for content element configuration
$pluginSignature = 'typo3docchecklogin_doccheckauthentication';

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('tt_content', '--div--;Configuration,pi_flexform,', $pluginSignature, 'after:subheader');
ExtensionManagementUtility::addPiFlexFormValue(
    '*',
    // Flexform configuration schema file
    'FILE:EXT:typo3_docchecklogin/Configuration/FlexForms/Setup.xml',
    $pluginSignature
);
