<?php

class APICase {
    private $id;
    private $userName;
    private $fullName;
    private $name;
    private $surname;
    private $nickname;
    private $bdate;
    private $gender;
    /* @var APIIdentifier[] $identifiers */
    private $identifiers = [];

    /**
     *
     * @param SimpleXMLElement $xmlNode
     * @return APICase
     */
    static public function parseXML($xmlNode) {
        if (!$xmlNode) {
            return null;
        }
        $case = new APICase();
        $case->id = (string) $xmlNode->ref;
        $case->userName = (string) $xmlNode->username;
        if ($xmlNode->data) {
            $case->fullName = (string) $xmlNode->data->full_name;
            $case->name = (string) $xmlNode->data->name;
            $case->surname = (string) $xmlNode->data->surname;
            $case->nickname = (string) $xmlNode->data->nickname;
            $case->bdate = (string) $xmlNode->data->bdate;
            $case->gender = (string) $xmlNode->data->gender;
        }
        $identifiers = [];
        if ($xmlNode->identifiers) {
            foreach ($xmlNode->identifiers->identifier as $idNode) {
                $identifiers[] = APIIdentifier::parseXML($idNode);
            }
            $case->identifiers = array_filter($identifiers);
        }
        return $case;
    }

    /*
     * **********************************
     * GETTERS
     * **********************************
     */
    /**
     *
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     *
     * @return string
     */
    public function getUsername() {
        return $this->userName;
    }

    /**
     *
     * @return string
     */
    public function getFullName() {
        return $this->fullName;
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
    public function getSurname() {
        return $this->surname;
    }

    /**
     *
     * @return string
     */
    public function getNickname() {
        return $this->nickname;
    }

    /**
     *
     * @return string
     */
    public function getBirthdate() {
        return $this->bdate;
    }

    /**
     *
     * @return string
     */
    public function getGender() {
        return $this->gender;
    }

    /**
     *
     * @return APIIdentifier[]
     */
    public function getIdentifiers() {
        return $this->identifiers;
    }
}