<?php

class APIAdmission {
    // Status constants
    const STATUS_INCOMPLETE = "INCOMPLETE";
    const STATUS_ACTIVE = "ACTIVE";
    const STATUS_REJECTED = "REJECTED";
    const STATUS_DISCHARGED = "DISCHARGED";
    const STATUS_PAUSED = "PAUSED";
    const STATUS_ENROLLED = "ENROLLED";

    // Private members
    private $id;
    private $caseId;
    private $case;
    private $enrolDate;
    private $admissionDate;
    private $dischargeRequestDate;
    private $dischargeDate;
    private $suspendedDate;
    private $rejectedDate;
    private $status;
    private $dateToDisplay;
    private $ageToDisplay;
    /** @var APISubscription */
    private $subscription;
    /** @var APIAdmissionPerformance */
    private $performance;
    private $isNewAdmission = false;
    /** @var LinkcareSoapAPI $api */
    private $api;

    public function __construct() {
        $this->api = LinkcareSoapAPI::getInstance();
    }

    /**
     *
     * @param SimpleXMLElement $xmlNode
     * @return APIAdmission
     */
    static public function parseXML($xmlNode) {
        if (!$xmlNode) {
            return null;
        }
        $admission = new APIAdmission();
        $admission->id = (string) $xmlNode->ref;
        $admission->status = NullableString($xmlNode->status); // admission_create returns the status at this level
        $admission->isNewAdmission = $xmlNode->type != "EXIST";
        if ($xmlNode->data) {
            if ($xmlNode->data->case) {
                $admission->caseId = NullableString($xmlNode->data->case->ref);
                $admission->case = APISubscription::parseXML($xmlNode->data->case);
            }
            $admission->enrolDate = NullableString($xmlNode->data->enrol_date);
            $admission->admissionDate = NullableString($xmlNode->data->admission_date);
            $admission->dischargeRequestDate = NullableString($xmlNode->data->discharge_request_date);
            $admission->dischargeDate = NullableString($xmlNode->data->discharge_date);
            $admission->suspendedDate = NullableString($xmlNode->data->suspended_date);
            $admission->rejectedDate = NullableString($xmlNode->data->rejected_date);
            if (!$admission->status) {
                $admission->status = NullableString($xmlNode->data->status);
            }
            $admission->dateToDisplay = NullableString($xmlNode->data->date_to_display);
            $admission->ageToDisplay = NullableInt($xmlNode->data->age_to_display);
            if ($xmlNode->data->subscription) {
                $admission->subscription = APISubscription::parseXML($xmlNode->data->subscription);
            }
            if ($xmlNode->performance) {
                $admission->performance = APIAdmissionPerformance::parseXML($xmlNode->performance);
            } else {
                $admission->performance = new APIAdmissionPerformance();
            }
        }
        return $admission;
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
    public function getId() {
        return $this->id;
    }

    /**
     * This function can be used on the Admission object returned by a call to the API function admission_create().
     * The return value can be:
     * - true: A new Admission has been created
     * - false: admission_create() did not create a new Admission because there was already an active Admission for the subscription (Program + Team)
     * indicated
     *
     * @return boolean
     */
    public function isNew() {
        return $this->isNewAdmission;
    }

    /**
     *
     * @return APICase
     */
    public function getCase() {
        return $this->case;
    }

    /**
     *
     * @return string
     */
    public function getCaseId() {
        return $this->caseId;
    }

    /**
     *
     * @return string
     */
    public function getEnrolDate() {
        return $this->enrolDate;
    }

    /**
     *
     * @return string
     */
    public function getAdmissionDate() {
        return $this->admissionDate;
    }

    /**
     *
     * @return string
     */
    public function getDischargeRequestDate() {
        return $this->dischargeRequestDate;
    }

    /**
     *
     * @return string
     */
    public function getDischargeDate() {
        return $this->dischargeDate;
    }

    /**
     *
     * @return string
     */
    public function getSuspendedDate() {
        return $this->suspendedDate;
    }

    /**
     *
     * @return string
     */
    public function getRejectedDate() {
        return $this->rejectedDate;
    }

    /**
     *
     * @return string
     */
    public function getStatus() {
        return $this->status;
    }

    /**
     *
     * @return string
     */
    public function getDateToDisplay() {
        return $this->dateToDisplay;
    }

    /**
     *
     * @return int
     */
    public function getAgeToDisplay() {
        return $this->ageToDisplay;
    }

    /**
     *
     * @return APISubscription
     */
    public function getSubscription() {
        return $this->subscription;
    }

    /**
     *
     * @return APIAdmissionPerformance
     */
    public function getPerformance() {
        return $this->performance;
    }

    /*
     * **********************************
     * METHODS
     * **********************************
     */
    /**
     *
     * @param int $maxRes
     * @param int $offset
     * @param TaskFilter $filter
     * @param boolean $ascending
     * @return APITask[]
     */
    public function getTaskList($maxRes = null, $offset = null, $filter = null, $ascending = true) {
        if (!$filter) {
            $filter = new TaskFilter();
        }
        $filter->setObjectType('TASKS');
        return $this->api->admission_get_task_list($this->id, $maxRes, $offset, $filter, $ascending);
    }

    /**
     *
     * @param int $maxRes
     * @param int $offset
     * @param TaskFilter $filter
     * @param boolean $ascending
     * @return APIEvent[]
     */
    public function getEventList($maxRes = null, $offset = null, $filter = null, $ascending = true) {
        if (!$filter) {
            $filter = new TaskFilter();
        }
        $filter->setObjectType('EVENTS');
        return $this->api->admission_get_task_list($this->id, $maxRes, $offset, $filter, $ascending);
    }
}