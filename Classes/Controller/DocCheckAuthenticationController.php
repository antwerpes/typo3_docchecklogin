<?php declare(strict_types=1);

namespace Antwerpes\Typo3Docchecklogin\Controller;

/*
 *  Copyright notice
 *
 *  (c) 2013 antwerpes ag <opensource@antwerpes.de>
 *  All rights reserved
 *
 *  The TYPO3 Extension ap_docchecklogin is licensed under the MIT License
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is
 *  furnished to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in
 *  all copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 */
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Plugin 'DocCheck Authentication' for the 'typo3_docchecklogin' extension.
 */
class DocCheckAuthenticationController extends ActionController
{
    protected $extConf = [];

    /**
     * pageRepository.
     *
     * @var PageRepository
     */
    protected $pageRepository;

    public function injectPageRepository(PageRepository $pageRepository): void
    {
        $this->pageRepository = $pageRepository;
    }

    /**
     * Main show Action.
     *
     * @throws AspectNotFoundException
     */
    public function showAction(): ResponseInterface
    {
        $context = GeneralUtility::makeInstance(Context::class);
        $getParameter = $_GET;
        $loggedIn = $context->getPropertyFromAspect('frontend.user', 'isLoggedIn');

        // is logged in?
        if ($loggedIn) {
            $this->loggedIn();
        } else {
            $this->loggedOut($getParameter);
        }

        $this->view->assignMultiple([
            'loggedIn' => $loggedIn,
        ]);

        $this->view->assign('configError', $this->checkConfig());

        return $this->htmlResponse();
    }

    /**
     * @throws DBALException
     * @throws Exception
     * @throws StopActionException
     */
    public function loggedIn(): void
    {
        $redirectToUri = $this->getRedirectUriFromFeLogin() ?: $this->getRedirectUriFromCookie();

        if ($redirectToUri) {
            // Hook To overwrite the redirect
            if (array_key_exists('typo3_docchecklogin', $GLOBALS['TYPO3_CONF_VARS']['EXT']) && array_key_exists('beforeRedirect', $GLOBALS['TYPO3_CONF_VARS']['EXT']['typo3_docchecklogin'])) {
                $_params = [
                    'redirectToUri' => &$redirectToUri,
                    'pObj' => &$this,
                ];

                foreach ($GLOBALS['TYPO3_CONF_VARS']['EXT']['typo3_docchecklogin']['beforeRedirect'] as $_funcRef) {
                    GeneralUtility::callUserFunction($_funcRef, $_params, $this);
                }
            }

            $this->redirectToUri($redirectToUri);
        }
    }

    /**
     * @param $getParameter
     */
    public function loggedOut($getParameter): void
    {
        $settings = $this->settings;
        $redirectUrl = $getParameter['redirect_url'] ?? null;
        // ... or if the redirect-option is chosen in the plugin
        if (! $redirectUrl && array_key_exists('redirect', $settings)) {
            $redirectUrl = $this->uriBuilder->reset()->setTargetPageUid((int) $settings['redirect'])->setLinkAccessRestrictedPages(true)->setCreateAbsoluteUri(true)->build();
        }

        if ($redirectUrl) {
            // store as cookie and expire in 10 minutes
            setcookie('docchecklogin_redirect', $redirectUrl, (int) gmdate('U') + 600, '/');
        } else {
            // delete an older cookie if no longer needed
            setcookie('docchecklogin_redirect', '', (int) gmdate('U') - 3600, '/');
        }

        if (array_key_exists('loginId', $settings)) {
            $loginId = $settings['loginId'] ?: $settings['loginOverrideId'];
        } else {
            $loginId = $settings['loginOverrideId'];
        }

        // most settings are injected implicitly, but a custom login template must be checked briefly
        if ('custom' === $this->settings['loginLayout']) {
            $templateKey = $this->settings['customLayout'];
        } else {
            $templateKey = $this->settings['loginLayout'].'_red';
        }

        $this->view->assignMultiple([
            'loginId' => $loginId,
            'templateKey' => $templateKey,
        ]);
    }

    /**
     * Get Redirect URL form the "docchecklogin_redirect" Cookie.
     *
     * @return null|mixed
     */
    public function getRedirectUriFromCookie()
    {
        if (array_key_exists('docchecklogin_redirect', $_COOKIE)) {
            // clear the cookie
            $redirectUri = $_COOKIE['docchecklogin_redirect'];
            setcookie('docchecklogin_redirect', '', (int) gmdate('U') - 3600, '/');

            return $redirectUri;
        }
    }

    /**
     * @return null|string
     *
     * @throws DBALException
     * @throws Exception
     */
    public function getRedirectUriFromFeLogin()
    {
        $user = $this->request->getAttribute('frontend.user')->user;

        // user configuration takes precedence
        $redirectToPid = $user['felogin_redirectPid'];
        $redirectUri = null;

        // only bother fetching the group redirect config if no user user-level config was found
        if ('' !== $redirectToPid) {
            // Take only the first group for redirect
            $firstUserGroup = explode(',', $user['usergroup'])[0];

            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('fe_groups');
            $statement = $queryBuilder->select('felogin_redirectPid')
                ->from('fe_groups')
                ->where(
                    $queryBuilder->expr()->isNotNull('felogin_redirectPid'),
                    $queryBuilder->expr()->eq('uid', $firstUserGroup),
                )
                ->execute()->fetchAssociative();
            $redirectToPid = $statement['felogin_redirectPid'];
        }

        if ($redirectToPid) {
            $redirectUri = $this->uriBuilder->reset()->setTargetPageUid((int) $redirectToPid)->setCreateAbsoluteUri(true)->build();
        }

        return $redirectUri;
    }

