<?php

/**
 * ******************************** FITBIT RELATED FUNCTIONS *********************************
 */
use FitbitOAuth2Client\Fitbit;
use League\OAuth2\Client\Token\AccessToken;
use function GuzzleHttp\json_decode;

/**
 * Obtain the Activity data from the FITBIT API using two dates.
 * The date parameters are itself included in the response, they have to be in 'yyyy-MM-dd' format.<br>
 * The return an associative array formatted as follows:
 * <ul>
 * <li>'[["dateTime" => "yyyy-MM-dd","value" => "NUMBER"],</li>
 * <li>'["dateTime" => "yyyy-MM-dd","value" => "NUMBER"],</li>
 * <li>'["dateTime" => "yyyy-MM-dd","value" => "NUMBER"]]</li>
 * </ul>
 *
 * Possible error codes returned in FitbitResource:
 * <ul>
 * <li>refresh_token_error: there was an error while refreshing the expired token.</li>
 * <li>request_error: there was an error while obtaining the activity data.</li>
 * <li>unknown_error: the obtained data has changed its format or there was an uncaught error.</li>
 * </ul>
 *
 * @param FitbitResource $resource
 * @param string $startDate date as 'yyyy-MM-dd'
 * @param string $endDate date as 'yyyy-MM-dd'
 * @param string $locale
 * @return array
 *
 */
function getActivityData(FitbitResource $resource, $startDate, $endDate, $locale = 'es_ES') {
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
 * Obtain intraday activity data for a specific day.
 * Returned data is breakdown in the specified period series. It will have the following format:
 * <ul>
 * <li>'[{ "time": "00:00:00", "value": 0 },</li>
 * <li>'{ "time": "00:01:00", "value": 287 },</li>
 * <li>'{ "time": "00:02:00", "value": 287 }]</li>
 * </ul>
 *
 * Available breakdown periods:
 * <ul>
 * <li>15min</li>
 * <li>5min</li>
 * <li>1min (default value)</li>
 * </ul>
 *
 * Possible error codes returned in FitbitResource:
 * <ul>
 * <li>refresh_token_error: there was an error while refreshing the expired token.</li>
 * <li>request_error: there was an error while obtaining the activity data.</li>
 * <li>permissions_error: the application used to request the data doesn't have enough permissions to request intraday activity data.</li>
 * <li>unknown_error: the obtained data has changed its format or there was an uncaught error.</li>
 * </ul>
 * Access to the Intraday Time Series: https://dev.fitbit.com/build/reference/web-api/intraday-requests/
 *
 * @param FitbitResource $resource
 * @param string $date
 * @param string $breakdownPeriod
 * @param string $locale
 * @return array
 */
function getDetailedActivity(FitbitResource $resource, $date, $breakdownPeriod, $locale = 'es_ES') {
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
 * Obtain sleep data for a range of days or a specific day.
 * The returned data will have the following format:
 * <ul>
 * <li> [{
 * <ul>
 * <li>"summary": {"start_time": "2022-03-15 00:05:00", "end_time": "2022-03-16 06:00:00", "duration": 21300} },</li>
 * <li>"detail" : [
 * <ul>
 * <li>{"start_time": "2022-03-15 00:05:00","end_time": "2022-03-16 01:00:00","duration": 3300,"level": "light"},</li>
 * <li>{"start_time": "2022-03-15 01:00:00","end_time": "2022-03-16 05:30:00","duration": 16200,"level": "deep"},</li>
 * <li>{...},</li>
 * <li>{...}]</li>
 * </ul>
 * </li>
 * </ul>
 * },</li>
 * <li>{...},</li>
 * <li>{...}]</li>
 * </ul>
 *
 * If no startDate is given, the endDate will be the asked day, using the call for only one Date instead of a Date Range.
 *
 * Possible error codes returned in FitbitResource:
 * <ul>
 * <li>refresh_token_error: there was an error while refreshing the expired token.</li>
 * <li>request_error: there was an error while obtaining the sleep data.</li>
 * <li>unknown_error: the obtained data has changed its format or there was an uncaught error.</li>
 * </ul>
 *
 * @param FitbitResource $resource
 * @param string $startDate
 * @param string $endDate
 * @param string $locale
 * @return array
 */
function getSleepData(FitbitResource $resource, $startDate, $endDate, $locale = 'es_ES') {
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
            $resource->setErrorDescription('Some error happened when requesting activity information.');
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
 * Obtain a list of devices for the user with its data, which includes 'lastSyncTime'.
 * Returned data is breakdown for each device. It will have the following format:
 * <ul>
 * <li> <p>'[{ "battery": "High",</p>
 * <p> "batteryLevel": 25,</p>
 * <p> "deviceVersion": "Charge HR",</p>
 * <p>"id": "27072629", </p>
 * <p>"lastSyncTime": "2015-07-27T17:01:39.313",</p>
 * <p>"type": "TRACKER" },</p>
 * </li>
 * <li> <p>'{ "battery": "Empty",</p>
 * <p>"deviceVersion": "MobileTrack",</p>
 * <p>"id": "29559794",</p>
 * <p>"lastSyncTime": "2015-07-19T16:57:59.000",</p>
 * <p>"type": "TRACKER" }, ...]</p>
 * </li>
 * </ul>
 *
 * Possible error codes returned in FitbitResource:
 * <ul>
 * <li>refresh_token_error: there was an error while refreshing the expired token.</li>
 * <li>request_error: there was an error while obtaining the activity data.</li>
 * <li>unknown_error: the obtained data has changed its format or there was an uncaught error.</li>
 * </ul>
 *
 * @param FitbitResource $resource
 * @param string $locale
 * @return array
 */
