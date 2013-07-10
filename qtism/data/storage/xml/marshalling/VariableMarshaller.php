<?php

namespace qtism\data\storage\xml\marshalling;

use qtism\data\QtiComponent;
use qtism\data\expressions\Variable;
use \DOMElement;

/**
 * Marshalling/Unmarshalling implementation for variable.
 * 
 * @author Jérôme Bogaerts <jerome@taotesting.com>
 *
 */
class VariableMarshaller extends Marshaller {
	
	/**
	 * Marshall a Variable object into a DOMElement object.
	 * 
	 * @param QtiComponent $component A Variable object.
	 * @return DOMElement The according DOMElement object.
	 */
	protected function marshall(QtiComponent $component) {
		$element = static::getDOMCradle()->createElement($component->getQTIClassName());
		
		self::setDOMElementAttribute($element, 'identifier', $component->getIdentifier());
		
		$weightIdentifier = $component->getWeightIdentifier();
		if (!empty($weightIdentifier)) {
			self::setDOMElementAttribute($element, 'weightIdentifier', $weightIdentifier);
		}
		
		return $element;
	}
	
	/**
	 * Unmarshall a DOMElement object corresponding to a QTI Variable element.
	 * 
	 * @param DOMElement $element A DOMElement object.
	 * @return QtiComponent A Variable object.
	 * @throws UnmarshallingException If the mandatory attribute 'identifier' is not set in $element.
	 */
	protected function unmarshall(DOMElement $element) {
		
		if (($identifier = static::getDOMElementAttributeAs($element, 'identifier')) !== null) {
			$object = new Variable($identifier);
			
			if (($weightIdentifier = static::getDOMElementAttributeAs($element, 'weightIdentifier')) !== null) {
				$object->setWeightIdentifier($weightIdentifier);
			}
			
			return $object;
		}
		else {
			$msg = "The mandatory attribute 'identifier' is missing from element '" . $element->nodeName . "'.";
			throw new UnmarshallingException($msg, $element);
		}
	}
	
	public function getExpectedQTIClassName() {
		return 'variable';
	}
}