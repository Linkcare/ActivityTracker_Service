<?php

/**
 * ******************************** FITBIT RELATED FUNCTIONS *********************************
 */
use FitbitOAuth2Client\Fitbit;

class FitbitProvider implements IActivityProvider {

    /**
     *
     * {@inheritdoc}
     * @see IActivityProvider::getProvider()
     */
    public function getProvider() {
        return Fitbit::getProvider();
    }

    /**
     *
     * {@inheritdoc}
     * @see IActivityProvider::getProviderName()
     */
    public function getProviderName() {
        return 'fitbit';
    }

    /**
     *
     * {@inheritdoc}
     * @see IActivityProvider::getAuthorizationUrl()
     */
    public function getAuthorizationUrl($state) {
        $addParams = '&prompt=login';
        $scope = [];
        foreach ($GLOBALS['PERMISSIONS_REQUESTED'] as $permission) {
            if (in_array($permission, ['bloodpressure', 'spo2'])) {
                continue;
            }
            $scope[] = $permission;
        }
        $authorizationUrlParams = ['state' => $state, 'scope' => $scope];
        $authorizationUrl = Fitbit::getProvider()->getAuthorizationUrl($authorizationUrlParams);

        return $authorizationUrl . $addParams;
    }

    /**
     *
     * {@inheritdoc}
     * @see IActivityProvider::getAccessToken()
     */
    public function getAccessToken($grant, array $options = []) {
        return Fitbit::getProvider()->getAccessToken($grant, $options);
    }

    /**
     *
     * {@inheritdoc}
     * @see IActivityProvider::normalizeScopes()
     */
    public function normalizeScopes($scopes) {
        // The scopes of Fitbit are already normalized
        return $scopes;
    }

    /**
     *
     * {@inheritdoc}
     * @see IActivityProvider::getActivityData()
     */
    public function getActivityData(OauthResource $resource, $startDate, $endDate, $timezone = 0, $locale = 'es_ES') {
        // ONLY FOR DEBUG
        // $steps = [['dateTime' => '2021-06-28', 'value' => '3224'], ['dateTime' => '2021-06-29', 'value' => '2864'],
        // ['dateTime' => '2021-06-30', 'value' => '9230']];
        // return $steps;
        if (!$startDate) {
            $startDate = '2021-01-01';
        }
        if (!$endDate) {
            $endDate = 'today';
        }

        $accessToken = $resource->getAccessToken();
        if (!$accessToken) {
            return [];
        }

        try {
            // Obtain the activity data from FITBIT
            $baseUrl = Fitbit::BASE_FITBIT_API_URL . '/1/user/-/activities/steps/date/' . $startDate . '/' . $endDate . '.json';
            $request = Fitbit::getProvider()->getAuthenticatedRequest(Fitbit::METHOD_GET, $baseUrl, $accessToken,
                    ['headers' => [Fitbit::HEADER_ACCEPT_LOCALE => $locale]]);

            $response = Fitbit::getProvider()->getParsedResponse($request);
        } catch (Exception $e) {
            // Failed to perform the request.
            $resource->setErrorCode("request_error");
            $resource->setErrorDescription($e->getMessage());
        }

        if (!$response || !$response['activities-steps']) {
            if ($resource->getErrorCode() == null) {
                $resource->setErrorCode('unknown_error');
            }
            if ($resource->getErrorDescription() == null) {
                $resource->setErrorDescription('Some error happened when requesting activity information.');
            }
            return [];
        }

        return $response['activities-steps'];
    }

    /**
     *
     * {@inheritdoc}
     * @see IActivityProvider::getDetailedActivity()
     */
    public function getDetailedActivity(OauthResource $resource, $date, $breakdownPeriod, $timezone = 0, $locale = 'es_ES') {
        // ONLY FOR DEBUG
        // $steps = [['time' => '09:10', 'value' => '300'], ['time' => '09:15', 'value' => '400'], ['time' => '10:22', 'value' => '500'],
        // ['time' => '11:02', 'value' => '600']];
        // return $steps;
        if (!$date) {
            $date = 'today';
        }

        $accessToken = $resource->getAccessToken();
        if (!$accessToken) {
            return [];
        }

        try {
            if (!$breakdownPeriod) {
                $baseUrl = Fitbit::BASE_FITBIT_API_URL . '/1/user/-/activities/steps/date/' . $date . '/1d.json';
            } else {
                $baseUrl = Fitbit::BASE_FITBIT_API_URL . '/1/user/-/activities/steps/date/' . $date . '/1d/' . $breakdownPeriod . '.json';
            }
            // Obtain the activity data from FITBIT
            $request = Fitbit::getProvider()->getAuthenticatedRequest(Fitbit::METHOD_GET, $baseUrl, $accessToken,
                    ['headers' => [Fitbit::HEADER_ACCEPT_LOCALE => $locale]]);

            $response = Fitbit::getProvider()->getParsedResponse($request);
        } catch (Exception $e) {
            // Failed to perform the request.
            $resource->setErrorCode("request_error");
            $resource->setErrorDescription($e->getMessage());
        }

        if (!$response || !$response['activities-steps-intraday']['dataset']) {
            if ($response['activities-steps'] && !$response['activities-steps-intraday']['dataset']) {
                $resource->setErrorCode("permissions_error");
                $resource->setErrorDescription('Insufficient permissions for intraday activity data. See "Access to the Intraday Time Series".');
            } else {
                if ($resource->getErrorCode() == null) {
                    $resource->setErrorCode('unknown_error');
                }
                if ($resource->getErrorDescription() == null) {
                    $resource->setErrorDescription('Some error happened when requesting activity information.');
                }
            }
            return [];
        }

        return $response['activities-steps-intraday']['dataset'];
    }

