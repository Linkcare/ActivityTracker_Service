<?php
use InvalidArgumentException;

class FitbitResource {
    /**
     *
     * @var string
     */
    protected $accessToken;

    /**
     *
     * @var string
     */
    protected $refreshToken;

    /**
     *
     * @var int
     */
    protected $expiration;

    /**
     *
     * @var string
     */
    protected $errorCode;

    /**
     *
     * @var string
     */
    protected $errorDescription;

    /**
     *
     * @var string
     */
    protected $admissionId;

    /**
     *
     * @var string
     */
    protected $taskId;

    /**
     *
     * @var array
     */
    protected $activityData = [];

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
        if (!empty($options['activityData'])) {
            $this->activityData = $options['activityData'];
        }
    }
}