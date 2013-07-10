<?php

namespace qtism\data\storage\xml;

use qtism\data\storage\xml\LibXmlErrorCollection;
use qtism\data\storage\StorageException;
use \Exception;

/**
 * An exception type that represent an error when dealing with QTI-XML files.
 * 
 * @author Jérôme Bogaerts <jerome@taotesting.com>
 *
 */
class XmlStorageException extends StorageException {
	
	/**
	 * An array containing libxml errors.
	 * 
	 * @var LibXmlErrorCollection
	 */
	private $errors = null;
	
	/**
	 * 
	 * 
	 * @param string $message A human-readable message describing the exception.
	 * @param Exception $previous An optional previous exception which is the cause of this one.
	 * @param array $errors An array of errors (stdClass) as generated by libxml_get_errors().
	 */
	public function __construct($message, $previous = null, LibXmlErrorCollection $errors = null) {
		parent::__construct($message, 0, $previous);
		
		if (empty($errors)) {
			$errors = new LibXmlErrorCollection();
		}
		
		$this->setErrors($errors);
	}
	
	/**
	 * Set the errors returned by libxml_get_errors.
	 * 
	 * @param LibXmlErrorCollection $errors A collection of LibXMLError objects.
	 */
	protected function setErrors(LibXmlErrorCollection $errors = null) {
		$this->errors = $errors;
	}
	
	/**
	 * Get the errors generated by libxml_get_errors.
	 * 
	 * @return LibXmlErrorCollection A collection of LibXMLError objects.
	 */
	public function getErrors() {
		return $this->errors;
	}
}