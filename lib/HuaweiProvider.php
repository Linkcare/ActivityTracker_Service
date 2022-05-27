<?php

/**
 * ******************************** HUAWEI RELATED FUNCTIONS *********************************
 */
use HuaweiOAuth2Client\Huawei;
use League\OAuth2\Client\Token\AccessToken;
use function GuzzleHttp\json_decode;

class HuaweiProvider implements IActivityProvider {

    /**
     *
     * {@inheritdoc}
     * @see IActivityProvider::getProvider()
     */
    public function getProvider() {
        return Huawei::getProvider();
    }

    /**
     *
     * {@inheritdoc}
     * @see IActivityProvider::getAuthorizationUrl()
     */
    public function getAuthorizationUrl($state) {
        $addParams = '&access_type=offline';
        $authorizationUrlParams = ['state' => $state];
        $authorizationUrl = Huawei::getProvider()->getAuthorizationUrl($authorizationUrlParams);

        return $authorizationUrl . $addParams;
    }

    /**
     *
     * {@inheritdoc}
     * @see IActivityProvider::getAccessToken()
     */
    public function getAccessToken($grant, array $options = []) {
        Huawei::getProvider()->getAccessToken($grant, $options);
    }

    /**
     * Specific error codes:
     * <ul>
     * <li>dates_interval_error: the interval between the start and end times cannot exceed 30 days.</li>
     * </ul>
     *
     * {@inheritdoc}
     * @see IActivityProvider::getActivityData()
     */
    public function getActivityData(OauthResource $resource, $startDate, $endDate, $locale = 'es_ES') {
        // The dates must be a timestamp of a 13-digit integer, in milliseconds.
        if (isset($startDate)) {
            $startDate = strtotime($startDate) * 1000;
        } else {
            return [];
        }
        // If endDate isn't defined, the maximum possible interval (start date + 30 days and 23:59 hours) will be assigned
        // If it's defined, sum 23:59 hours and transform it to milliseconds
        $endDate = isset($endDate) ? (strtotime($endDate) + 84983) * 1000 : $startDate + 2591940000;
        // Check in case the difference between given dates was bigger than 30 days
        if (($endDate - $startDate) > 2592000000) {
            $resource->setErrorCode("dates_interval_error");
            $resource->setErrorDescription("The interval between the start and end times cannot exceed 30 days.");
            return [];
        }

        // The 'dataTypeName' value is the scope for atomic 'steps'.
        $activityData = $this->getSampleSet($resource, $startDate, $endDate, 86400000, "com.huawei.continuous.steps.delta");

        $result = [];
        // Obtain the data by day
        for ($i = 0; $i < sizeof($activityData['group']); $i++) {
            $data = $activityData['group'][$i];
            if (sizeof($data['sampleSet']) > 0) {
                $date = date('Y-m-d', $data['startTime'] / 1000);

                $steps = 0;
                for ($h = 0; $h < sizeof($data['sampleSet']); $h++) {
                    for ($v = 0; $v < sizeof($data['sampleSet'][$h]['samplePoints']); $v++) {
                        $samplePoint = $data['sampleSet'][$h]['samplePoints'][$v];
                        for ($k = 0; $k < sizeof($samplePoint['value']); $k++) {
                            if ($samplePoint['value'][$k]['fieldName'] == 'steps') {
                                $steps += $samplePoint['value'][$k]['integerValue'];
                            }
                        }
                    }
                }
                if ($steps > 0) {
                    $row = [];
                    $row['dateTime'] = $date;
                    $row['value'] = $steps;
                    array_push($result, $row);
                }
            }
        }
        return $result;
    }

