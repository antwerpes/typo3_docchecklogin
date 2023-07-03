<?php declare(strict_types=1);

use Antwerpes\Typo3Docchecklogin\Controller\DocCheckAuthenticationController;
use Antwerpes\Typo3Docchecklogin\Service\DocCheckAuthenticationService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') || exit;
(function (): void {
    ExtensionUtility::configurePlugin(
        'Typo3Docchecklogin',
        'DocCheckAuthentication',
        [
            DocCheckAuthenticationController::class => 'show',
        ],
        // non-cacheable actions
        [
            DocCheckAuthenticationController::class => 'show',
        ]
    );

    // wizards
    ExtensionManagementUtility::addPageTSConfig(
        'mod {
            wizards.newContentElement.wizardItems.plugins {
                elements {
                    doccheckauthentication {
                        iconIdentifier = docchecklogin-plugin-product
                        title = LLL:EXT:typo3_docchecklogin/Resources/Private/Language/locallang_backend.xlf:plugin.name
                        description = LLL:EXT:typo3_docchecklogin/Resources/Private/Language/locallang_backend.xlf:plugin.description
                        tt_content_defValues {
                            CType = list
                            list_type = typo3docchecklogin_doccheckauthentication
                        }
                    }
                }
                show = *
            }
       }'
    );

    ExtensionManagementUtility::addService(
        // Extension Key
        'typo3_docchecklogin',
        // Service type
        'auth',
        // Service key
        \Antwerpes\Typo3Docchecklogin\Service\DocCheckAuthenticationService::class,
        [
            'title' => 'DocCheck Authentication Service',
            'description' => 'Authenticates users through the DocCheck Authentication Service',

            'subtype' => 'getUserFE,authUserFE',

            'available' => true,
            'priority' => 60,
            'quality' => 60,

            'os' => '',
            'exec' => '',

            'className' => DocCheckAuthenticationService::class,
        ]
    );
})();
