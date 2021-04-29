<?php

class LinkcareSoapAPI {
    /* @var APISession $api */
    private static $api;
    /* @var SoapClient $client */
    public $client = null;
    /* @var APISession $session */
    private $session = null;
    private $lastErrorCode;
    private $lastErrorMessage;

    /** @var SoapClient */
    private static $soapClient;

    /**
     *
     * @param SoapClient $client
     * @param APISession $session
     */
    private function __construct($client, $session) {
        $this->client = $client;
        $this->session = $session;
    }

    /**
     * Prepares the connection with WS-API
     *
     * @param string $endpoint
     * @throws APIException
     */
    public function setEndpoint($endpoint) {
        $wsdl = null; // $url . "/LINKCARE.wsdl.php";
        $client = null;

        // Obtenemos el TOKEN si ya existe o iniciamos sesión si no existe
        // Cuando de error hay que borrar el TOKEN
        $uri = parse_url($endpoint)['scheme'] . '://' . parse_url($endpoint)['host'];
        try {
            $client = new SoapClient($wsdl, ['location' => $endpoint, 'uri' => $uri, "connection_timeout" => 10]);
        } catch (SoapFault $fault) {
            $errorMsg = "ERROR: SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})";
            throw new APIException("SOAP_ERROR", $errorMsg);
        }

        self::$soapClient = $client;
    }

    /**
     *
     * @return LinkcareSoapAPI
     */
    static public function getInstance() {
        return self::$api;
    }

    /**
     * Error code returned by the last API call
     *
     * @return string
     */
    public function errorCode() {
        return $this->lastErrorCode;
    }

    /**
     * Error message returned by the last API call
     *
     * @return string
     */
    public function errorMessage() {
        return $this->lastErrorMessage;
    }

    /*
     * **********************************
     * API FUNCTIONS
     * **********************************
     */
    /**
     *
     * @param string $user
     * @param string $password
     * @param number|string $timezone
     * @throws APIException
     */
    static public function session_init($user, $password, $timezone = 0) {
        if (is_numeric($timezone)) {
            $timezone = $timezone <= 0 ? "-" . abs($timezone) : "+" . abs($timezone);
        }
        $client = self::$soapClient;
        if (!$client) {
            throw new APIException('ENDPOINT_MISSING', 'It is necessary to configure the endpoint before invoking WS-API');
        }

        $date = currentDate($timezone);
        $result = $client->session_init($user, $password, null, null, null, null, null, $date);
        if ($result["ErrorCode"]) {
            throw new APIException($result["ErrorCode"], $result["ErrorMsg"]);
        } else {
            $session = APISession::parseResponse($result);
        }

        self::$api = new LinkcareSoapAPI($client, $session);
        return self::$api;
    }

    /**
     * Join an existing Session
     *
     * @param string $token
     * @param string|number $timezone
     * @return LinkcareSoapAPI
     * @throws APIException
     */
    static public function session_join($token, $timezone = null) {
        $session = self::prepareAPISession(self::$soapClient, $token, $timezone);

        self::$api = new LinkcareSoapAPI(self::$soapClient, $session);
        return self::$api;
    }

    public function getSession() {
        return $this->session;
    }

    /**
     * Sets the active TEAM for the session
     *
     * @param string $teamId
     */
    public function session_set_team($teamId) {
        $params = ["team" => $teamId];
        $resp = $this->invoke('session_set_team', $params);
        if (!$resp->getErrorCode()) {
            $this->session->setTeamId($teamId);
        }
    }

    /**
     * Sets the active ROLE for the session
     *
     * @param string $roleId
     */
    public function session_role($roleId) {
        $params = ["role" => $roleId];
        $resp = $this->invoke('session_role', $params);
        if (!$resp->getErrorCode()) {
            $this->session->setRoleId($roleId);
        }
    }

    /**
     * Get information about a PROGRAM
     *
     * @param string $programId
     * @param string $subscriptionId
     * @return APIProgram
     */
    public function program_get($programId, $subscriptionId = null) {
        $program = null;
        $params = ["program_id" => $programId, "subscription" => $subscriptionId];
        $resp = $this->invoke('program_get', $params);
        if (!$resp->getErrorCode()) {
            if ($found = simplexml_load_string($resp->getResult())) {
                $program = APIProgram::parseXML($found);
            }
        }

        return $program;
    }

    /**
     * Get information about a TEAM
     *
     * @param string $teamId
     * @return APITeam
     */
    public function team_get($teamId) {
        $team = null;
        $params = ["team" => $teamId];
        $resp = $this->invoke('team_get', $params);
        if (!$resp->getErrorCode()) {
            if ($found = simplexml_load_string($resp->getResult())) {
                $team = APITeam::parseXML($found->data);
            }
        }

        return $team;
    }

