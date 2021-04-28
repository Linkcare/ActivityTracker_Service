<?php

class APIIdentifier {
    private $id;
    private $description;
    private $value;

    public function __construct($id = null, $value = null) {
        $this->setId($id);
        $this->setValue($value);
    }

    /**
     *
     * @param SimpleXMLElement $xmlNode
     * @return APIIdentifier
     */
    static public function parseXML($xmlNode) {
        if (!$xmlNode) {
            return null;
        }
        $identifier = new APIIdentifier();
        $identifier->id = (string) $xmlNode->label;
        $identifier->description = (string) $xmlNode->description;
        $identifier->value = (string) $xmlNode->value;
        return $identifier;
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
    public function getDescription() {
        return $this->description;
    }

    /**
     *
     * @return string
     */
    public function getValue() {
        return $this->value;
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
    public function setValue($value) {
        $this->value = $value;
    }

    /*
     * **********************************
     * METHODS
     * **********************************
     */

    /**
     *
     * @param XMLHelper $xml
     * @param SimpleXMLElement $parentNode
     * @return SimpleXMLElement
     */
    public function toXML($xml, $parentNode) {
        if ($parentNode === null) {
            $parentNode = $xml->rootNode;
        }

        $xml->createChildNode($parentNode, "label", $this->getId());
        $xml->createChildNode($parentNode, "value", $this->getValue());

        return $parentNode;
    }
}