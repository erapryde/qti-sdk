<?php

namespace qtism\data\storage\xml\marshalling;

use qtism\data\state\OutcomeDeclarationCollection;
use qtism\data\TestFeedbackCollection;
use qtism\data\TestPartCollection;
use qtism\data\AssessmentTest;
use qtism\data\QtiComponent;
use \DOMElement;

/**
 * Marshalling/Unmarshalling implementation for assessmentTest.
 * 
 * @author Jérôme Bogaerts <jerome@taotesting.com>
 *
 */
class AssessmentTestMarshaller extends SectionPartMarshaller {
	
	/**
	 * Marshall an AssessmentTest object into a DOMElement object.
	 * 
	 * @param QtiComponent $component An AssessmentTest object.
	 * @return DOMElement The according DOMElement object.
	 * @throws MarshallingException
	 */
	protected function marshall(QtiComponent $component) {
		$element = self::getDOMCradle()->createElement($component->getQTIClassName());
		
		self::setDOMElementAttribute($element, 'identifier', $component->getIdentifier());
		self::setDOMElementAttribute($element, 'title', $component->getTitle());
		
		$toolName = $component->getToolName();
		if (!empty($toolName)) {
			self::setDOMElementAttribute($element, 'toolName', $component->getToolName());
		}
		
		$toolVersion = $component->getToolVersion();
		if (!empty($toolVersion)) {
			self::setDOMElementAttribute($element, 'toolVersion', $component->getToolVersion());
		}
		
		foreach ($component->getOutcomeDeclarations() as $outcomeDeclaration) {
			$marshaller = $this->getMarshallerFactory()->createMarshaller($outcomeDeclaration);
			$element->appendChild($marshaller->marshall($outcomeDeclaration));
		}
		
		foreach ($component->getTestParts() as $part) {
			$marshaller = $this->getMarshallerFactory()->createMarshaller($part);
			$element->appendChild($marshaller->marshall($part));
		}
		
		$outcomeProcessing = $component->getOutcomeProcessing();
		if (!empty($outcomeProcessing)) {
			$marshaller = $this->getMarshallerFactory()->createMarshaller($outcomeProcessing);
			$element->appendChild($marshaller->marshall($outcomeProcessing));
		}
		
		foreach ($component->getTestFeedbacks() as $feedback) {
			$marshaller = $this->getMarshallerFactory()->createMarshaller($feedback);
			$element->appendChild($marshaller->marshall($feedback));
		}
		
		return $element;
	}
	
	/**
	 * Unmarshall a DOMElement object corresponding to a QTI outcomeProcessing element.
	 * 
	 * If $assessmentTest is provided, it will be decorated with the unmarshalled data and returned,
	 * instead of creating a new AssessmentTest object.
	 * 
	 * @param DOMElement $element A DOMElement object.
	 * @param AssessmentTest $assessmentTest An AssessmentTest object to decorate.
	 * @return QtiComponent An OutcomeProcessing object.
	 * @throws UnmarshallingException
	 */
	protected function unmarshall(DOMElement $element, AssessmentTest $assessmentTest = null) {
		
		if (($identifier = static::getDOMElementAttributeAs($element, 'identifier')) !== null) {
			
			if (($title = static::getDOMElementAttributeAs($element, 'title')) !== null) {
				
				if (empty($assessmentTest)) {
					$object = new AssessmentTest($identifier, $title);
				}
				else {
					$object = $assessmentTest;
					$object->setIdentifier($identifier);
					$object->setTitle($title);
				}
				
				// Get the test parts.
				$testPartsElts = self::getChildElementsByTagName($element, 'testPart');
				
				if (count($testPartsElts) > 0) {
					$testParts = new TestPartCollection();
					
					foreach ($testPartsElts as $partElt) {
						$marshaller = $this->getMarshallerFactory()->createMarshaller($partElt);
						$testParts[] = $marshaller->unmarshall($partElt);
					}
					
					$object->setTestParts($testParts);
					
					if (($toolName = static::getDOMElementAttributeAs($element, 'toolName')) !== null) {
						$object->setToolName($toolName);
					}
					
					if (($toolVersion = static::getDOMElementAttributeAs($element, 'toolVersion')) !== null) {
						$object->setToolVersion($toolVersion);
					}
					
					$testFeedbackElts = self::getChildElementsByTagName($element, 'testFeedback');
					if (count($testFeedbackElts) > 0) {
						$testFeedbacks = new TestFeedbackCollection();
						
						foreach ($testFeedbackElts as $feedbackElt) {
							$marshaller = $this->getMarshallerFactory()->createMarshaller($feedbackElt);
							$testFeedbacks[] = $marshaller->unmarshall($feedbackElt);
						}
						
						$object->setTestFeedbacks($testFeedbacks);
					}
					
					$outcomeDeclarationElts = self::getChildElementsByTagName($element, 'outcomeDeclaration');
					if (count($outcomeDeclarationElts) > 0) {
						$outcomeDeclarations = new OutcomeDeclarationCollection();
						
						foreach ($outcomeDeclarationElts as $outcomeDeclarationElt) {
							$marshaller = $this->getMarshallerFactory()->createMarshaller($outcomeDeclarationElt);
							$outcomeDeclarations[] = $marshaller->unmarshall($outcomeDeclarationElt);
						}
						
						$object->setOutcomeDeclarations($outcomeDeclarations);
					}
					
					$outcomeProcessingElts = self::getChildElementsByTagName($element, 'outcomeProcessing');
					if (isset($outcomeProcessingElts[0])) {
						$marshaller = $this->getMarshallerFactory()->createMarshaller($outcomeProcessingElts[0]);
						$object->setOutcomeProcessing($marshaller->unmarshall($outcomeProcessingElts[0]));
					}
					
					return $object;
				}
				else {
					$msg = "An 'assessmentTest' element must contain at least one 'testPart' child element. None found.";
					throw new UnmarshallingException($msg, $element);
				}
			}
			else {
				$msg = "The mandatory attribute 'title' is missing from element 'assessmentTest'.";
				throw new UnmarshallingException($msg, $element);
			}
			
		}
		else {
			$msg = "The mandatory attribute 'identifier' is missing from element 'assessmentTest'.";
			throw new UnmarshallingException($msg, $element);
		}
	}
	
	public function getExpectedQTIClassName() {
		return 'assessmentTest';
	}
}