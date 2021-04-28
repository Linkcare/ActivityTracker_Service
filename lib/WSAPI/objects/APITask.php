<?php

class APITask {
    const STATUS_NOT_ASSIGNED = 11;
    const STATUS_NOT_DONE = 12;
    const STATUS_DONE = 13;

    // Private members
    private $id;
    private $taskCode;
    private $name;
    private $description;
    private $date;
    private $hour;
    private $duration;
    private $followReport;
    private $status;
    private $recursive;
    private $locked;
    /** @var string */
    private $admissionId;
    /** @var string */
    private $caseId;

    /** @var APITaskAssignment[] $assignments */
    private $assignments = [];
    /** @var APIForm[] $forms */
    private $forms = [];

    /**
     *
     * @param SimpleXMLElement $xmlNode
     * @return APITask
     */
    static public function parseXML($xmlNode) {
        if (!$xmlNode) {
            return null;
        }
        $task = new APITask();
        $task->id = NullableString($xmlNode->ref);
        if ($xmlNode->code) {
            $task->taskCode = NullableString($xmlNode->code);
        } elseif ($xmlNode->refs) {
            $task->taskCode = NullableString($xmlNode->refs->task_code);
        }
        $task->name = NullableString($xmlNode->name);
        $task->description = NullableString($xmlNode->description);

        $task->date = NullableString($xmlNode->date);
        $task->hour = NullableString($xmlNode->hour);
        $task->duration = NullableInt($xmlNode->duration);
        $task->followReport = NullableString($xmlNode->follow_report);
        $task->status = NullableString($xmlNode->status);
        $task->recursive = NullableString($xmlNode->recursive);
        $task->locked = textToBool($xmlNode->locked);

        if ($xmlNode->admission) {
            $task->admissionId = NullableString($xmlNode->admission->ref);
        }
        if ($xmlNode->case) {
            $task->caseId = NullableString($xmlNode->case->ref);
        }
        $assignments = [];
        if ($xmlNode->assignments) {
            foreach ($xmlNode->assignments->assignment as $assignNode) {
                $assignments[] = APITaskAssignment::parseXML($assignNode);
            }
            $task->assignments = array_filter($assignments);
        }

        $forms = [];
        if ($xmlNode->forms) {
            foreach ($xmlNode->forms->form as $formNode) {
                $forms[] = APIForm::parseXML($formNode);
            }
            $task->forms = array_filter($forms);
        }
        return $task;
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
     *
     * @return string
     */
    public function getTaskCode() {
        return $this->taskCode;
    }

    /**
     *
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     *
     * @return string
     */
    public function getDescription() {
        return $this->description;
    }

    /**
     *
     * @return string
     */
    public function getDate() {
        return $this->date;
    }

    /**
     *
     * @return string
     */
    public function getHour() {
        return $this->hour;
    }

    /**
     *
     * @return int
     */
    public function getDuration() {
        return $this->duration;
    }

    /**
     *
     * @return string
     */
    public function getFollowReport() {
        return $this->followReport;
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
    public function getRecursive() {
        return $this->recursive;
    }

    /**
     *
     * @return boolean
     */
    public function getLocked() {
        return $this->locked;
    }

    /**
     *
     * @return APIForm[]
     */
    public function getForms() {
        return $this->forms;
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
    public function getCaseId() {
        return $this->caseId;
    }

    /*
     * **********************************
     * SETTERS
     * **********************************
     */
    /**
     * Adds a new assignment to a TASK
     *
     * @param unknown $assignment
     */
    public function addAssignments($assignment) {
        if (!$assignment) {
            return;
        }
        $this->assignments[] = $assignment;
    }

    /*
     * **********************************
     * METHODS
     * **********************************
     */
    /**
     * Removes all the assignments of the TASK
     */
    public function clearAssignments() {
        $this->assignments = [];
    }

    /**
     *
     * @param XMLHelper $xml
     * @return SimpleXMLElement $parentNode
     */
    public function toXML($xml, $parentNode = null) {
        if ($parentNode === null) {
            $parentNode = $xml->rootNode;
        }
        $xml->createChildNode($parentNode, "ref", $this->getId());
        if ($this->getDate() !== null) {
            $xml->createChildNode($parentNode, "date", $this->getDate());
        }
        if ($this->getHour() !== null) {
            $xml->createChildNode($parentNode, "hour", $this->getHour());
        }
        if ($this->getDuration() !== null) {
            $xml->createChildNode($parentNode, "duration", $this->getDuration());
        }
        if ($this->getFollowReport() !== null) {
            $xml->createChildNode($parentNode, "follow_report", $this->getFollowReport());
        }
        if ($this->getStatus() !== null) {
            $xml->createChildNode($parentNode, "status", $this->getStatus());
        }
        if ($this->getRecursive() !== null) {
            $xml->createChildNode($parentNode, "recursive", $this->getRecursive());
        }
        if ($this->getLocked() !== null) {
            $xml->createChildNode($parentNode, "locked", boolToText($this->getLocked()));
        }

        $assignmentsNode = $xml->createChildNode($parentNode, "assignments");
        foreach ($this->assignments as $a) {
            $assignNode = $xml->createChildNode($assignmentsNode, "assignment");
            $a->toXML($xml, $assignNode);
        }
    }
}