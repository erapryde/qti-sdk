<?php

namespace qtism\data\state;

use qtism\data\QtiComponentCollection;

use \InvalidArgumentException;
use qtism\data\QtiComponent;

/**
 * From IMS QTI:
 * 
 * An interpolationTable transforms a source float (or integer) by finding the first 
 * interpolationTableEntry with a sourceValue that is less than or equal to 
 * (subject to includeBoundary) the source value.
 * 
 * For example, an interpolation table can be used to map a raw numeric score onto 
 * an identifier representing a grade. It may also be used to implement numeric 
 * transformations such as those from a simple raw score to a value on a calibrated scale.
 * 
 * @author Jérôme Bogaerts <jerome@taotesting.com>
 *
 */
class InterpolationTable extends LookupTable {
	
	private $interpolationTableEntries;
	
	/**
	 * Create a new instance of InterpolationTable.
	 * 
	 * @param InterpolationTableEntryCollection $interpolationTableEntries A collection of InterpolationTableEntry objects.
	 * @param mixed $defaultValue The default oucome value to be used when no matching table entry is found.
	 * @throws InvalidArgumentException If $interpolationTableEntries is an empty collection.
	 */
	public function __construct(InterpolationTableEntryCollection $interpolationTableEntries, $defaultValue = null) {
		parent::__construct($defaultValue);
		$this->setInterpolationTableEntries($interpolationTableEntries);
	}
	
	/**
	 * Get the InterpolationTableEntry objects contained by the InterpolationTable.
	 * 
	 * @return InterpolationTableEntryCollection A collection of InterpolationTableEntry objects.
	 */
	public function getInterpolationTableEntries() {
		return $this->interpolationTableEntries;
	}
	
	/**
	 * Set the InterpolationTableEntry objects contained by the current InterpolationTable.
	 * 
	 * @param InterpolationTableEntryCollection $interpolationTableEntries A collection of InterpolationTableEntry objects.
	 * @throws InvalidArgumentException If $interpolationTableEntries is an empty collection.
	 */
	public function setInterpolationTableEntries(InterpolationTableEntryCollection $interpolationTableEntries) {
		if (count($interpolationTableEntries) > 0) {
			$this->interpolationTableEntries = $interpolationTableEntries;
		}
		else {
			$msg = "An InterpolationTable object must contain at least one InterpolationTableEntry object.";
			throw new InvalidArgumentException($msg);
		}
	}
	
	public function getQTIClassName() {
		return 'interpolationTable';
	}
	
	public function getComponents() {
		$comp = array_merge(parent::getComponents()->getArrayCopy(), 
							$this->getInterpolationTableEntries()->getArrayCopy());
		return new QtiComponentCollection($comp);
	}
}