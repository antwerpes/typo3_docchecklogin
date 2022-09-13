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
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Plugin 'DocCheck Authentication' for the 'typo3_docchecklogin' extension.
 */
class DocCheckAuthenticationController extends ActionController
{
    /**
     * @var string
     */
    public const SIGNAL_BEFORE_REDIRECT = 'beforeRedirect';

    /**
     * Frontend User array, old style.
     *
     * @var array
     */
    protected $feUser;

    public function initializeObject(): void
    {
        $this->initializeFeUser();
    }

    public function mainAction(): ResponseInterface
    {
        // is logged in?
        if ($this->feUser) {
            return new ForwardResponse('loggedIn');
        } else {
            return new ForwardResponse('loginForm');
        }
        return $this->htmlResponse();
    }

    public function loggedInAction(): void
    {
        // if the settings tell us to redirect on a successful login, do so now.
        if (true === $GLOBALS['ap_docchecklogin_do_redirect']) {
            // reset the do_redirect flag
            $GLOBALS['ap_docchecklogin_do_redirect'] = false;

            // a ?redirect_url -Parameter takes precedence
            $redirectToUri = $this->getRedirectUriFromCookie();
            // alternatively, get redirect conf from user or user group config
            if (! $redirectToUri) {
                $redirectToUri = $this->getRedirectUriFromFeLogin();
            }
            // aight, so did we find a page id to redirect to?
            if ($redirectToUri) {
                // this way works better than $this->redirect(), which will always add some bullshit params
                if (0 === mb_stripos($redirectToUri, '/')) {
                    $redirectToUri = mb_substr($redirectToUri, 1);
                }

                $hookParams = ['redirectToUri' => &$redirectToUri, 'feUser' => &$this->feUser];
                $this->callHook(self::SIGNAL_BEFORE_REDIRECT, $hookParams);
                $this->redirectToUri($redirectToUri);
            }

            return;
        }
    }

    /**
     * Tries to get a redirect configuration (Page ID) for the current user.
     *
     * @return null|int Page ID
     */
    public function getUserRedirectPid()
    {
        $redirectToPid = $GLOBALS['TSFE']->fe_user->user['felogin_redirectPid'];

        if (! $redirectToPid) {
            return;
        }

        return $redirectToPid;
    }

    /**
     * Tries to get a redirect configuration (Page ID) for the current user's primary group.
     *
     * @return null|int Page ID
     */
    public function getGroupRedirectPid()
    {
        $groupData = $GLOBALS['TSFE']->fe_user->groupData;
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('fe_groups');
        $statement = $queryBuilder->select('felogin_redirectPid')
            ->from('fe_groups')
            ->where('felogin_redirectPid<>\'\' AND uid IN ('.implode(',', $groupData['uid']).')')
            ->execute();

        while ($row = $statement->fetch()) {
            return $row[0];
        }
    }

    public function loginFormAction(): ResponseInterface
    {
        // set a redirect cookie, if a redirect_url GET Param is set
        $redirectUrl = $_GET['redirect_url'];
        // set a redirect cookie, if a return_url GET Param is set
        if (! $redirectUrl) {
            $redirectUrl = $_GET['return_url'];
        }
        // ... or if the redirect-option is chosen in the plugin
        if (! $redirectUrl && $this->settings['redirect']) {
            $redirectUrl = $this->uriBuilder->reset()->setTargetPageUid($this->settings['redirect'])->setLinkAccessRestrictedPages(true)->setCreateAbsoluteUri(true)->build();
        }

        if ($redirectUrl) {
            // store as cookie and expire in 10 minutes
            setcookie('ap_docchecklogin_redirect', $redirectUrl, (int) gmdate('U') + 600, '/');
        } else {
            // delete an older cookie if no longer needed
            setcookie('ap_docchecklogin_redirect', '', (int) gmdate('U') - 3600, '/');
        }

        $loginId = $this->settings['loginId'];
        // override given loginId if loginOverrideId is set
        if (is_numeric($this->settings['loginOverrideId'])) {
            $loginId = $this->settings['loginOverrideId'];
        }

        // most settings are injected implicitly, but a custom login template must be checked briefly
        if ('custom' === $this->settings['loginLayout']) {
            $templateKey = $this->settings['customLayout'];
        } else {
            $templateKey = $this->settings['loginLayout'].'_red';
        }

        $this->view->assign('loginId', $loginId);
        $this->view->assign('templateKey', $templateKey);
        return $this->htmlResponse();
    }

    public function getRedirectUriFromCookie()
    {
        if (array_key_exists('ap_docchecklogin_redirect', $_COOKIE)) {
            // clear the cookie
            $redirectUri = $_COOKIE['ap_docchecklogin_redirect'];
            setcookie('ap_docchecklogin_redirect', '', (int) gmdate('U') - 3600, '/');

            return $redirectUri;
        }
    }

    public function getRedirectUriFromFeLogin()
    {
        // user configuration takes precedence
        $redirectToPid = $this->getUserRedirectPid();
        $redirectUri = null;
        // only bother fetching the group redirect config if no user user-level config was found
        if (! $redirectToPid) {
            $redirectToPid = $this->getGroupRedirectPid();
        }

        if ($redirectToPid) {
            $redirectUri = $this->uriBuilder->reset()->setTargetPageUid($redirectToPid)->setCreateAbsoluteUri(true)->build();
        }

        return $redirectUri;
    }

    protected function initializeFeUser(): void
    {
        if ($GLOBALS['TSFE']->fe_user->user && $GLOBALS['TSFE']->fe_user->user['uid']) {
            $this->feUser = $GLOBALS['TSFE']->fe_user->user;
        }
    }

    /**
     * Call a specified hook.
     *
     * @param $hookName
     * @param $params
     */
    protected function callHook($hookName, &$params): void
    {
        // call hook to post-process the fetched user record
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ap_docchecklogin'][$hookName])) {
            $params['pObj'] = $this;

            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ap_docchecklogin'][$hookName] as $funcRef) {
                GeneralUtility::callUserFunction($funcRef, $params, $this);
            }
        }

        if ($this->signalSlotDispatcher) {
            $this->signalSlotDispatcher->dispatch(__CLASS__, $hookName, $params);
        }
    }
}
