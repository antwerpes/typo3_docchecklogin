<?php declare(strict_types=1);

namespace Antwerpes\Typo3Docchecklogin\Utility;

use TYPO3\CMS\Backend\Routing\Exception\InvalidRequestTokenException;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class OauthUtility
{
    private $generateTokenUrl = 'https://login.doccheck.com/service/oauth/access_token/';
    private $validateTokenUrl = 'https://login.doccheck.com/service/oauth/access_token/checkToken.php';
    private $userDataUrl = 'https://login.doccheck.com/service/oauth/user_data/';

    /**
     * Validate The Access Token
     * When no Access Token is found, try to generate a new token
     * When one is found, check if it is still valid
     * When it is not valid, try to generate a new token.
     *
     * @param mixed $clientId
     * @param mixed $clientSecret
     * @param mixed $code
     *
     * @return bool
     *
     * @throws InvalidRequestTokenException
     */
    public function validateToken($clientId, $clientSecret, $code)
    {
        if (array_key_exists('DC_ACCESS_TOKEN', $GLOBALS)) {

            $requestFactory= GeneralUtility::makeInstance(RequestFactory::class);
            $url = $this->validateTokenUrl.'?access_token='.$GLOBALS['DC_ACCESS_TOKEN'];
            $additionalOptions = [
                'http_errors' => false,
            ];
            $response = $requestFactory->request($url, 'POST', $additionalOptions);
            $result = json_decode($response->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR);


            if (array_key_exists('boolIsValid', $result) && $result['boolIsValid']) {
                return true;
            }

            return $this->refreshToken($clientId, $clientSecret, $code);
        }

        return $this->generateToken($clientId, $clientSecret, $code);
    }

    /**
     * Generate the Access Token with the given Parameters.
     *
     * @param mixed $clientId
     * @param mixed $clientSecret
     * @param mixed $code
     *
     * @return bool
     *
     * @throws InvalidRequestTokenException
     */
    public function generateToken($clientId, $clientSecret, $code)
    {
        $requestFactory= GeneralUtility::makeInstance(RequestFactory::class);
        $url = $this->generateTokenUrl.'?client_id='.$clientId.'&client_secret='.$clientSecret.'&code='.$code.'&grant_type=authorization_code';
        $additionalOptions = [
            'http_errors' => false,
        ];
        $response = $requestFactory->request($url, 'POST', $additionalOptions);
        $result = json_decode($response->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(
                'DocCheck Authentication Error ' . $response->getStatusCode() . '- ' . $result['error_description']
            );
        }
        if (array_key_exists( 'access_token', $result)) {
            $GLOBALS['DC_ACCESS_TOKEN'] = $result['access_token'];
            $GLOBALS['DC_REFRESH_TOKEN'] = $result['refresh_token'];

            return true;
        }

        throw new InvalidRequestTokenException(
            'DocCheck Authentication: There was a Problem in receiving the access token'
        );
    }

    /**
     * Refresh the Access Token with the given refresh Token
     * When the Refresh Token is found, try to generate the access token new.
     *
     * @param mixed $clientId
     * @param mixed $clientSecret
     * @param mixed $code
     *
     * @return bool
     *
     * @throws InvalidRequestTokenException
     */
    public function refreshToken($clientId, $clientSecret, $code)
    {
        if (array_key_exists('DC_REFRESH_TOKEN', $GLOBALS)) {
            $requestFactory= GeneralUtility::makeInstance(RequestFactory::class);
            $url = $this->generateTokenUrl.'?client_id='.$clientId.'&client_secret='.$clientSecret.'&refresh_token='.$GLOBALS['DC_REFRESH_TOKEN'].'&grant_type=refresh_token';
            $additionalOptions = [
                'http_errors' => false,
            ];
            $response = $requestFactory->request($url, 'POST', $additionalOptions);
            $result = json_decode($response->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR);

            if (array_key_exists('access_token', $result)) {
                $GLOBALS['DC_ACCESS_TOKEN'] = $result['access_token'];

                return true;
            }
            throw new InvalidRequestTokenException(
                'DocCheck Authentication: There was a Problem in refreshing the access token'
            );
        } else {
            return $this->generateToken($clientId, $clientSecret, $code);
        }
    }

    /**
     * Get User Data via the Access Token.
     *
     * @return mixed
     *
     * @throws InvalidRequestTokenException
     */
    public function getUserData()
    {
        if (array_key_exists('DC_ACCESS_TOKEN', $GLOBALS)) {
            $requestFactory= GeneralUtility::makeInstance(RequestFactory::class);
            $url = $this->userDataUrl.'?access_token='.$GLOBALS['DC_ACCESS_TOKEN'];

            $additionalOptions = [
                'http_errors' => false,
            ];
            $response = $requestFactory->request($url, 'POST', $additionalOptions);
            $result = json_decode($response->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR);


            if (array_key_exists('uniquekey', $result) && $result['uniquekey']) {
                return $result;
            }
            throw new InvalidRequestTokenException(
                'DocCheck Authentication: No User Found with given access token'
            );
        } else {
            throw new InvalidRequestTokenException(
                'DocCheck Authentication: Invalid Request'
            );
        }
    }
}