    /**
     *
     * {@inheritdoc}
     * @see IActivityProvider::getDetailedActivity()
     */
    public function getDetailedActivity(OauthResource $resource, $date, $breakdownPeriod, $locale = 'es_ES') {
        // The dates must be a timestamp of a 13-digit integer, in milliseconds.
        if (isset($date)) {
            $startDate = strtotime($date) * 1000;
            // The end date will be the given date plus 24 hours in milliseconds
            $endDate = $startDate + 86400000;
        } else {
            return [];
        }

        // Translate the breakdown period string to the corresponding milliseconds
        switch ($breakdownPeriod) {
            case "15min" :
                $groupByTime = 60 * 15 * 1000;
                break;
            case "5min" :
                $groupByTime = 60 * 5 * 1000;
                break;
            case "1min" :
                $groupByTime = 60 * 1 * 1000;
                break;
            default :
                // As the documentation says, 1min is the default value
                $groupByTime = 60 * 1000;
        }

        // The 'dataTypeName' value is the scope for atomic 'steps'.
        $activityData = $this->getSampleSet($resource, $startDate, $endDate, $groupByTime, "com.huawei.continuous.steps.delta");

        $result = [];
        // Obtain the data by breakdown period
        for ($i = 0; $i < sizeof($activityData['group']); $i++) {
            $data = $activityData['group'][$i];
            if (sizeof($data['sampleSet']) > 0) {
                $date = date('H:i:s', $data['startTime'] / 1000);

                $steps = 0;
                for ($h = 0; $h < sizeof($data['sampleSet']); $h++) {
                    for ($v = 0; $v < sizeof($data['sampleSet'][$h]['samplePoints']); $v++) {
                        $samplePoint = $data['sampleSet'][$h]['samplePoints'][$v];
                        for ($k = 0; $k < sizeof($samplePoint['value']); $k++) {
                            if ($samplePoint['value'][$k]['fieldName'] == 'steps') {
                                $steps += $samplePoint['value'][$k]['integerValue'];
                            }
                        }
                    }
                }
                if ($steps > 0) {
                    $row = [];
                    $row['time'] = $date;
                    $row['value'] = $steps;
                    array_push($result, $row);
                }
            }
        }
        return $result;
    }

    /**
     *
     * {@inheritdoc}
     * @see IActivityProvider::getSleepData()
     */
    public function getSleepData(OauthResource $resource, $startDate, $endDate, $locale = 'es_ES') {
        // The dates must be a timestamp of a 13-digit integer, in milliseconds.
        if (!$endDate) {
            return [];
        } else {
            $endDate = strtotime($endDate) * 1000;
        }

        if (!$startDate) {
            // If start date isn't defined, it means we want just a day
            // then it'll be the end date minus 24 hours
            $startDate = $endDate - 86400000;
        } else {
            $startDate = strtotime($startDate) * 1000;
        }

        // The dataTypeName value is the scope for atomic sleep.
        $activityData = $this->getSampleSet($resource, $startDate, $endDate, 86400000, "com.huawei.continuous.sleep.fragment");

        $result = [];
        // Obtain the data by day
        for ($i = 0; $i < sizeof($activityData['group']); $i++) {
            $data = $activityData['group'][$i];
            if (sizeof($data['sampleSet']) > 0) {

                for ($h = 0; $h < sizeof($data['sampleSet']); $h++) {
                    for ($v = 0; $v < sizeof($data['sampleSet'][$h]['samplePoints']); $v++) {
                        $samplePoint = $data['sampleSet'][$h]['samplePoints'][$v];

                        $startTime = date('Y-m-d H:i:s', $samplePoint['startTime'] / 1000);
                        $endTime = date('Y-m-d H:i:s', $samplePoint['endTime'] / 1000);
                        for ($k = 0; $k < sizeof($samplePoint['value']); $k++) {
                            $value = $samplePoint['value'][$k]['integerValue'];
                            if ($value > 0) {
                                $row = [];
                                $row['start_time'] = $startTime;
                                $row['end_time'] = $endTime;
                                $row['duration'] = $value;
                                $row['level'] = $samplePoint['value'][$k]['fieldName'];
                                array_push($result, $row);
                            }
                        }
                    }
                }
            }
        }
        return $result;
    }

