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

    /** @var boolean */
    private $hasChanged = false;

    /**
     * Constructs the Fitbit Resource object with the tokens and different data.
     *
     * @param array $options An array of parameters to create the object.
     * @throws InvalidArgumentException if 'expiration' is a string.
     */
    public function __construct(array $options = []) {
        if (!empty($options['access_token'])) {
            $this->accessToken = $options['access_token'];
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
    }

    /*
     * **********************************
     * GETTERS
     * **********************************
     */

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
        if ($this->accessToken != $accessToken) {
            $this->hasChanged = true;
        }
        $this->accessToken = $accessToken;
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
            $this->accessToken = null;
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
        if ($this->errorCode || !$this->accessToken || !$this->refreshToken || !$this->expiration) {
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