    /**
     *
     * @param string $programId
     * @param string $teamId
     * @param string $subscriptionId
     * @return APISubscription
     */
    public function subscription_get($program, $team, $subscriptionId) {
        $subscription = null;
        $params = ["program" => $program, 'team' => $team, 'subscription' => $subscriptionId];
        $resp = $this->invoke("subscription_get", $params);
        if (!$resp->getErrorCode()) {
            if ($result = simplexml_load_string($resp->getResult())) {
                $subscription = APISubscription::parseXML($result);
            }
        }

        return $subscription;
    }

    /**
     *
     * @param string[] $filter Associative array with filter options. The key of each item is the name of the filter
     * @return APISubscription[]
     */
    public function subscription_list($filter = null) {
        $subscriptionList = [];
        $params = ["filter" => $filter ? json_encode($filter) : null];
        $resp = $this->invoke("subscription_list", $params);
        if (!$resp->getErrorCode()) {
            if ($searchResults = simplexml_load_string($resp->getResult())) {
                foreach ($searchResults->subscription as $subscriptionNode) {
                    $subscriptionList[] = APISubscription::parseXML($subscriptionNode);
                }
            }
        }
        return array_filter($subscriptionList);
    }

    /**
     *
     * @param int $case
     * @param int $subscription
     * @param string $date
     * @param int $team
     * @param boolean $allow_incomplete
     * @param StdClass $setupValues JSON object with a list of fields to store in the ADMISSION SETUP stage
     * @return APIAdmission
     */
    function admission_create($caseId, $subscriptionId, $date, $team = null, $allowIncomplete = false, $setupValues = null) {
        $admission = null;
        $strValues = is_object($setupValues) ? json_encode($setupValues) : null;
        $params = ["case" => $caseId, "subscription" => $subscriptionId, "date" => $date, "team" => $team,
                "allow_incomplete" => $allowIncomplete ? "1" : "", "setup_values" => $strValues];
        $resp = $this->invoke('admission_create', $params);
        if (!$resp->getErrorCode()) {
            if ($result = simplexml_load_string($resp->getResult())) {
                $admission = APIAdmission::parseXML($result);
            }
        }

        return $admission;
    }

    /**
     *
     * @param string $admissionId
     * @return APIAdmission
     */
    function admission_get($admissionId) {
        $admission = null;
        $params = ["admission" => $admissionId];
        $resp = $this->invoke('admission_get', $params);
        if (!$resp->getErrorCode()) {
            if ($result = simplexml_load_string($resp->getResult())) {
                $admission = APIAdmission::parseXML($result);
            }
        }

        return $admission;
    }

    /**
     *
     * @param int $admissionId
     */
    function admission_delete($admissionId) {
        $params = ["admission" => $admissionId];
        $this->invoke('admission_delete', $params);
    }

    /**
     *
     * @param string $admissionId
     * @param int $maxRes
     * @param int $offset
     * @param TaskFilter $filter
     * @param boolean $ascending
     * @return APITask[]
     */
    public function admission_get_task_list($admissionId, $maxRes = null, $offset = null, $filter = null, $ascending = true) {
        $taskList = [];
        $params = ["admission" => $admissionId, "max_res" => $maxRes, "offset" => $offset, "filter" => $filter ? $filter->toString() : null,
                "ascending" => $ascending ? "1" : 0];
        $resp = $this->invoke('admission_get_task_list', $params);
        if (!$resp->getErrorCode()) {
            if ($found = simplexml_load_string($resp->getResult())) {
                foreach ($found->task as $taskNode) {
                    $taskList[] = APITask::parseXML($taskNode);
                }
            }
        }

        return array_filter($taskList);
    }

    /**
     *
     * @param int $taskId
     * @return APITask
     */
    public function task_get($taskId) {
        $task = null;
        $params = ["task" => $taskId, "context" => ""];
        $resp = $this->invoke('task_get', $params);
        if (!$resp->getErrorCode()) {
            if ($found = simplexml_load_string($resp->getResult())) {
                $task = APITask::parseXML($found);
            }
        }

        return $task;
    }

    /**
     *
     * @param APITask $task
     */
    function task_set($task) {
        $xml = new XMLHelper('task');
        $task->toXML($xml, null);
        $params = ["task" => $xml->toString()];
        $this->invoke('task_set', $params);
    }

