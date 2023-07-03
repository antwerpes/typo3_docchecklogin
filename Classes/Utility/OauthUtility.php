<?php declare(strict_types=1);

namespace Antwerpes\Typo3Docchecklogin\Utility;

use TYPO3\CMS\Backend\Routing\Exception\InvalidRequestTokenException;

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
     * @return bool
     *
     * @throws InvalidRequestTokenException
     */
    public function validateToken($clientId, $clientSecret, $code)
    {
        if (array_key_exists('DC_ACCESS_TOKEN', $GLOBALS)) {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $this->validateTokenUrl.'?access_token='.$GLOBALS['DC_ACCESS_TOKEN'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
            ]);

            $response = json_decode(curl_exec($curl));
            curl_close($curl);

            if ($response->boolIsValid) {
                return true;
            }

            return $this->refreshToken($clientId, $clientSecret, $code);
        }

        return $this->generateToken($clientId, $clientSecret, $code);
    }

    /**
     * Generate the Access Token with the given Parameters.
     *
     * @return bool
     *
     * @throws InvalidRequestTokenException
     */
    public function generateToken($clientId, $clientSecret, $code)
    {
        $url = $this->generateTokenUrl.'?client_id='.$clientId.'&client_secret='.$clientSecret.'&code='.$code.'&grant_type=authorization_code';
        $response = $this->createCurl($url);

        if (property_exists($response, 'error')) {
            throw new InvalidRequestTokenException(
                'DocCheck Authentication: '.$response->error_description
            );
        }

        if (property_exists($response, 'access_token')) {
            $GLOBALS['DC_ACCESS_TOKEN'] = $response->access_token;
            $GLOBALS['DC_REFRESH_TOKEN'] = $response->refresh_token;

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
     * @return bool
     *
     * @throws InvalidRequestTokenException
     */
    public function refreshToken($clientId, $clientSecret, $code)
    {
        if (array_key_exists('DC_REFRESH_TOKEN', $GLOBALS)) {
            $url = $this->generateTokenUrl.'?client_id='.$clientId.'&client_secret='.$clientSecret.'&refresh_token='.$GLOBALS['DC_REFRESH_TOKEN'].'&grant_type=refresh_token';
            $response = $this->createCurl($url);

            if ($response->access_token) {
                $GLOBALS['DC_ACCESS_TOKEN'] = $response->access_token;

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
            $url = $this->userDataUrl.'?access_token='.$GLOBALS['DC_ACCESS_TOKEN'];
            $response = $this->createCurl($url);

            if ($response->uniquekey) {
                return $response;
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

    /**
     * Helper Class to Generate the curl response.
     *
     * @return mixed
     */
    public function createCurl($url)
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ]);

        $response = json_decode(curl_exec($curl));
        curl_close($curl);

        return $response;
    }
}
