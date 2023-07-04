<?php declare(strict_types=1);

namespace Antwerpes\Typo3Docchecklogin\Service;

use Antwerpes\Typo3Docchecklogin\Utility\OauthUtility;
use Doctrine\DBAL\DBALException;
use Exception;
use TYPO3\CMS\Core\Authentication\AuthenticationService;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DocCheckAuthenticationService extends AuthenticationService
{
    protected $extConf = [];

    public function __construct()
    {
        $this->extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('typo3_docchecklogin');
    }

    public function initAuth($mode, $loginData, $authInfo, $pObj): void
    {
        $authInfo['db_user']['checkPidList'] = $this->extConf['dummyUserPid'];
        $authInfo['db_user']['check_pid_clause'] = ' AND pid = '.$authInfo['db_user']['checkPidList'].' ';
        parent::initAuth($mode, $loginData, $authInfo, $pObj);
    }

    /**
     * Retrieve the Dummy User whenever we come from the DocCheck Service.
     *
     * @return mixed Array of all users matching current IP
     *
     * @throws Exception
     */
    public function getUser()
    {
        $dcVal = $_GET['dc'] ?? null;
        $dcCode = $_GET['code'] ?? null;
        $dcLoginId = $_GET['login_id'] ?? null;
        $dcClientSecret = $this->extConf['clientSecret'] ?? null;

        // if no dc param is given - let's not even bother getting the dummy user
        if (! $dcVal) {
            return false;
        }

        // if we are not using uniquekey feature, just get the dummy user...
        if ($dcCode && $this->extConf['uniqueKeyEnable']) {
            $user = $this->getUniqueUser($dcVal, $dcCode, $dcClientSecret, $dcLoginId);
        } else {
            $user = $this->getDummyUser();
        }

        return $user;
    }

    /**
     * Authenticate a user
     * Return 200 if the DocCheck Login is okay. This means that no more checks are needed. Otherwise authentication may fail because we may don't have a password.
     *
     * @param $user array Data of user
     *
     * @return 100|200|bool
     */
    public function authUser(array $user): int
    {
        // return values:
        // 200 - authenticated and no more checking needed - useful for IP checking without password
        // 100 - Just go on. User is not authenticated but there's still no reason to stop.
        // false - this service was the right one to authenticate the user but it failed
        // true - this service was able to authenticate the user

        $dcVal = $_GET['dc'] ?? null;
        $dcCode = $_GET['code'] ?? null;
        $dcClientSecret = $this->extConf['clientSecret'] ?? null;

        // Check if needed Parameter for oauth are given
        // Else try to auth the Dummyuser
        if ($dcCode && $dcClientSecret && $this->extConf['uniqueKeyEnable']) {
            $ok = $this->authUniqueUser($user, $dcVal);
        } else {
            $ok = $this->authDummyUser($user, $dcVal);
        }

        return $ok;
    }

    /**
     * Fetch or create a unique user.
     *
     * @param $dcVal string for routing, if wanted
     * @param mixed $dcCode
     * @param mixed $dcClientSecret
     * @param mixed $dcLoginId
     *
     * @return array user array
     *
     * @throws Exception
     */
    protected function getUniqueUser($dcVal, $dcCode, $dcClientSecret, $dcLoginId)
    {
        // Go Over Oauth when a clientSecret is provided
        $authenticateUser = null;
        $userData = null;

        if ($dcClientSecret) {
            $oauth = new OauthUtility();
            $authenticateUser = $oauth->validateToken($dcLoginId, $dcClientSecret, $dcCode);

            if (! $authenticateUser) {
                throw new Exception('DocCheck Authentication: User coudnt get authenticated.');
            }

            $userData = $oauth->getUserData();
        }

        $uniqKey = $_GET['uniquekey'] ?? null;

        if (! $this->isValidMd5($uniqKey)) {
            throw new Exception('DocCheck Authentication: Unique key is not valid.');
        }
        $group = $this->getUniqueUserGroupId($dcVal);

        // try and fetch the user
        $username = 'dc_'.$uniqKey;
        $userObject = $this->fetchUserRecord($username);

        if (! $userObject) {
            // else: we don't have a record for this user yet
            $userObject = $this->createUserRecord($username, $group, $this->extConf['dummyUserPid']);
        }

        // Double Check if we have now a user
        if ($userObject) {
            // if the group changed update it
            if ($userObject['usergroup'] !== $group) {
                $userObject = $this->updateGroupId($userObject, $group);
            }
            // now in case we have Personal enabled and a valid authenticated User, save the personal data in the database.
            if ($authenticateUser && $this->extConf['dcPersonalEnable']) {
                $userObject = $this->augmentDcPersonal($userObject, $userData);
            }

            return $userObject;
        }

        throw new Exception('DocCheck Authentication: Could not find or create an automated fe_user');
    }

    /**
     * @param mixed $username
     * @param mixed $group
     * @param mixed $pid
     *
     * @return false|mixed
     *
     * @throws DBALException
     */
    protected function createUserRecord($username, $group, $pid)
    {
        $dbUser = $this->db_user;
        $insertArray = [];

        $insertArray[$dbUser['username_column']] = $username;
        $insertArray['pid'] = $pid;
        $insertArray['usergroup'] = $group;
        $insertArray['crdate'] = $insertArray['tstamp'] = time();

        // add a salted random password
        $hashInstance = GeneralUtility::makeInstance(PasswordHashFactory::class)->getDefaultHashInstance('FE');
        $hashedPassword = $hashInstance->getHashedPassword(random_int(0, getrandmax()).time().$username);
        $insertArray[$dbUser['userident_column']] = $hashedPassword;

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($dbUser['table']);
        $queryBuilder
            ->insert($dbUser['table'])->values($insertArray)->executeStatement();

        // get the newly created user
        return $this->fetchUserRecord($username);
    }

    protected function updateGroupId($userObject, $group)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->db_user['table']);
        $queryBuilder
            ->update($this->db_user['table'])
            ->where(
                $queryBuilder->expr()->eq('uid', $userObject['uid'])
            )
            ->set('usergroup', $group)
            ->executeStatement();

        return $this->fetchUserRecord($userObject['username']);
    }

    /**
     *  If DocCheck Personal parameters are detected, add them to the user object.
     *
     * @param mixed $user
     * @param mixed $userData
     *
     * @return mixed
     *
     * @throws DBALException
     */
    protected function augmentDcPersonal($user, $userData)
    {
        // If there was an error while recieving the userData
        // 1. When the user revoked the agreement to send his data
        // 2. When you don't have the business licence
        if (property_exists($userData, 'error')) {
            return $user;
        }
        $paramMapping = [
            'address_name_title' => 'title',
            'address_name_first' => 'first_name',
            'address_name_last' => 'last_name',
            'address_gender' => 'tx_typo3docchecklogin_gender',
            'address_street' => 'address',
            'address_postal_code' => 'zip',
            'address_city' => 'city',
            'address_country_iso' => 'country',
            'email' => 'email',
            // doccheck profession and discipline: see the official technical documentation at https://crm.doccheck.com/
            'occupation_profession_id' => 'tx_typo3docchecklogin_profession',
            'occupation_discipline_id' => 'tx_typo3docchecklogin_discipline',
            'occupation_profession_parent_id' => 'tx_typo3docchecklogin_profession_parent',
        ];

        $updateArr = [];

        foreach ($paramMapping as $dcFieldname => $typo3Fieldname) {
            // only touch the fields that have been provided by dcPersonal
            if (property_exists($userData, $dcFieldname)) {
                $val = utf8_encode($userData->{$dcFieldname});
                $user[$typo3Fieldname] = html_entity_decode($val);
                $updateArr[$typo3Fieldname] = html_entity_decode($val);
            }
        }

        if (count($updateArr) > 0) {
            // save the changes to db
            // $res = $GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->db_user['table'], 'uid=' . $user['uid'], $updateArr);
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->db_user['table']);
            $queryBuilder
                ->update($this->db_user['table'])
                ->where(
                    $queryBuilder->expr()->eq('uid', $user['uid']) // if 120 would be a user parameter, use $queryBuilder->createNamedParameter($param) for security reasons
                );

            foreach ($updateArr as $updKey => $updVal) {
                $queryBuilder->set($updKey, $updVal);
            }
            $queryBuilder->execute();
        }

        return $user;
    }

    /**
     * get the group, into which generated users are supposed to be added. this can be a static configured group, or
     * - in combination with the routing feature, a resolved group id.
     *
     * @param mixed $dcVal
     *
     * @return int group id
     *
     * @throws Exception
     */
    protected function getUniqueUserGroupId($dcVal)
    {
        $grp = $this->fetchDummyUserGroup($this->extConf['dummyUser'], (int) $this->extConf['dummyUserPid']);
        // is routing enabled?
        if ($this->extConf['routingEnable']) {
            $grp = $this->getRoutedGroupId($dcVal);

            if (null === $grp) {
                $grp = $this->fetchDummyUserGroup($this->extConf['dummyUser'], (int) $this->extConf['dummyUserPid']);
            }
        }

        if (! $grp) {
            // whoops, no group found
            throw new Exception('DocCheck Authentication: Could not find front end user group '.$grp);
        }

        return $grp;
    }

    /**
     * Fetch the dummy usergroup for the given username, on a specific PID.
     *
     * @param mixed $username
     * @param mixed $pid
     *
     * @return false|mixed
     *
     * @throws DBALException
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    protected function fetchDummyUserGroup($username, $pid)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('fe_users');
        $result = $queryBuilder
            ->select('usergroup')
            ->from('fe_users')
            ->where(
                $queryBuilder->expr()->eq('username', $queryBuilder->createNamedParameter($username)),
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid))
            )
            ->executeQuery()->fetchAssociative();

        if ('' !== $result) {
            return $result['usergroup'];
        }

        return false;
    }

    /**
     * Read the routing map and find a suitable group id for this user.
     *
     * @param mixed $dcVal
     *
     * @return null|int ID of the associated group, or null if none found
     */
    protected function getRoutedGroupId($dcVal)
    {
        // first, explode the route map
        $routingMapStr = $this->extConf['routingMap'];
        $routingMapStr = explode(',', $routingMapStr);

        foreach ($routingMapStr as $routeItem) {
            [$grp, $dcParam] = explode('=', $routeItem);

            if ($dcParam === $dcVal) {
                return (int) $grp;
            }
        }
    }

    protected function isValidMd5($md5)
    {
        return ! empty($md5) && preg_match('/^[a-f0-9]{32}$/', $md5);
    }

    /**
     * Check whether
     * ... the given user is the dummy user
     * ... the dummy may sign in with this dc-param.
     *
     * @param string
     * @param mixed $dcVal
     * @param mixed $user
     *
     * @return 100|200|bool
     */
    protected function authDummyUser($user, $dcVal)
    {
        if (! $this->isDummyUser($user)) {
            // oops, not the dummy user. Try other auth methods.
            return 100;
        }

        // now check whether we have the valid dc param

        if ('' !== $dcVal && $dcVal === $this->extConf['dcParam']) {
            return 200;
        }

        return false;
    }

    /**
     * @param mixed $user
     * @param mixed $dcVal
     *
     * @throws Exception
     */
    protected function authUniqueUser($user, $dcVal)
    {
        $dcLoginId = $_GET['login_id'] ?? null;
        $dcCode = $_GET['code'] ?? null;

        if (! $this->isUniqueUser($user)) {
            // not a unique user, try other auth methods.
            return 100;
        }
        // find the correct group
        $expectedGroupId = (string) $this->getUniqueUserGroupId($dcVal);
        $actualGroupId = (string) $user['usergroup'];

        // the given dcval does not match any configured group id
        if ('' === $actualGroupId || '0' === $actualGroupId) {
            return false;
        }

        // is the unqiueUser in the expected group?
        if ($expectedGroupId !== $actualGroupId) {
            // nope.
            return false;
        }

        // Authenticate the User via Dc Login
        $oauth = new OauthUtility();
        $authenticateUser = $oauth->validateToken($dcLoginId, $this->extConf['clientSecret'], $dcCode);

        if ($authenticateUser) {
            return 200;
        }

        return false;
    }

    /**
     * Find out whether a given user is the dummy (non-unique).
     *
     * @param mixed $user
     *
     * @return bool
     */
    protected function isDummyUser($user)
    {
        return (int) $user['pid'] === (int) $this->extConf['dummyUserPid']
             && $user['username'] === $this->extConf['dummyUser'];
    }

    /**
     * Detect whether a given user has been generated by this extension.
     *
     * @param mixed $user
     *
     * @return bool
     */
    protected function isUniqueUser($user)
    {
        // if uniquekey is not even enabled, this can't be a unique key user.
        if (! $this->extConf['uniqueKeyEnable']) {
            return false;
        }

        // if the pid is incorrect, break
        if ((int) $user['pid'] !== (int) $this->extConf['dummyUserPid']) {
            return false;
        }

        // match the username pattern
        return ! (! preg_match('/^dc_[0-9a-f]{32}$/i', $user[$this->db_user['username_column']]));
    }

    /**
     * Helper function to get the generic dummy user record.
     *
     * @throws Exception
     */
    private function getDummyUser()
    {
        $dummyUserName = $this->extConf['dummyUser'];

        if (! $dummyUserName) {
            throw new Exception('DocCheck Authentication: No Dummy User specified in Extension settings');
        }

        $user = $this->fetchUserRecord($dummyUserName);

        if (! $user) {
            throw new Exception('DocCheck Authentication: Dummy User "'.$dummyUserName.'" was not found on the Page with the ID "'.$this->extConf['dummyUserPid'].'"');
        }

        return $user;
    }
}