    /**
     * Returns the list of ACTIVITIES of a FORM
     *
     * @param int $taskId
     * @return APIForm[]
     */
    public function task_activity_list($taskId) {
        $activities = [];
        $params = ["task_id" => $taskId];
        $resp = $this->invoke('task_activity_list', $params);
        if (!$resp->getErrorCode()) {
            if ($results = simplexml_load_string($resp->getResult())) {
                foreach ($results->activity as $activityNode) {
                    if ($activityNode->type == 'form') {
                        $activities[] = APIForm::parseXML($activityNode);
                    } else {}
                }
            }
        }

        return array_filter($activities);
    }

    /**
     * Inserts a new TASK in an ADMISSION.
     * The return value is the ID of the new TASK
     *
     * @param string $caseContactXML
     * @param int $subscriptionId
     * @param boolean $allowIncomplete
     * @return int
     */
    function task_insert_by_task_code($admissionId, $taskCode, $date = null) {
        $taskId = null;
        $params = ["admission" => $admissionId, "task_code" => $taskCode, "date" => $date];
        $resp = $this->invoke('task_insert_by_task_code', $params);
        if (!$resp->getErrorCode()) {
            $taskId = $resp->getResult();
        }

        return $taskId;
    }

    /**
     * Creates a new CASE
     * The value returned is the ID of the new CASE
     *
     * @param APIContact $contact
     * @param int $subscriptionId
     * @param boolean $allowIncomplete
     * @return int
     */
    function case_insert($contact, $subscriptionId = null, $allowIncomplete = false) {
        $xml = new XMLHelper("case");
        $contact->toXML($xml, null);

        $params = ["case" => $xml->toString(), "subscription" => $subscriptionId, "allow_incomplete" => boolToText($allowIncomplete)];
        $resp = $this->invoke('case_insert', $params);
        if (!$resp->getErrorCode()) {
            if ($result = simplexml_load_string($resp->getResult())) {
                $caseId = NullableInt($result->case);
            }
        }

        return $caseId;
    }

    /**
     *
     * @param string $caseId
     * @param string $subscriptionId
     * @param string $admissionId
     * @return APIContact
     */
    public function case_get_contact($caseId, $subscriptionId = null, $admissionId = null) {
        $contact = null;
        $params = ["case" => $caseId, "subscription" => $subscriptionId, "admission" => $admissionId];
        $resp = $this->invoke('case_get_contact', $params);
        if (!$resp->getErrorCode()) {
            if ($found = simplexml_load_string($resp->getResult())) {
                $contact = APIContact::parseXML($found);
            }
        }

        return $contact;
    }

    /**
     *
     * @param string $caseId
     * @param APIContact $contact
     * @param string $admissionId
     */
    public function case_set_contact($caseId, $contact, $admissionId = null) {
        $xml = new XMLHelper('contact');

        $contact->setId($caseId);
        $contact->toXML($xml, $xml->rootNode);
        $params = ["case" => $xml->toString(), "admission" => $admissionId];
        $resp = $this->invoke('case_set_contact', $params);
    }

    /**
     *
     * @param int $caseId
     */
    function case_delete($caseId) {
        $params = ["case" => $caseId, "type" => "DELETE"];
        $this->invoke('case_delete', $params);
    }

    /**
     *
     * @param string $searchText
     * @return APICase[];
     */
    public function case_search($searchText = "") {
        $caseList = [];
        $params = ["search_str" => $searchText];
        $resp = $this->invoke('case_search', $params);
        if (!$resp->getErrorCode()) {
            if ($searchResults = simplexml_load_string($resp->getResult())) {
                foreach ($searchResults->case as $caseNode) {
                    $caseList[] = APICase::parseXML($caseNode);
                }
            }
        }

        return array_filter($caseList);
    }

    /**
     *
     * @param int $caseId
     * @param boolean $get
     * @param int $subscriptionId
     * @return APIAdmission[]
     */
    public function case_admission_list($caseId, $get = false, $subscriptionId = null, $search) {
        $admissionList = [];
        $params = ["case" => $caseId, "get" => $get ? "1" : "", "subscription" => $subscriptionId, "search_str" => $search];
        $resp = $this->invoke('case_admission_list', $params);
        if (!$resp->getErrorCode()) {
            if ($found = simplexml_load_string($resp->getResult())) {
                foreach ($found->admission as $admissionNode) {
                    $admissionList[] = APIAdmission::parseXML($admissionNode);
                }
            }
        }

        return array_filter($admissionList);
    }

