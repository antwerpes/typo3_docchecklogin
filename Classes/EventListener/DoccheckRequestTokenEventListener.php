<?php

namespace Antwerpes\Typo3Docchecklogin\EventListener;

use TYPO3\CMS\Core\Authentication\Event\BeforeRequestTokenProcessedEvent;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Security\RequestToken;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DoccheckRequestTokenEventListener
{
    public function __invoke(BeforeRequestTokenProcessedEvent $event): void
    {
        $dcLoginConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('typo3_docchecklogin');
        $user = $event->getUser();
        $queryParameter = $event->getRequest()->getQueryParams();
        $requestToken = $event->getRequestToken();
        // fine, there is a valid request token
        if ($requestToken instanceof RequestToken) {
            return;
        }

        // We try a doccheck login
        if (array_key_exists('dc', $queryParameter)) {
            // Set User Pid
            if ($user->checkPid) {
                $user->checkPid_value = $dcLoginConfiguration['dummyUserPid'];
            }
            // Create new Token
            $event->setRequestToken(RequestToken::create('core/user-auth/fe')->withMergedParams(['pid' => $dcLoginConfiguration['dummyUserPid']]));

            return;
        }

        $event->setRequestToken(
            RequestToken::create('core/user-auth/'.$user->loginType)
        );
    }
}