    /**
     *
     * {@inheritdoc}
     * @see IActivityProvider::getSleepData()
     */
    public function getSleepData(OauthResource $resource, $startDate, $endDate, $timezone = 0, $locale = 'es_ES') {
        if (!$endDate) {
            return [];
        }

        if (!$startDate) {
            $date = $endDate;
        }

        $accessToken = $resource->getAccessToken();
        if (!$accessToken) {
            return [];
        }

        try {
            // Obtain the sleep data from FITBIT
            if ($date != null) {
                $baseUrl = Fitbit::BASE_FITBIT_API_URL . '/1.2/user/-/sleep/date/' . $date . '.json';
            } else {
                $baseUrl = Fitbit::BASE_FITBIT_API_URL . '/1.2/user/-/sleep/date/' . $startDate . '/' . $endDate . '.json';
            }
            $request = Fitbit::getProvider()->getAuthenticatedRequest(Fitbit::METHOD_GET, $baseUrl, $accessToken,
                    ['headers' => [Fitbit::HEADER_ACCEPT_LOCALE => $locale]]);

            $response = Fitbit::getProvider()->getParsedResponse($request);
        } catch (Exception $e) {
            // Failed to perform the request.
            $resource->setErrorCode("request_error");
            $resource->setErrorDescription($e->getMessage());
        }

        if (!$response || !$response['sleep']) {
            if ($resource->getErrorCode() == null) {
                $resource->setErrorCode('unknown_error');
            }
            if ($resource->getErrorDescription() == null) {
                $resource->setErrorDescription('Some error happened when requesting sleep information.');
            }
            return [];
        }

        // Prepare the formatted response
        $formattedResponse = [];

        foreach ($response['sleep'] as $sleep) {
            $formattedDay = [];
            $formattedData = [];
            $index = 0;

            $formattedDay['summary']['start_time'] = $sleep['startTime'];
            $formattedDay['summary']['end_time'] = $sleep['endTime'];
            // The duration is given in milliseconds
            $formattedDay['summary']['duration'] = $sleep['duration'] / 1000;

            foreach ($sleep['levels']['data'] as $data) {
                $formattedData['level'] = $sleep['level'];
                $formattedData['duration'] = $sleep['seconds'];
                $formattedData['start_time'] = $data['dateTime'];
                // The end date is the following series dateTime or the day's endTime
                if (isset($sleep['levels']['data'][$index + 1])) {
                    $formattedData['end_time'] = $sleep['levels']['data'][$index + 1]['dateTime'];
                } else {
                    $formattedData['end_time'] = $sleep['endTime'];
                }

                $formattedResponse['detail'][] = $formattedData;
                $index++;
            }

            $formattedResponse[] = $formattedDay;
        }

        return $formattedResponse;
    }

    /**
     *
     * {@inheritdoc}
     * @see IActivityProvider::getDeviceData()
     */
    public function getDeviceData(OauthResource $resource, $locale = 'es_ES') {
        $accessToken = $resource->getAccessToken();
        if (!$accessToken) {
            return [];
        }

        try {
            $baseUrl = Fitbit::BASE_FITBIT_API_URL . '/1/user/-/devices.json';
            // Obtain the activity data from FITBIT
            $request = Fitbit::getProvider()->getAuthenticatedRequest(Fitbit::METHOD_GET, $baseUrl, $accessToken,
                    ['headers' => [Fitbit::HEADER_ACCEPT_LOCALE => $locale]]);

            $response = Fitbit::getProvider()->getParsedResponse($request);
        } catch (Exception $e) {
            // Failed to perform the request.
            $resource->setErrorCode("request_error");
            $resource->setErrorDescription($e->getMessage());
        }

        if ($response === null) {
            if ($resource->getErrorCode() == null) {
                $resource->setErrorCode('unknown_error');
            }
            if ($resource->getErrorDescription() == null) {
                $resource->setErrorDescription('Some error happened when requesting device information.');
            }
            return [];
        }

        if (empty($response)) {
            // No sync information available. Return a default date
            $response[] = ['lastSyncTime' => '0000-00-00T00:00:00.000'];
        }
        $deviceData = [];
        foreach ($response as $device) {
            // Format datetime from '2021-07-01T12:23:11.000' to '2021-07-01 12:23:11'
            $syncTime = $device['lastSyncTime'];
            $device['lastSyncTime'] = str_replace('T', ' ', explode('.', $syncTime)[0]);
            $deviceData[] = $device;
        }
        return $deviceData;
    }

