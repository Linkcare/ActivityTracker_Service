<?php
use League\OAuth2\Client\Token\AccessToken;
use function GuzzleHttp\json_decode;
use League\OAuth2\Client\Provider\AbstractProvider;

class OauthResource {
    /**  @var string */
    protected $token;

    /** @var string */
    protected $refreshToken;

    /** @var int */
    protected $expiration;

    /** @var int */
    protected $expires_in;

    /** @var string */
    protected $errorCode;

    /** @var string */
    protected $errorDescription;

    /** @var string */
    protected $admissionId;

    /** @var string */
    protected $taskId;

    /** @var boolean */
    private $hasChanged = false;

    /** @var AccessToken */
    private $accessToken = null;

    /**
     * Constructs the Oauth Resource object with the tokens and different data.
     *
     * @param array $options An array of parameters to create the object.
     * @param AbstractProvider $provider
     * @throws InvalidArgumentException if 'expiration' is a string.
     */
    public function __construct(array $options = [], IActivityProvider $provider) {
        if (!empty($options['access_token'])) {
            $this->token = $options['access_token'];
        }
        if (!empty($options['refresh_token'])) {
            $this->refreshToken = $options['refresh_token'];
        }
        if (!empty($options['expiration'])) {
            if (!is_numeric($options['expiration'])) {
                throw new \InvalidArgumentException('expiration value must be an integer');
            }

            $this->expiration = $options['expiration'];
        }
        if (!empty($options['errorCode'])) {
            $this->errorCode = $options['errorCode'];
        }
        if (!empty($options['errorDescription'])) {
            $this->errorDescription = $options['errorDescription'];
        }
        if (!empty($options['admissionId'])) {
            $this->admissionId = $options['admissionId'];
        }
        if (!empty($options['taskId'])) {
            $this->taskId = $options['taskId'];
        }

        if ($this->token && $this->refreshToken && $this->expiration && !$this->errorCode && !$this->errorDescription) {
            $accessToken = new AccessToken(['access_token' => $this->token, 'refresh_token' => $this->refreshToken, 'expires' => $this->expiration]);

            if ($accessToken->hasExpired()) {
                try {
                    $accessToken = $provider->getAccessToken('refresh_token', ['refresh_token' => $this->getRefreshToken()]);
                    $this->setAccessToken($accessToken->getToken());
                    $this->setRefreshToken($accessToken->getRefreshToken());
                    $this->setExpiration($accessToken->getExpires());
                } catch (Exception $e) {
                    // The call was wrongly performed.
                    $this->setErrorCode("refresh_token_error");
                    $this->setErrorDescription($e->getMessage());
                    $accessToken = null;
                }
            }
            $this->accessToken = $accessToken;
        }
    }

    /*
     * **********************************
     * GETTERS
     * **********************************
     */

    /**
     *
     * @return AccessToken
     */
    public function getAccessToken() {
        return $this->accessToken;
    }

    /**
     *
     * @return string
     */
    public function getToken() {
        return $this->token;
    }

    /**
     *
     * @return string
     */
    public function getRefreshToken() {
        return $this->refreshToken;
    }

    /**
     *
     * @return number
     */
    public function getExpiration() {
        return $this->expiration;
    }

    /**
     * Returns the expiration date (UTC) as a string with format YYYY-MM-DD hh:mm:ss.
     *
     * @return string
     */
    public function getExpirationDate() {
        if ($this->expiration) {
            return date('Y-m-d H:i:s', $this->expiration);
        }
        return null;
    }

    /**
     *
     * @return string
     */
    public function getErrorCode() {
        return $this->errorCode;
    }

    /**
     *
     * @return string
     */
    public function getErrorDescription() {
        return $this->errorDescription;
    }

    /**
     *
     * @return string
     */
    public function getAdmissionId() {
        return $this->admissionId;
    }

    /**
     *
     * @return string
     */
    public function getTaskId() {
        return $this->taskId;
    }

    /*
     * **********************************
     * SETTERS
     * **********************************
     */

    /**
     *
     * @param string $accessToken
     */
    public function setAccessToken($accessToken) {
        if ($this->token != $accessToken) {
            $this->hasChanged = true;
        }
        $this->token = $accessToken;
    }

    /**
     *
     * @param string $refreshToken
     */
    public function setRefreshToken($refreshToken) {
        if ($this->refreshToken != $refreshToken) {
            $this->hasChanged = true;
        }
        $this->refreshToken = $refreshToken;
    }

    /**
     *
     * @param string $expiration
     */
    public function setExpiration($expiration) {
        if ($this->expiration != $expiration) {
            $this->hasChanged = true;
        }
        $this->expiration = $expiration;
    }

    /**
     *
     * @return string
     */
    public function setErrorCode($errorCode) {
        if ($errorCode) {
            $this->token = null;
            $this->refreshToken = null;
            $this->expiration = null;
            $this->hasChanged = true;
        }
        $this->errorCode = $errorCode;
    }

    /**
     *
     * @return string
     */
    public function setErrorDescription($errorDescription) {
        $this->errorDescription = $errorDescription;
    }

    /*
     * **********************************
     * METHODS
     * **********************************
     */
    /**
     *
     * @return boolean
     */
    public function isValid() {
        if ($this->errorCode || !$this->token || !$this->refreshToken || !$this->expiration) {
            return false;
        }

        return true;
    }

    /**
     *
     * @return boolean
     */
    public function changed() {
        return $this->hasChanged;
    }
}