function getDeviceData(FitbitResource $resource, $locale = 'es_ES') {
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
            $resource->setErrorDescription('Some error happened when requesting activity information.');
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
 * Update the FitBit profile of a user, the following fields are supported:
 * <ul>
 * <li> fullname: Full name </li>
 * <li> gender: More accurately, sex; (MALE/FEMALE/NA) </li>
 * <li> birthday: Date of birth; in the format yyyy-MM-dd </li>
 * <li> height: Height; in the format X.XX, in the unit system that corresponds to the Accept-Language header provided ($locale parameter) </li>
 * <li> strideLengthWalking: Walking stride length; in the format X.XX, in the unit system that corresponds to the Accept-Language header provided
 * ($locale parameter) </li>
 * <li> strideLengthRunning: Running stride length; in the format X.XX, in the unit system that corresponds to the Accept-Language header provided
 * ($locale parameter) </li>
 * </ul>
 *
 * <p> They have to be passed as $params in an array with each field as key having its correctly formatted value. </p>
 *
 * <p> API Reference: https://dev.fitbit.com/build/reference/web-api/user/#update-profile </p>
 *
 * <p> Possible error codes returned in FitbitResource: </p>
 * <ul>
 * <li>refresh_token_error: there was an error while refreshing the expired token.</li>
 * <li>request_error: there was an error while obtaining the activity data, it could be related to a lack of WRITE permissions.</li>
 * <li>unknown_error: the obtained data has changed its format or there was an uncaught error.</li>
 * </ul>
 *
 * @param FitbitResource $resource
 * @param array $params
 * @param string $locale
 * @return array
 */
function fitbitUpdateProfile(FitbitResource $resource, $params = [], $locale = 'es_ES') {
    if (!empty($params)) {
        $bodyParams = [];
        if (!isNullOrEmpty($params["fullname"])) {
            $bodyParams[] = "fullname=" . formatNameForFitbit($params["fullname"]);
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
 * Update the the user's daily activity goals, the following fields are supported:
 * <ul>
 * <li> distance: Goal value; in the format X.XX or integer </li>
 * <li> steps: Goal value; integer </li>
 * </ul>
 *
 * <p> The Activity Goals to update can either be 'weekly' or 'daily', setting that can be specified at $period </p>
 *
 * <p> They have to be passed as $params in an array with each field as key having its correctly formatted value. </p>
 *
 * <p> API Reference: https://dev.fitbit.com/build/reference/web-api/activity/#activity-goals </p>
 *
 * <p> Possible error codes returned in FitbitResource: </p>
 * <ul>
 * <li>refresh_token_error: there was an error while refreshing the expired token.</li>
 * <li>request_error: there was an error while obtaining the activity data, it could be related to a lack of WRITE permissions.</li>
 * <li>unknown_error: the obtained data has changed its format or there was an uncaught error.</li>
 * </ul>
 *
 * @param FitbitResource $resource
 * @param array $params
 * @param string $period
 * @param string $locale
 * @return array
 */
function fitbitUpdateActivityGoals(FitbitResource $resource, $params = [], $period = 'weekly', $locale = 'es_ES') {
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
?>
