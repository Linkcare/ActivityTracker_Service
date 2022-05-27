<?php

namespace HuaweiOAuth2Client;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

class Huawei extends AbstractProvider {
    use BearerAuthorizationTrait;
    static $provider = null;

    /**
     * Huawei URLs.
     */

    /**
     *
     * @const string
     */
    const OAUTH_HUAWEI_API_URL = 'https://oauth-login.cloud.huawei.com';
    /**
     *
     * @const string
     */
    const HEALTH_HUAWEI_API_URL = 'https://health-api.cloud.huawei.com';

    /**
     * HTTP headers
     *
     * @const string
     */
    const HEADER_CONTENT = 'Content-Type';
    /**
     *
     * @const string
     */
    const HEADER_AUTH = 'Authorization';
    /**
     *
     * @const string
     */
    const HEADER_HEALTH_HUAWEI_PERMISSION = 'x-health-app-privacy';

    /**
     * Overridden to inject our options provider
     *
     * @param array $options
     * @param array $collaborators
     */
    public function __construct(array $options = [], array $collaborators = []) {
        $collaborators['optionProvider'] = new HuaweiOptionsProvider($options['clientId'], $options['clientSecret']);
        parent::__construct($options, $collaborators);
    }

    /**
     * Obtain the Huawei provider using the global variables from the configuration
     *
     * @return Huawei
     */
    static public function getProvider() {
        if (self::$provider) {
            return self::$provider;
        }
        self::$provider = new Huawei(['clientId' => $GLOBALS['HUAWEI_CLIENT_ID'], 'clientSecret' => $GLOBALS['HUAWEI_CLIENT_SECRET'],
                'redirectUri' => $GLOBALS['HUAWEI_REDIRECT_URI']]);
        return self::$provider;
    }

    /**
     * Get authorization url to begin OAuth flow.
     *
     * @return string
     */
    public function getBaseAuthorizationUrl() {
        return static::OAUTH_HUAWEI_API_URL . '/oauth2/v3/authorize';
    }

    /**
     * Get the url with parameters to begin a request.
     *
     * @return string
     */
    public function getUrlWithParams($url, array $options = []) {
        return $this->appendQuery($url, $this->getAuthorizationQuery($options));
    }

    /**
     * Get access token url to retrieve token.
     *
     * @param array $params
     *
     * @return string
     */
    public function getBaseAccessTokenUrl(array $params) {
        return static::OAUTH_HUAWEI_API_URL . '/oauth2/v3/token';
    }

    /**
     * Returns the url to retrieve the resource owners's profile/details.
     *
     * @param AccessToken $token
     *
     * @return string
     */
    public function getResourceOwnerDetailsUrl(AccessToken $token) {
        // TODO
        return static::HEALTH_HUAWEI_API_URL . '/1/user/-/profile.json';
    }

    /**
     * Returns the default scopes that we'll need for Huawei.
     * More at: https://developer.huawei.com/consumer/en/doc/development/HMSCore-Guides/scope-list-0000001055419280#section1078716412914
     *
     * @return array
     */
    public function getDefaultScopes() {
        return ['openid', 'https://www.huawei.com/healthkit/sleep.read', 'https://www.huawei.com/healthkit/step.read',
                'https://www.huawei.com/healthkit/activity.read'];
    }

    /**
     * Checks Huawei API response for errors.
     *
     * @throws IdentityProviderException
     *
     * @param ResponseInterface $response
     * @param array|string $data Parsed response data
     */
    protected function checkResponse(ResponseInterface $response, $data) {
        if ($response->getStatusCode() >= 400) {
            $errorMessage = '';
            if (!empty($data['errors'])) {
                foreach ($data['errors'] as $error) {
                    if (!empty($errorMessage)) {
                        $errorMessage .= ' , ';
                    }
                    $errorMessage .= implode(' - ', $error);
                }
            } else {
                $errorMessage = $response->getReasonPhrase();
            }
            throw new IdentityProviderException($errorMessage, $response->getStatusCode(), $response);
        }
    }

    /**
     * Returns the string used to separate scopes.
     *
     * @return string
     */
    protected function getScopeSeparator() {
        return ' ';
    }

    /**
     * Returns authorization parameters based on provided options.
     *
     * @param array $options
     *
     * @return array Authorization parameters
     */
    protected function getAuthorizationParameters(array $options) {
        $params = parent::getAuthorizationParameters($options);
        // Remove here any parameter we don't want

        return $params;
    }

    /**
     * Revoke access for an app, given its id
     *
     * @param AccessToken $accessToken
     * @param string $appId
     *
     * @return mixed
     */
    public function revoke(AccessToken $accessToken, string $appId) {
        $options = $this->getOptionProvider()->getAccessTokenOptions(self::METHOD_POST, []);

        $uri = $this->appendQuery(self::OAUTH_HUAWEI_API_URL . '/healthkit/v1/consents/' . $appId, $this->buildQueryString(['deleteData' => false]));
        $request = $this->getRequest(self::METHOD_POST, $uri, $options);

        return $this->getResponse($request);
    }

    public function parseResponse(ResponseInterface $response) {
        return parent::parseResponse($response);
    }

    protected function createResourceOwner(array $response, AccessToken $token) {}
}

?>