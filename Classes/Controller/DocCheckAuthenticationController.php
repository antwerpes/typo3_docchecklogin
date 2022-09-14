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
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

/**
 * Plugin 'DocCheck Authentication' for the 'typo3_docchecklogin' extension.
 */
class DocCheckAuthenticationController extends ActionController
{

    protected $extConf = [];

    public function initializeAction()
    {
        $this->extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('typo3_docchecklogin');
    }

    /**
     * Main show Action
     * @return ResponseInterface
     * @throws \TYPO3\CMS\Core\Context\Exception\AspectNotFoundException
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
            'loggedIn' => $loggedIn
        ]);

        return $this->htmlResponse();
    }

    /**
     * @return void
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     */
    public function loggedIn(): void
    {
        $redirectToUri = $this->getRedirectUriFromCookie() ? $this->getRedirectUriFromCookie() : $this->getRedirectUriFromFeLogin();

        if ($redirectToUri) {

            //Hook To overwrite the redirect
            if(is_array($GLOBALS['TYPO3_CONF_VARS']['EXT']['typo3_docchecklogin']['beforeRedirect'])){
                $_params = [
                    'redirectToUri' => &$redirectToUri,
                    'pObj' => &$this
                ];
                foreach($GLOBALS['TYPO3_CONF_VARS']['EXT']['typo3_docchecklogin']['beforeRedirect'] as $_funcRef) {
                    \TYPO3\CMS\Core\Utility\GeneralUtility::callUserFunction($_funcRef,$_params, $this);
                }
            }

            $this->redirectToUri($redirectToUri);
        }
    }

    /**
     * @param $getParameter
     * @return void
     */
    public function loggedOut($getParameter)
    {
        $settings = $this->settings;
        $redirectUrl = array_key_exists('redirect_url', $getParameter) ? $getParameter['redirect_url'] : null;
        // ... or if the redirect-option is chosen in the plugin
        if (!$redirectUrl && array_key_exists('redirect', $settings)) {
            $redirectUrl = $this->uriBuilder->reset()->setTargetPageUid(intval($settings['redirect']))->setLinkAccessRestrictedPages(true)->setCreateAbsoluteUri(true)->build();
        }

        if ($redirectUrl) {
            // store as cookie and expire in 10 minutes
            setcookie('docchecklogin_redirect', $redirectUrl, (int)gmdate('U') + 600, '/');
        } else {
            // delete an older cookie if no longer needed
            setcookie('docchecklogin_redirect', '', (int)gmdate('U') - 3600, '/');
        }

        if (array_key_exists('loginId', $settings)) {
            $loginId = $settings['loginId'] ? $settings['loginId'] : $settings['loginOverrideId'];
        } else {
            $loginId = $settings['loginOverrideId'];
        }

        // most settings are injected implicitly, but a custom login template must be checked briefly
        if ('custom' === $this->settings['loginLayout']) {
            $templateKey = $this->settings['customLayout'];
        } else {
            $templateKey = $this->settings['loginLayout'] . '_red';
        }

        $this->view->assignMultiple([
            'loginId' => $loginId,
            'templateKey' => $templateKey
        ]);
    }

    /**
     * Get Redirect URL form the "docchecklogin_redirect" Cookie
     * @return mixed|null
     */
    public function getRedirectUriFromCookie()
    {
        if (array_key_exists('docchecklogin_redirect', $_COOKIE)) {
            // clear the cookie
            $redirectUri = $_COOKIE['docchecklogin_redirect'];
            setcookie('docchecklogin_redirect', '', (int)gmdate('U') - 3600, '/');

            return $redirectUri;
        }

        return null;
    }

    /**
     * @return string|null
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function getRedirectUriFromFeLogin()
    {
        $user = $this->request->getAttribute('frontend.user')->user;

        // user configuration takes precedence
        $redirectToPid = $user['felogin_redirectPid'];
        $redirectUri = null;
        // only bother fetching the group redirect config if no user user-level config was found
        if (!$redirectToPid) {
            //Take only the first group for redirect
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
            $redirectUri = $this->uriBuilder->reset()->setTargetPageUid($redirectToPid)->setCreateAbsoluteUri(true)->build();
        }

        return $redirectUri;
    }

}