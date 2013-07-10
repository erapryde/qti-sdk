<?php

namespace qtism\data\storage\xml\marshalling;

use qtism\data\QtiComponent;
use qtism\data\state\CorrectResponse;
use qtism\data\state\Value;
use qtism\data\state\ValueCollection;
use qtism\data\storage\xml\marshalling\ValueMarshaller;
use qtism\common\enums\BaseType;
use \DOMElement;
use \InvalidArgumentException;

/**
 * Marshalling/Unmarshalling implementation for correctResponse.
 * 
 * @author Jérôme Bogaerts <jerome@taotesting.com>
 *
 */
class CorrectResponseMarshaller extends Marshaller {
	
	private $baseType = -1;
	
	public function setBaseType($baseType = -1) {
		if (in_array($baseType, BaseType::asArray()) || $baseType == -1) {
			$this->baseType = $baseType;
		}
		else {
			$msg = "The baseType argument must be a value from the BaseType enumeration.";
			throw new InvalidArgumentException($msg);
		}
	}
	
	public function getBaseType() {
		return $this->baseType;
	}
	
	public function __construct($baseType = -1) {
		$this->setBaseType($baseType);
	}
	
	/**
	 * Marshall a CorrectResponse object into a DOMElement object.
	 * 
	 * @param QtiComponent $component A CorrectResponse object.
	 * @return DOMElement The according DOMElement object.
	 */
	protected function marshall(QtiComponent $component) {
		$element = static::getDOMCradle()->createElement($component->getQTIClassName());
		
		$interpretation = $component->getInterpretation();
		if (!empty($interpretation)) {
			self::setDOMElementAttribute($element, 'interpretation', $interpretation);
		}
		
		// A CorrectResponse contains 1..* Value objects
		foreach ($component->getValues() as $value) {
			$valueMarshaller = $this->getMarshallerFactory()->createMarshaller($value, array($this->getBaseType()));
			$valueElement = $valueMarshaller->marshall($value);
			$element->appendChild($valueElement);
		}
		
		return $element;
	}
	
	/**
	 * Unmarshall a DOMElement object corresponding to a QTI correctResponse element.
	 * 
	 * @param DOMElement $element A DOMElement object.
	 * @return QtiComponent A CorrectResponse object.
	 * @throws UnmarshallingException If the DOMElement object cannot be unmarshalled in a valid CorrectResponse object.
	 */
	protected function unmarshall(DOMElement $element) {
		
		$interpretation = static::getDOMElementAttributeAs($element, 'interpretation', 'string');
		$interpretation = (empty($interpretation)) ? '' : $interpretation;

		// Retrieve the values ...
		$values = new ValueCollection();
		$valueElements = $element->getElementsByTagName('value');
		
		if ($valueElements->length > 0) {
			for ($i = 0; $i < $valueElements->length; $i++) {
				$valueMarshaller = $this->getMarshallerFactory()->createMarshaller($valueElements->item($i), array($this->getBaseType()));
				$values[] = $valueMarshaller->unmarshall($valueElements->item($i));
			}
			
			$object = new CorrectResponse($values, $interpretation);
			return $object;
		}
		else {
			$msg = "A 'correctResponse' QTI element must contain at least one 'value' QTI element.";
			throw new UnmarshallingException($msg, $element);
		}
		
	}
	
	public function getExpectedQTIClassName() {
		return 'correctResponse';
	}
}