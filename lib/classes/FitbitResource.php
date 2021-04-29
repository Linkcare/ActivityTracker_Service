<?php
use InvalidArgumentException;

class FitbitResource {
    /**  @var string */
    protected $accessToken;

    /** @var string */
    protected $refreshToken;

    /** @var int */
    protected $expiration;

    /** @var string */
    protected $errorCode;

    /** @var string */
    protected $errorDescription;

    /** @var string */
    protected $admissionId;

    /** @var string */
    protected $taskId;

    /**
     * Constructs the Fitbit Resource object with the tokens and different data.
     *
     * @param array $options An array of parameters to create the object.
     * @throws InvalidArgumentException if 'expiration' is a string.
     */
    public function __construct(array $options = []) {
        if (!empty($options['access_token'])) {
            $this->access_token = $options['access_token'];
        }
        if (!empty($options['refresh_token'])) {
            $this->refresh_token = $options['refresh_token'];
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
    }

    /**
     *
     * @return string
     */
    public function getAccessToken() {
        return $this->accessToken;
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
            return date('Y-m-d h:i:s', $this->expiration);
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

    /**
     *
     * @param string $accessToken
     */
    public function setAccessToken($accessToken) {
        $this->accessToken = $accessToken;
    }

    /**
     *
     * @param string $refreshToken
     */
    public function setRefreshToken($refreshToken) {
        $this->refreshToken = $refreshToken;
    }

    /**
     *
     * @param string $expiration
     */
    public function setExpiration($expiration) {
        $this->expiration = $expiration;
    }
}