<?php

namespace qtism\data\state;

use qtism\data\QtiComponentCollection;
use qtism\data\QtiComponent;
use \InvalidArgumentException;

/**
 * The QTI MapEntry class implementation.
 * 
 * Author note: The specification says that the caseSensitive attribute is mandatory.
 * However, the XSD file for version 2.1 states that it is not. What is its default
 * value then? We decided to set it up to true.
 * 
 * @author Jérôme Bogaerts <jerome@taotesting.com>
 *
 */
class MapEntry extends QtiComponent {
	
	/**
	 * From IMS QTI:
	 * 
	 * The source value.
	 * 
	 * Note: qti:valueType
	 * 
	 * @var mixed
	 */
	private $mapKey;
	
	/**
	 * From IMS QTI:
	 * 
	 * The mapped value.
	 * 
	 * @var float
	 */
	private $mappedValue;
	
	/**
	 * From IMS QTI:
	 * 
	 * Used to control whether or not a mapEntry string is matched case sensitively.
	 * 
	 * @var boolean
	 */
	private $caseSensitive = true;
	
	/**
	 * Create a new MapEntry object.
	 * 
	 * @param mixed $mapKey A qti:valueType value (any baseType).
	 * @param float $mappedValue A mapped value.
	 * @param boolean $caseSensitive Whether a mapEntry string is matched case sensitively.
	 * @throws InvalidArgumentException If $mappedValue is not a float or $caseSensitive is not a boolean.
	 */
	public function __construct($mapKey, $mappedValue, $caseSensitive = true) {
		$this->setMapKey($mapKey);
		$this->setMappedValue($mappedValue);
		$this->setCaseSensitive($caseSensitive);
	}
	
	/**
	 * Set the source value.
	 * 
	 * @param mixed $mapKey A qti:valueType value.
	 */
	public function setMapKey($mapKey) {
		$this->mapKey = $mapKey;
	}
	
	/**
	 * Get the source value.
	 * 
	 * @return mixed A qti:valueType value.
	 */
	public function getMapKey() {
		return $this->mapKey;
	}
	
	/**
	 * Set the mapped value.
	 * 
	 * @param float $mappedValue A mapped value.
	 * @throws InvalidArgumentException If $mappedValue is not a float value.
	 */
	public function setMappedValue($mappedValue) {
		if (is_float($mappedValue) || is_double($mappedValue)) {
			$this->mappedValue = $mappedValue;
		}
		else {
			$msg = "The attribute 'mappedValue' must be a float value, '" . gettype($mappedValue) . "' given.";
			throw new InvalidArgumentException($msg);
		}
	}
	
	/**
	 * Get the mapped value.
	 * 
	 * @return float A mapped value.
	 */
	public function getMappedValue() {
		return $this->mappedValue;
	}
	
	/**
	 * Set whether the mapEntry string is matched case sensitively.
	 * 
	 * @param boolean $caseSensitive
	 * @throws InvalidArgumentException If $caseSensitive is not a boolean value.
	 */
	public function setCaseSensitive($caseSensitive) {
		if (is_bool($caseSensitive)) {
			$this->caseSensitive = $caseSensitive;
		}
		else {
			$msg = "The attribute 'caseSensitive' must be a boolean value, '" . gettype($caseSensitive) . "'.";
			throw new InvalidArgumentException($msg);
		}
	}
	
	/**
	 * Whether the mapEntry string is matched case sensitively.
	 * 
	 * @return boolean
	 */
	public function isCaseSensitive() {
		return $this->caseSensitive;
	}
	
	public function getQTIClassName() {
		return 'mapEntry';
	}
	
	public function getComponents() {
		return new QtiComponentCollection();
	}
}