    protected function checkConfig()
    {
        $localizationUtility = GeneralUtility::makeInstance(LocalizationUtility::class);
        $errors = false;

        // IF Dc Param is empty
        if ('' === $this->extConf['dcParam']) {
            $this->addFlashMessage(
                $localizationUtility->translate('error.message.dcParam.empty', 'typo3_docchecklogin'),
                '',
                AbstractMessage::ERROR,
                false
            );
            $errors = true;
        }

        // IF Unique Key Feature is not enabled
        if ('1' !== $this->extConf['uniqueKeyEnable']) {
            // Is there is no dummy User
            if ('' === $this->extConf['dummyUser']) {
                $this->addFlashMessage(
                    $localizationUtility->translate('error.message.dummyUser.empty', 'typo3_docchecklogin'),
                    '',
                    AbstractMessage::ERROR,
                    false
                );
                $errors = true;
            } else {
                // check if dummy User exists
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('fe_users');
                $statement = $queryBuilder->select('uid')
                    ->from('fe_users')
                    ->where(
                        $queryBuilder->expr()->eq('username', $queryBuilder->createNamedParameter($this->extConf['dummyUser'])),
                    )
                    ->execute()->fetchAssociative();

                if (! $statement) {
                    $this->addFlashMessage(
                        $localizationUtility->translate('error.message.dummyUser.notfound', 'typo3_docchecklogin'),
                        '',
                        AbstractMessage::ERROR,
                        false
                    );
                    $errors = true;
                }
            }
        }

        if ('' === $this->extConf['dummyUserPid']) {
            $this->addFlashMessage(
                $localizationUtility->translate('error.message.dummyUserPid.empty', 'typo3_docchecklogin'),
                '',
                AbstractMessage::ERROR,
                false
            );
            $errors = true;
        } else {
            // check if pid exists
            $page = $this->pageRepository->getPage((int) $this->extConf['dummyUserPid']);

            if (0 === count($page)) {
                $this->addFlashMessage(
                    $localizationUtility->translate('error.message.dummyUserPid.notfound', 'typo3_docchecklogin'),
                    '',
                    AbstractMessage::ERROR,
                    false
                );
                $errors = true;
            }
        }

        if ('1' === $this->extConf['uniqueKeyEnable'] && '' === $this->extConf['clientSecret']) {
            $this->addFlashMessage(
                $localizationUtility->translate('error.message.clientSecret.empty', 'typo3_docchecklogin'),
                '',
                AbstractMessage::ERROR,
                false
            );
            $errors = true;
        }

        if ('1' === $this->extConf['uniqueKeyEnable'] && '' === $this->extConf['uniqueKeyGroup']) {
            $this->addFlashMessage(
                $localizationUtility->translate('error.message.uniqueKeyGroup.empty', 'typo3_docchecklogin'),
                '',
                AbstractMessage::ERROR,
                false
            );
            $errors = true;
        } elseif ('1' === $this->extConf['uniqueKeyEnable'] && '' !== $this->extConf['uniqueKeyGroup']) {
            // check if usergroup exists
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('fe_groups');
            $statement = $queryBuilder->select('uid')
                ->from('fe_groups')
                ->where(
                    $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter((int) $this->extConf['uniqueKeyGroup'])),
                )
                ->execute()->fetchAssociative();

            if (! $statement) {
                $this->addFlashMessage(
                    $localizationUtility->translate('error.message.uniqueKeyGroup.notfound', 'typo3_docchecklogin'),
                    '',
                    AbstractMessage::ERROR,
                    false
                );
                $errors = true;
            }
        }

        if ('1' === $this->extConf['routingEnable'] && '' === $this->extConf['routingMap']) {
            $this->addFlashMessage(
                $localizationUtility->translate('error.message.routingMap.empty', 'typo3_docchecklogin'),
                '',
                AbstractMessage::ERROR,
                false
            );
            $errors = true;
        }

        if ('1' === $this->extConf['crawlingEnable'] && '' === $this->extConf['crawlingIP']) {
            $this->addFlashMessage(
                $localizationUtility->translate('error.message.crawlingIP.empty', 'typo3_docchecklogin'),
                '',
                AbstractMessage::ERROR,
                false
            );
            $errors = true;
        }

        if ('1' === $this->extConf['crawlingEnable'] && '' === $this->extConf['crawlingUser'] && '' === $this->extConf['dummyUser']) {
            $this->addFlashMessage(
                $localizationUtility->translate('error.message.crawlingUser.empty', 'typo3_docchecklogin'),
                '',
                AbstractMessage::ERROR,
                false
            );
            $errors = true;
        }

        if ('1' === $this->extConf['dcPersonalEnable'] && '0' === $this->extConf['uniqueKeyEnable']) {
            $this->addFlashMessage(
                $localizationUtility->translate('error.message.dcPersonalEnable.error', 'typo3_docchecklogin'),
                '',
                AbstractMessage::ERROR,
                false
            );
            $errors = true;
        }

        return $errors;
    }

    protected function initializeAction(): void
    {
        $this->extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('typo3_docchecklogin');
    }
}
