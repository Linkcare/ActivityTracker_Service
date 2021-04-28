<?php

class APIContact {
    private $id;
    private $userName;
    private $editable;
    private $fullName;
    private $name;
    private $middleName;
    private $familyName;
    private $familyName2;
    private $bdate;
    private $age;
    private $gender;
    /* @var APIIdentifier[] $identifiers */
    private $identifiers = [];
    /* @var APIContactAddress[] $addresses */
    private $addresses = [];
    /* @var APIContactChannel[] $phones */
    private $phones = [];
    /* @var APIContactChannel[] $emails */
    private $emails = [];
    /* @var APIContactChannel[] $devices */
    private $devices = [];

    /**
     *
     * @param SimpleXMLElement $xmlNode
     * @return APIContact
     */
    static public function parseXML($xmlNode) {
        if (!$xmlNode) {
            return null;
        }
        $contact = new APIContact();
        $contact->id = (string) $xmlNode->ref;
        $contact->userName = NullableString($xmlNode->username);
        $contact->editable = textToBool((string) $xmlNode->editable);
        if ($xmlNode->data) {
            $contact->bdate = NullableString($xmlNode->data->bdate);
            $contact->age = NullableString($xmlNode->data->age);
            $contact->gender = NullableString($xmlNode->data->gender);
        }
        if ($xmlNode->name) {
            $contact->fullName = NullableString($xmlNode->name->full_name);
            $contact->name = NullableString($xmlNode->name->given_name);
            $contact->middleName = NullableString($xmlNode->name->middleName);
            $contact->familyName = NullableString($xmlNode->name->family_name);
            $contact->familyName2 = NullableString($xmlNode->name->family_name2);
        }

        $identifiers = [];
        if ($xmlNode->identifiers) {
            foreach ($xmlNode->identifiers->identifier as $idNode) {
                $identifiers[] = APIIdentifier::parseXML($idNode);
            }
            $contact->identifiers = array_filter($identifiers);
        }

        $addresses = [];
        if ($xmlNode->addresses) {
            foreach ($xmlNode->addresses->address as $addressNode) {
                $addresses[] = APIContactAddress::parseXML($addressNode);
            }
            $contact->addresses = array_filter($addresses);
        }

        if ($xmlNode->channels) {
            $phones = [];
            if ($xmlNode->channels->phones) {
                foreach ($xmlNode->channels->phones->phone as $chNode) {
                    $phones[] = APIContactChannel::parseXML($chNode);
                }
            }
            $emails = [];
            if ($xmlNode->channels->emails) {
                foreach ($xmlNode->channels->emails->email as $chNode) {
                    $emails[] = APIContactChannel::parseXML($chNode);
                }
            }
            $devices = [];
            if ($xmlNode->channels->devices) {
                foreach ($xmlNode->channels->devices->device as $chNode) {
                    $devices[] = APIContactChannel::parseXML($chNode);
                }
            }
            $contact->phones = array_filter($phones);
            $contact->emails = array_filter($emails);
            $contact->devices = array_filter($devices);
        }

        return $contact;
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
     * @return boolean
     */
    public function isEditable() {
        return $this->editable;
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
    public function getMiddleName() {
        return $this->middleName;
    }

    /**
     *
     * @return string
     */
    public function getFamilyName() {
        return $this->familyName;
    }

    /**
     *
     * @return string
     */
    public function getFamilyName2() {
        return $this->familyName2;
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
     * @return int
     */
    public function getAge() {
        return $this->age;
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

    /**
     *
     * @return APIContactAddress[]
     */
    public function getAddresses() {
        return $this->addresses;
    }

    /**
     *
     * @return APIContactChannel[]
     */
    public function getPhones() {
        return $this->phones;
    }

    /**
     *
     * @return APIContactChannel[]
     */
    public function getEmails() {
        return $this->emails;
    }

    /**
     *
     * @return APIContactChannel[]
     */
    public function getDevices() {
        return $this->devices;
    }

    /*
     * **********************************
     * SETTERS
     * **********************************
     */
    /**
     *
     * @param string $value
     */
    public function setId($value) {
        $this->id = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setUsername($value) {
        $this->userName = $value;
    }

    /**
     *
     * @param boolean $value
     */
    public function setEditable($value) {
        $this->editable = textToBool($value);
    }

    /**
     *
     * @param string $value
     */
    public function setFullName($value) {
        $this->fullName = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setName($value) {
        $this->name = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setMiddleName($value) {
        $this->middleName = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setFamilyName($value) {
        $this->familyName = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setFamilyName2($value) {
        $this->familyName2 = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setBirthdate($value) {
        $this->bdate = $value;
    }

    /**
     *
     * @param int $value
     */
    public function setAge($value) {
        $this->age = $value;
    }

    /**
     *
     * @param string
     */
    public function setGender($value) {
        $this->gender = $value;
    }

    /*
     * **********************************
     * METHODS
     * **********************************
     */
    /**
     * Searches the list of Identifiers of this Contact and returns the one that matches the $id passed (or null if not found)
     *
     * @param string $id
     * @return APIIdentifier
     */
    public function findIdentifier($id) {
        foreach ($this->getIdentifiers() as $x) {
            if ($x->getId() == $id) {
                return $x;
            }
        }
        return null;
    }

    /**
     *
     * @param APIIdentifier
     */
    public function addIdentifier($identifier) {
        $this->identifiers[] = $identifier;
    }

    /**
     *
     * @param APIContactAddress
     */
    public function addAddress($address) {
        $this->addresses[] = $address;
    }

    /**
     *
     * @param APIContactChannel
     */
    public function addPhone($phone) {
        $this->phones[] = $phone;
    }

    /**
     *
     * @param APIContactChannel
     */
    public function addEmail($email) {
        $this->emails[] = $email;
    }

    /**
     *
     * @param APIContactChannel
     */
    public function addDevice($device) {
        $this->devices[] = $device;
    }

    /**
     *
     * @return XMLHelper $xml
     * @param SimpleXMLElement $parentNode
     */
    public function toXML($xml, $parentNode) {
        if ($parentNode === null) {
            $parentNode = $xml->rootNode;
        }

        $xml->createChildNode($parentNode, "ref", $this->getId());
        $dataNode = $xml->createChildNode($parentNode, "data");
        if ($this->getAge() !== null) {
            $xml->createChildNode($dataNode, "age", $this->getAge());
        }
        if ($this->getBirthdate() !== null) {
            $xml->createChildNode($dataNode, "bdate", $this->getBirthdate());
        }
        if ($this->getGender() !== null) {
            $xml->createChildNode($dataNode, "gender", $this->getGender());
        }

        if ($this->getName() !== null || $this->getFamilyName() != null || $this->getFamilyName2() !== null) {
            $nameNode = $xml->createChildNode($parentNode, "name");
            if ($this->getName() !== null) {
                $xml->createChildNode($nameNode, "given_name", $this->getName());
            }
            if ($this->getFamilyName() !== null) {
                $xml->createChildNode($nameNode, "family_name", $this->getFamilyName());
            }
            if ($this->getFamilyName2() !== null) {
                $xml->createChildNode($nameNode, "family_name2", $this->getFamilyName2());
            }
        }

        if (!empty($this->getIdentifiers())) {
            $identifiersNode = $xml->createChildNode($parentNode, "identifiers");
            foreach ($this->getIdentifiers() as $identifier) {
                $identifier->toXML($xml, $xml->createChildNode($identifiersNode, "identifier"));
            }
        }

        if (!empty($this->getAddresses())) {
            $addressesNode = $xml->createChildNode($parentNode, "addresses");
            foreach ($this->getAddresses() as $address) {
                $address->toXML($xml, $xml->createChildNode($addressesNode, "address"));
            }
        }

        $channelsNode = $xml->createChildNode($parentNode, "channels");
        if (!empty($this->getPhones())) {
            $phonesNode = $xml->createChildNode($channelsNode, "phones");
            foreach ($this->phones as $ch) {
                $ch->toXML($xml, $xml->createChildNode($phonesNode, "phone"));
            }
        }
        if (!empty($this->getEmails())) {
            $emailsNode = $xml->createChildNode($channelsNode, "emails");
            foreach ($this->emails as $ch) {
                $ch->toXML($xml, $xml->createChildNode($emailsNode, "email"));
            }
        }
        if (!empty($this->getDevices())) {
            $devicesNode = $xml->createChildNode($channelsNode, "devices");
            foreach ($this->getDevices() as $ch) {
                $ch->toXML($xml, $xml->createChildNode($devicesNode, "device"));
            }
        }

        return $parentNode;
    }
}