<?php

namespace Antwerpes\Typo3Docchecklogin\EventListener;

use TYPO3\CMS\Core\Authentication\Event\BeforeRequestTokenProcessedEvent;
use TYPO3\CMS\Core\Security\RequestToken;

class DoccheckRequestTokenEventListener
{
    public function __invoke(BeforeRequestTokenProcessedEvent $event): void
    {
        $user = $event->getUser();
        $queryParameter = $event->getRequest()->getQueryParams();
        $requestToken = $event->getRequestToken();

        // fine, there is a valid request token
        if ($requestToken instanceof RequestToken) {
            return;
        }

        // We try a doccheck login
        if (array_key_exists('dc', $queryParameter)) {
            // Create new Token
            $event->setRequestToken(RequestToken::create('core/user-auth/fe'));

            return;
        }

        $event->setRequestToken(
            RequestToken::create('core/user-auth/'.$user->loginType)
        );
    }
}
