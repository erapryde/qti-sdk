<?php

namespace qtism\data\storage\xml\marshalling;

use qtism\data\QtiComponent;
use qtism\data\state\InterpolationTableEntry;
use qtism\common\enums\BaseType;
use qtism\data\state\Value;
use qtism\data\storage\Utils;
use \DOMElement;
use \InvalidArgumentException;

/**
 * Marshalling/Unmarshalling implementation for InterpolationTableEntry.
 * 
 * @author Jérôme Bogaerts <jerome@taotesting.com>
 *
 */
class InterpolationTableEntryMarshaller extends Marshaller {
	
	private $baseType = -1;
	
	/**
	 * Get the baseType of the variableDeclaration that contains
	 * the interpolationTableEntry to marshall.
	 * 
	 * @return integer A value from the BaseType enumeration.
	 */
	public function getBaseType() {
		return $this->baseType;
	}
	
	/**
	 * Set the base type of the variableDeclaration that contains
	 * the interpolationTableEntry to marshall.
	 * 
	 * @param int $baseType A value from the BaseType enumeration.
	 * @throws InvalidArgumentException If $baseType is not a value from the BaseType enumeration nor -1.
	 */
	public function setBaseType($baseType = -1) {
		if (in_array($baseType, BaseType::asArray()) || $baseType == -1) {
			$this->baseType = $baseType;
		}
		else {
			$msg = "The baseType attribute must be a value from the BaseType enumeration.";
			throw new InvalidArgumentException($msg);
		}
	}
	
	/**
	 * Create a new instance of InterpolationTableEntryMarshaller.
	 * 
	 * @param string $baseType The baseType of the variableDeclaration containing the InterpolationTableEntry to unmarshall.
	 */
	public function __construct($baseType = -1) {
		$this->setBaseType($baseType);
	}
	
	/**
	 * Marshall an InterpolationTableEntry object into a DOMElement object.
	 * 
	 * @param QtiComponent $component An InterpolationTableEntry object.
	 * @return DOMElement The according DOMElement object.
	 */
	protected function marshall(QtiComponent $component) {
		$element = static::getDOMCradle()->createElement($component->getQTIClassName());
		
		self::setDOMElementAttribute($element, 'sourceValue', $component->getSourceValue());
		self::setDOMElementAttribute($element, 'targetValue', $component->getTargetValue());
		self::setDOMElementAttribute($element, 'includeBoundary', $component->doesIncludeBoundary());
		
		return $element;
	}
	
	/**
	 * Unmarshall a DOMElement object corresponding to a QTI InterpolationEntry element.
	 * 
	 * @param DOMElement $element A DOMElement object.
	 * @return QtiComponent An InterpolationTableEntry object.
	 * @throws UnmarshallingException
	 */
	protected function unmarshall(DOMElement $element) {
		
		if (($sourceValue = static::getDOMElementAttributeAs($element, 'sourceValue', 'float')) !== null) {
			
			if (($targetValue = static::getDOMElementAttributeAs($element, 'targetValue', 'string')) !== null) {
				$object = new InterpolationTableEntry($sourceValue, Utils::stringToDatatype($targetValue, $this->getBaseType()));
				
				if (($includeBoundary = static::getDOMElementAttributeAs($element, 'includeBoundary', 'boolean')) !== null) {
					$object->setIncludeBoundary($includeBoundary);
				}

				return $object;
			}
		}
		else {
			$msg = "The mandatory attribute 'sourceValue' is missing from element '" . $element->nodeName . "'.";
			throw new UnmarshallingException($msg, $element);
		}
	}
	
	public function getExpectedQTIClassName() {
		return 'interpolationTableEntry';
	}
}