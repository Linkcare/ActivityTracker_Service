<?php

class APISession {
    private $token;
    private $userId;
    private $language;
    private $roleId;
    private $teamId;
    private $name;
    private $professionalId;
    private $caseId;
    private $associateId;
    private $timezone;

    /**
     *
     * @param string[] $sessionInfo
     * @return APICase
     */
    static public function parseResponse($sessionInfo) {
        if (!$sessionInfo) {
            return null;
        }
        $session = new APISession();
        if (array_key_exists("result", $sessionInfo)) {
            // session_get response
            if ($xml = simplexml_load_string($sessionInfo["result"])) {
                $session->token = (string) $xml->token;
                $session->userId = (string) $xml->user;
                $session->language = (string) $xml->language;
                $session->roleId = (string) $xml->role;
                $session->teamId = (string) $xml->team;
                $session->name = (string) $xml->name;
                $session->professionalId = (string) $xml->professional;
                $session->caseId = (string) $xml->case;
                $session->associateId = (string) $xml->associate;
            }
        } else {
            // session_init response
            $session->token = $sessionInfo["token"];
            $session->userId = $sessionInfo["user"];
            $session->language = $sessionInfo["language"];
            $session->roleId = $sessionInfo["role"];
            $session->teamId = $sessionInfo["team"];
            $session->name = $sessionInfo["name"];
            $session->professionalId = $sessionInfo["professional"];
            $session->caseId = $sessionInfo["case"];
            $session->associateId = $sessionInfo["associate"];
        }
        return $session;
    }

    /*
     * **********************************
     * GETTERS
     * **********************************
     */
    public function getToken() {
        return $this->token;
    }

    public function getUserId() {
        return $this->userId;
    }

    public function getLanguage() {
        return $this->language;
    }

    public function getRoleId() {
        return $this->roleId;
    }

    public function getTeamId() {
        return $this->teamId;
    }

    public function getName() {
        return $this->name;
    }

    public function getProfessionalId() {
        return $this->professionalId;
    }

    public function getCaseId() {
        return $this->caseId;
    }

    /**
     * Changes the active TEAM.
     * This function should never be used by a client.
     * This is a public function only for LinkcareSoapAPI functions after invoking session_set_team()
     *
     * @param string $teamId
     */
    public function setTeamId($teamId) {
        $this->teamId = $teamId;
    }

    /**
     * Changes the active ROLE.
     * This function should never be used by a client.
     * This is a public function only for LinkcareSoapAPI functions after invoking session_role()
     *
     * @param string $roleId
     */
    public function setRoleId($roleId) {
        $this->roleId = $roleId;
    }
}