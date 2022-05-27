<?php
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;

interface IActivityProvider {

    /**
     * Obtain the service provider.
     *
     * @return \League\OAuth2\Client\Provider\AbstractProvider
     */
    public function getProvider();

    /**
     * Obtain the OAuth Authentication URL for the corresponding provider service
     *
     * @param string $state
     * @return string
     */
    public function getAuthorizationUrl($state);

    /**
     * Request the Access token with the corresponding type of grant
     *
     * @param string $grant
     * @param array $options
     * @return AccessTokenInterface
     */
    public function getAccessToken($grant, array $options = []);

    /**
     * Obtain the Activity data from the activity provider API using two dates.
     * The date parameters are itself included in the response, they have to be in 'yyyy-MM-dd' format.<br>
     * The return an associative array formatted as follows:
     * <ul>
     * <li>'[["dateTime" => "yyyy-MM-dd","value" => "NUMBER"],</li>
     * <li>'["dateTime" => "yyyy-MM-dd","value" => "NUMBER"],</li>
     * <li>'["dateTime" => "yyyy-MM-dd","value" => "NUMBER"]]</li>
     * </ul>
     *
     * Possible error codes returned in OauthResource:
     * <ul>
     * <li>refresh_token_error: there was an error while refreshing the expired token.</li>
     * <li>request_error: there was an error while obtaining the activity data.</li>
     * <li>unknown_error: the obtained data has changed its format or there was an uncaught error.</li>
     * </ul>
     *
     * @param OauthResource $resource
     * @param string $startDate date as 'yyyy-MM-dd'
     * @param string $endDate date as 'yyyy-MM-dd'
     * @param string $locale
     * @return array
     *
     */
    public function getActivityData(OauthResource $resource, $startDate, $endDate, $locale = 'es_ES');

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
     * Possible error codes returned in OauthResource:
     * <ul>
     * <li>refresh_token_error: there was an error while refreshing the expired token.</li>
     * <li>request_error: there was an error while obtaining the activity data.</li>
     * <li>permissions_error: the application used to request the data doesn't have enough permissions to request intraday activity data.</li>
     * <li>unknown_error: the obtained data has changed its format or there was an uncaught error.</li>
     * </ul>
     * Access to the FITBIT Intraday Time Series: https://dev.fitbit.com/build/reference/web-api/intraday-requests/
     *
     * @param OauthResource $resource
     * @param string $date
     * @param string $breakdownPeriod
     * @param string $locale
     * @return array
     */
    public function getDetailedActivity(OauthResource $resource, $date, $breakdownPeriod, $locale = 'es_ES');

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
     * Possible error codes returned in OauthResource:
     * <ul>
     * <li>refresh_token_error: there was an error while refreshing the expired token.</li>
     * <li>request_error: there was an error while obtaining the sleep data.</li>
     * <li>unknown_error: the obtained data has changed its format or there was an uncaught error.</li>
     * </ul>
     *
     * @param OauthResource $resource
     * @param string $startDate
     * @param string $endDate
     * @param string $locale
     * @return array
     */
    public function getSleepData(OauthResource $resource, $startDate, $endDate, $locale = 'es_ES');

    /**
     * Obtain a list of devices for the user with its data, which includes 'lastSyncTime'.
     * Returned data is breakdown for each device. It will have the following format:
     * <ul>
     * <li> <p>'[{ "battery": "High",</p>
     * <p> "batteryLevel": 25,</p>
     * <p> "deviceVersion": "Charge HR",</p>
     * <p>"id": "27072629", </p>
     * <p>"lastSyncTime": "2015-07-27 17:01:39",</p>
     * <p>"type": "TRACKER" },</p>
     * </li>
     * <li> <p>'{ "battery": "Empty",</p>
     * <p>"deviceVersion": "MobileTrack",</p>
     * <p>"id": "29559794",</p>
     * <p>"lastSyncTime": "2015-07-19 16:57:59",</p>
     * <p>"type": "TRACKER" }, ...]</p>
     * </li>
     * </ul>
     *
     * Possible error codes returned in OauthResource:
     * <ul>
     * <li>refresh_token_error: there was an error while refreshing the expired token.</li>
     * <li>request_error: there was an error while obtaining the activity data.</li>
     * <li>unknown_error: the obtained data has changed its format or there was an uncaught error.</li>
     * </ul>
     *
     * @param OauthResource $resource
     * @param string $locale
     * @return array
     */
    public function getDeviceData(OauthResource $resource, $locale = 'es_ES');

    /**
     * Update the profile of a user, the following fields are supported:
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
     * <p> Fitbit API Reference: https://dev.fitbit.com/build/reference/web-api/user/#update-profile </p>
     *
     * <p> Possible error codes returned in OauthResource: </p>
     * <ul>
     * <li>refresh_token_error: there was an error while refreshing the expired token.</li>
     * <li>request_error: there was an error while obtaining the activity data, it could be related to a lack of WRITE permissions.</li>
     * <li>unknown_error: the obtained data has changed its format or there was an uncaught error.</li>
     * </ul>
     *
     * @param OauthResource $resource
     * @param array $params
     * @param string $locale
     * @return array
     */
    public function updateProfile(OauthResource $resource, $params = [], $locale = 'es_ES');

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
     * <p> Fitbit API Reference: https://dev.fitbit.com/build/reference/web-api/activity/#activity-goals </p>
     *
     * <p> Possible error codes returned in OauthResource: </p>
     * <ul>
     * <li>refresh_token_error: there was an error while refreshing the expired token.</li>
     * <li>request_error: there was an error while obtaining the activity data, it could be related to a lack of WRITE permissions.</li>
     * <li>unknown_error: the obtained data has changed its format or there was an uncaught error.</li>
     * </ul>
     *
     * @param OauthResource $resource
     * @param array $params
     * @param string $period
     * @param string $locale
     * @return array
     */
    public function updateActivityGoals(OauthResource $resource, $params = [], $period = 'weekly', $locale = 'es_ES');
}

?>