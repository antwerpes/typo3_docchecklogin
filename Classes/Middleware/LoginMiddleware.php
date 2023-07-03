<?php declare(strict_types=1);

namespace Antwerpes\Typo3Docchecklogin\Middleware;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class LoginMiddleware implements MiddlewareInterface
{
    /** @var Context */
    protected $context;
    protected $extConf = [];

    public function __construct(?Context $context = null)
    {
        $this->context = $context ?? GeneralUtility::makeInstance(Context::class);
        $this->extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('typo3_docchecklogin');
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // When crawling is disabled then don't bother to go on
        if (! $this->extConf['crawlingEnable'] || $this->context->getPropertyFromAspect('frontend.user', 'isLoggedIn')) {
            return $handler->handle($request);
        }

        // Crawler IP
        $crawlingIP = $this->extConf['crawlingIP'];

        if (! $crawlingIP) {
            throw new Exception('DocCheck Authentication: No DocCheck Crawler IP specified in Extension settings');
        }

        // IP not matching
        if ($_SERVER['REMOTE_ADDR'] !== $crawlingIP) {
            return $handler->handle($request);
        }

        // Crawler user.
        $crawlingUserName = $this->extConf['crawlingUser'];

        if (! $crawlingUserName) {
            $crawlingUserName = $this->extConf['dummyUser'];
        }

        $this->loginTmpUser($request, $crawlingUserName);

        return $handler->handle($request);
    }

    public function getUserGroup($uid)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('fe_groups');

        return $queryBuilder->select('*')
            ->from('fe_groups')->where($queryBuilder->expr()->eq('uid', $uid))->executeQuery()->fetchAssociative();
    }

    /**
     * Login a tmp User for current page.
     *
     * @param mixed $username
     */
    protected function loginTmpUser($request, $username): void
    {
        $frontendUser = $request->getAttribute('frontend.user');
        $user = $frontendUser->getRawUserByName($username);
        $frontendUser->user = $user;
        $usergroups = [];

        foreach (explode(',', $user['usergroup']) as $usergroup) {
            $usergroup = $this->getUserGroup($usergroup);
            $usergroups[$usergroup['uid']] = $usergroup;
        }

        $frontendUser->userGroups = $usergroups;
        $this->context->setAspect(
            'frontend.user',
            GeneralUtility::makeInstance(
                UserAspect::class,
                $frontendUser,
                explode(',', '0,-2,'.$user['usergroup'])
            )
        );
    }
}