    /**
     * For Huawei, getActivityData, getDetailedActivity and getSleepData will use the same call, which will be called from a private call.
     *
     * @param OauthResource $resource
     * @param int $startDate
     * @param int $endDate
     * @return array
     */
    private function getSampleSet(OauthResource $resource, $startDate, $endDate, $groupByTime, $dataTypeName) {
        $accessToken = $resource->getAccessToken();
        if (!$accessToken) {
            return [];
        }

        try {
            $body = '{
                      "polymerizeWith": [
                        {
                          "dataTypeName": "' . $dataTypeName . '"
                        }
                      ],
                      "startTime": ' . $startDate . ',
                      "endTime": ' . $endDate . ',
                      "groupByTime": {
                         "duration": ' . $groupByTime . '
                       }
                    }';
            $baseUrl = Huawei::HEALTH_HUAWEI_API_URL . '/healthkit/v1/sampleSet:polymerize';
            $request = Huawei::getProvider()->getAuthenticatedRequest(Huawei::METHOD_POST, $baseUrl, null,
                    [
                            'headers' => [Huawei::HEADER_CONTENT => 'application/json;charset=utf-8',
                                    Huawei::HEADER_AUTH => 'Bearer ' . $accessToken->getToken()], 'body' => $body]);

            $response = Huawei::getProvider()->getParsedResponse($request);
        } catch (Exception $e) {
            // Failed to perform the request.
            $resource->setErrorCode("request_error");
            $resource->setErrorDescription($e->getMessage());
        }

        if (!$response || !$response['group']) {
            if ($resource->getErrorCode() == null) {
                $resource->setErrorCode('unknown_error');
            }
            if ($resource->getErrorDescription() == null) {
                $resource->setErrorDescription('Some error happened when requesting information.');
            }
            return [];
        }

        return $response;
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
            $baseUrl = Huawei::HEALTH_HUAWEI_API_URL . '/healthkit/v1/dataCollectors';
            $request = Huawei::getProvider()->getAuthenticatedRequest(Huawei::METHOD_GET, $baseUrl, null,
                    ['headers' => [Huawei::HEADER_CONTENT => 'application/json', Huawei::HEADER_AUTH => 'Bearer ' . $accessToken->getToken()]]);

            $response = Huawei::getProvider()->getParsedResponse($request);
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
                $resource->setErrorDescription('Some error happened when requesting device data.');
            }
            return [];
        }

        $deviceData = [];
        for ($i = 0; $i < sizeof($response['dataCollector']); $i++) {
            $row = [];
            $dataCollector = $response['dataCollector'][$i];
            if (isset($dataCollector['lastUpdateTime'])) {
                $row['lastSyncTime'] = date('Y-m-d H:i:s', $dataCollector['lastUpdateTime'] / 1000);
            } else {
                $row['lastSyncTime'] = '0000-00-00 00:00:00';
            }
            if (isset($dataCollector['deviceInfo'])) {
                if (isset($dataCollector['deviceInfo']['devType'])) {
                    $row['type'] = $dataCollector['deviceInfo']['devType'];
                }
                if (isset($dataCollector['deviceInfo']['version'])) {
                    $row['deviceVersion'] = $dataCollector['deviceInfo']['version'];
                }
            }
            $row['id'] = $dataCollector['collectorId'];
            // There's no way to obtain the battery and batteryLevel values at the moment
            $row['battery'] = '';
            $row['batteryLevel'] = '';

            array_push($deviceData, $row);
        }
        return $deviceData;
    }

    /**
     *
     * {@inheritdoc}
     * @see IActivityProvider::updateProfile()
     */
    public function updateProfile(OauthResource $resource, $params = [], $locale = 'es_ES') {
        // Method currently not available for HUAWEI
        return [];
    }

    /**
     *
     * {@inheritdoc}
     * @see IActivityProvider::updateActivityGoals()
     */
    public function updateActivityGoals(OauthResource $resource, $params = [], $period = 'weekly', $locale = 'es_ES') {
        // Method currently not available for HUAWEI
        return [];
    }
}
?>