    /**
     *
     * @param string $caseId
     * @param int $maxRes
     * @param int $offset
     * @param TaskFilter $filter
     * @param boolean $ascending
     * @return APITask[]
     */
    public function case_get_task_list($caseId, $maxRes = null, $offset = null, $filter = null, $ascending = true) {
        $taskList = [];
        $params = ["case" => $caseId, "max_res" => $maxRes, "offset" => $offset, "filter" => $filter ? $filter->toString() : null,
                "ascending" => $ascending ? "1" : 0];
        $resp = $this->invoke('case_get_task_list', $params);
        if (!$resp->getErrorCode()) {
            if ($found = simplexml_load_string($resp->getResult())) {
                foreach ($found->task as $taskNode) {
                    $taskList[] = APITask::parseXML($taskNode);
                }
            }
        }

        return array_filter($taskList);
    }

    /**
     *
     * @param int $formId
     * @param boolean $withQuestions
     * @param boolean $asClosed
     * @return APIForm
     */
    public function form_get_summary($formId, $withQuestions = false, $asClosed = false) {
        $form = null;
        $params = ["form" => $formId, "with_questions" => $withQuestions ? "1" : "", "as_closed" => $asClosed ? "1" : ""];
        $resp = $this->invoke('form_get_summary', $params);
        if ($xml = simplexml_load_string($resp->getResult())) {
            $form = APIForm::parseXML($xml);
        }

        return $form;
    }

    /**
     *
     * @param string $formId
     * @param string $questionId
     * @param string $value
     * @param string $optionId
     * @param string $eventId
     * @param boolean $closeForm
     */
    function form_set_answer($formId, $questionId, $value, $optionId = null, $eventId = null, $closeForm = false) {
        $params = ["form_id" => $formId, "question_id" => $questionId, "value" => $value, "option_id" => $optionId, "event_id" => $eventId,
                "close_form" => $closeForm ? "1" : ""];
        $this->invoke('form_set_answer', $params);
    }

    /**
     *
     * @param string $formId
     * @param APIQuestion[] $questions
     * @param boolean $closeForm
     */
    function form_set_all_answers($formId, $questions, $closeForm = false) {
        $xml = new XMLHelper('questions');

        foreach ($questions as $q) {
            $qNode = $xml->createChildNode(null, "question");
            $q->toXML($xml, $qNode);
        }

        $params = ["form" => $formId, "xml_answers" => $xml->toString(), "close_form" => $closeForm ? "1" : ""];
        $this->invoke('form_set_all_answers', $params);
    }

    /*
     * **********************************
     * PRIVATE METHODS
     * **********************************
     */

    /**
     * Initializes a session in WS-API
     *
     * @param SoapClient $client
     * @param string $token
     * @param int $timezone
     * @throws APIException
     * @return APISession
     */
    static private function prepareAPISession($client, $token = null, $timezone = 0) {
        $errorMsg = "";
        $session = null;

        try {
            $result = $client->session_get($token);
            if ($result["ErrorCode"]) {
                service_log("session_get error " . $result["ErrorMsg"]);
                throw new APIException($result["ErrorCode"], $result["ErrorMsg"]);
            } else {
                $session = APISession::parseResponse($result);
            }
        } catch (SoapFault $fault) {
            $errorMsg = "ERROR: SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})";
            service_log("Error registering session! $errorMsg");
            throw new APIException("SOAP_ERROR", $errorMsg);
        }
        return $session;
    }

    /**
     * Starts a SOAP call to the function indicated in $functionName
     *
     * @param string $functionName Name of the function to invoke
     * @param string[] $params Associative array. The key of each item is the name of the parameter
     * @throws APIException
     * @return APIResponse;
     */
    private function invoke($functionName, $params, $returnRaw = false) {
        $this->lastErrorCode = null;
        $this->lastErrorMessage = null;

        if (!$this->client) {
            throw new APIException('ENDPOINT_MISSING', 'It is necessary to configure the endpoint before invoking WS-API');
        }

        try {
            $args = [new SoapParam($this->session->getToken(), "session")];
            foreach ($params as $paramName => $paramValue) {
                $args[] = new SoapParam($paramValue, $paramName);
            }
            $result = $this->client->__soapCall($functionName, $args);
        } catch (SoapFault $fault) {
            service_log("Error invoking function $functionName! (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring}");
            $errorMsg = "ERROR: SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})";
            $result = ["result" => null, "ErrorCode" => "SOAP_FAULT", "ErrorMsg" => $errorMsg];
        }

        if ($returnRaw) {
            // Used for old API functions that do not return a standardized response
            return new APIResponse($result, null, null);
        } else {
            if ($result["ErrorCode"]) {
                throw new APIException($result["ErrorCode"], $result["ErrorMsg"], $result["result"]);
            }

            return new APIResponse($result["result"], $result["ErrorCode"], $result["ErrorMsg"]);
        }
    }
}