    /**
     *
     * {@inheritdoc}
     * @see IActivityProvider::updateProfile()
     */
    public function updateProfile(OauthResource $resource, $params = [], $locale = 'es_ES') {
        if (!empty($params)) {
            $bodyParams = [];
            if (!isNullOrEmpty($params["fullname"])) {
                $bodyParams[] = "fullname=" . self::formatNameForFitbit($params["fullname"]);
            }
            if (!isNullOrEmpty($params["gender"])) {
                $bodyParams[] = "gender=" . $params["gender"];
            }
            if (!isNullOrEmpty($params["birthday"])) {
                $bodyParams[] = "birthday=" . $params["birthday"];
            }
            if (!isNullOrEmpty($params["height"])) {
                $bodyParams[] = "height=" . $params["height"];
            }
            if (!isNullOrEmpty($params["strideLengthWalking"])) {
                $bodyParams[] = "strideLengthWalking=" . $params["strideLengthWalking"];
            }
            if (!isNullOrEmpty($params["strideLengthRunning"])) {
                $bodyParams[] = "strideLengthRunning=" . $params["strideLengthRunning"];
            }
            $paramsString = join("&", $bodyParams);
        } else {
            return [];
        }

        $accessToken = $resource->getAccessToken();
        if (!$accessToken) {
            return [];
        }

        try {
            $baseUrl = Fitbit::BASE_FITBIT_API_URL . '/1/user/-/profile.json';
            // Update the user's FITBIT profile
            $request = Fitbit::getProvider()->getAuthenticatedRequest(Fitbit::METHOD_POST, $baseUrl, $accessToken,
                    ['headers' => [Fitbit::HEADER_ACCEPT_LOCALE => $locale, 'Content-Type' => 'application/x-www-form-urlencoded'],
                            'body' => $paramsString]);

            $response = Fitbit::getProvider()->getParsedResponse($request);
        } catch (Exception $e) {
            // Failed to perform the request.
            $resource->setErrorCode("request_error");
            $resource->setErrorDescription($e->getMessage());
        }

        if ($response && $response['user']) {
            return $response['user'];
        } else {
            if ($resource->getErrorCode() == null) {
                $resource->setErrorCode('unknown_error');
            }
            if ($resource->getErrorDescription() == null) {
                $resource->setErrorDescription("Some error happened when updating the user's profile.");
            }
            return [];
        }
    }

    /**
     *
     * {@inheritdoc}
     * @see IActivityProvider::updateActivityGoals()
     */
    public function updateActivityGoals(OauthResource $resource, $params = [], $period = 'weekly', $locale = 'es_ES') {
        if (!empty($params)) {
            $bodyParams = [];
            if (!isNullOrEmpty($params["distance"])) {
                $bodyParams[] = "distance=" . $params["distance"];
            }
            if (!isNullOrEmpty($params["steps"])) {
                $bodyParams[] = "steps=" . $params["steps"];
            }
            $paramsString = join("&", $bodyParams);
        } else {
            return [];
        }

        $accessToken = $resource->getAccessToken();
        if (!$accessToken) {
            return [];
        }

        try {
            $baseUrl = Fitbit::BASE_FITBIT_API_URL . '/1/user/-/activities/goals/' . $period . '.json';
            // Update the user's FITBIT profile
            $request = Fitbit::getProvider()->getAuthenticatedRequest(Fitbit::METHOD_POST, $baseUrl, $accessToken,
                    ['headers' => [Fitbit::HEADER_ACCEPT_LOCALE => $locale, 'Content-Type' => 'application/x-www-form-urlencoded'],
                            'body' => $paramsString]);

            $response = Fitbit::getProvider()->getParsedResponse($request);
        } catch (Exception $e) {
            // Failed to perform the request.
            $resource->setErrorCode("request_error");
            $resource->setErrorDescription($e->getMessage());
        }

        if ($response && $response['goals']) {
            return $response['goals'];
        } else {
            if ($resource->getErrorCode() == null) {
                $resource->setErrorCode('unknown_error');
            }
            if ($resource->getErrorDescription() == null) {
                $resource->setErrorDescription('Some error happened when updating the activity goals.');
            }
            return [];
        }
    }

    /**
     * The Fullname stored in Fitbit platform can't have number or special characters.
     * This function replaces numbers 0-9 by the characters 'A-J', and special characters by spaces
     */
    static private function formatNameForFitbit($txt) {
        $txt = trim($txt);
        if (!$txt) {
            return $txt;
        }

        $txt = str_replace(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'], $txt);
        $final = '';
        foreach (str_split_multibyte($txt) as $char) {
            if (!preg_match('/\p{L}/', $char)) {
                $final = trim($final) . ' ';
            } else {
                $final = $final . $char;
            }
        }

        return trim($final);
    }
}
?>
