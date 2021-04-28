<?php

class LC2Action {
    const ACTION_REDIRECT_TO_TASK = "SHOW_TASK";
    const ACTION_REDIRECT_TO_CASE = "SHOW_CASE_TASK_LIST";
    const ACTION_REDIRECT_TO_FORM = "SHOW_FORM";
    const ACTION_SERVICE_REQUEST = "SERVICE_REQUEST";
    const ACTION_ERROR_MSG = "ERROR_MSG";
    const REQUEST_SUBSCRIPTION = "REQUEST_SUBSCRIPTION";
    private $action;
    private $caseId;
    private $taskId;
    private $formId;
    private $admissionId;
    private $errorMessage;
    private $programId;
    private $teamId;
    private $requestType;

    public function __construct($actionType = null) {
        $this->setActionType($actionType);
    }

    public function setActionType($actionType) {
        $this->action = $actionType;
    }

    /**
     *
     * @param int $taskId
     */
    public function setTaskId($taskId) {
        $this->taskId = $taskId;
    }

    /**
     *
     * @param int $formId
     */
    public function setFormId($formId) {
        $this->formId = $formId;
    }

    /**
     *
     * @param int $caseId
     */
    public function setCaseId($caseId) {
        $this->caseId = $caseId;
    }

    /**
     *
     * @param int $admissionId
     */
    public function setAdmissionId($admissionId) {
        $this->admissionId = $admissionId;
    }

    /**
     *
     * @param int $programId
     */
    public function setProgramId($programId) {
        $this->programId = $programId;
    }

    /**
     *
     * @param int $teamId
     */
    public function setTeamId($teamId) {
        $this->teamId = $teamId;
    }

    /**
     *
     * @param string $errorMsg
     */
    public function setErrorMessage($errorMsg) {
        $this->errorMessage = $errorMsg;
    }

    /**
     *
     * @param string $errorMsg
     */
    public function setRequestType($type) {
        // For actions of type SERVICE_REQUEST
        $this->requestType = $type;
    }

    /*
     * **********************************
     * METHODS
     * **********************************
     */

    /**
     * Generates a JSON string that can be passed to LC2 with instructions about the action
     */
    public function toString() {
        $action = new stdClass();
        $action->action_request = $this->action;
        if ($this->taskId) {
            $action->task = $this->taskId;
        }
        if ($this->formId) {
            $action->form = $this->formId;
        }
        if ($this->caseId) {
            $action->case = $this->caseId;
        }
        if ($this->admissionId) {
            $action->admission = $this->admissionId;
        }

        if ($this->programId) {
            $action->program = $this->programId;
        }

        if ($this->teamId) {
            $action->team = $this->teamId;
        }

        if ($this->errorMessage) {
            $action->error_message = $this->errorMessage;
        }

        if ($this->requestType) {
            $action->request = $this->requestType;
        }

        return json_encode($action);
    }
}