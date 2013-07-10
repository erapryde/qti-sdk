<?php

namespace qtism\data\expressions\operators;

use qtism\common\enums\Cardinality;

use qtism\data\QtiComponentCollection;
use qtism\data\expressions\Expression;
use qtism\data\expressions\ExpressionCollection;
use \InvalidArgumentException;

/**
 * A QTI Operator is a composite expression. The expression
 * contains a set of sub-expressions.
 * 
 * @author Jérôme Bogaerts <jerome@taotesting.com>
 *
 */
abstract class Operator extends Expression {
	
	private $minOperands = 0;
	
	private $maxOperands = -1;
	
	private $acceptedCardinalities = array();
	
	private $acceptedBaseTypes = array();
	
	/**
	 * The sub-expressions.
	 * 
	 * @var ExpressionCollection
	 */
	private $expressions;
	
	/**
	 * Create a new instance of Operator.
	 * 
	 * @param ExpressionCollection $expressions The sub-expressions that form the operator.
	 * @param integer $minOperands The minimum operands count.
	 * @param integer $maxOperands The maximum operands count.
	 * @param array $acceptedCardinalities An array of values from the Cardinality enumeration.
	 * @param array $acceptedBaseTypes An array of values from the OperatorBaseType enumeration.
	 * @throws InvalidArgumentException If $expressions does not match the restrictions or an invalid argument is given.
	 */
	public function __construct(ExpressionCollection $expressions, $minOperands = 0, $maxOperands = -1, $acceptedCardinalities = array(Cardinality::SINGLE, Cardinality::MULTIPLE, Cardinality::ORDERED), $acceptedBaseTypes = array(OperatorBaseType::ANY)) {
		$this->setMinOperands($minOperands);
		$this->setMaxOperands($maxOperands);
		$this->setAcceptedCardinalities($acceptedCardinalities);
		$this->setAcceptedBaseTypes($acceptedBaseTypes);
		
		$this->setExpressions($expressions);
	}
	
	/**
	 * Set the collection of expressions that compose the Operation object.
	 * 
	 * @param ExpressionCollection $expressions A collection of $expressions that form the hierarchy of expressions.
	 * @throws InvalidArgumentException If $expressions does not contain at least one Expression object.
	 */
	public function setExpressions(ExpressionCollection $expressions) {
		// @todo use abstract method to detect if the expression count is
		// correct.
		$this->expressions = $expressions;
	}
	
	/**
	 * Get the collection of expressions that compose the Operator object.
	 * 
	 * @return ExpressionCollection A collection of Expression objects.
	 */
	public function getExpressions() {
		return $this->expressions;
	}
	
	public function getComponents() {
		$comp = $this->getExpressions()->getArrayCopy();
		return new QtiComponentCollection($comp);
	}
	
	/**
	 * Set the minimum operands count for this Operator.
	 * 
	 * @param int $minOperands An integer which is >= 0.
	 * @throws InvalidArgumentException If $minOperands is not an integer >= 0.
	 */
	protected function setMinOperands($minOperands) {
		if (is_int($minOperands) && $minOperands >= 0) {
			$this->minOperands = $minOperands;
		}
		else {
			$msg = "The minOperands argument must be an integer >= 0, '" . $minOperands . "' given.";
			throw new InvalidArgumentException($msg);
		}
	}
	
	/**
	 * Get the minimum operands count for this Operator.
	 * 
	 * @return int
	 */
	public function getMinOperands() {
		return $this->minOperands;
	}
	
	/**
	 * Set the maxmimum operands count for this Operator. The value
	 * is -1 if unlimited.
	 * 
	 * @param int $maxOperands
	 * @throws InvalidArgumentException If $maxOperands is not an integer.
	 */
	public function setMaxOperands($maxOperands) {
		if (is_int($maxOperands)) {
			$this->maxOperands = $maxOperands;
		}
		else {
			$msg = "The maxOperands argument must be an integer, '" . gettype($maxOperands) . "' given.";
			throw new InvalidArgumentException($msg);
		}
	}
	
	/**
	 * Get the maximum operands count for this Operator. The value
	 * is -1 if unlimited.
	 * 
	 * @return int
	 */
	public function getMaxOperands() {
		return $this->maxOperands;
	}
	
	/**
	 * Get the accepted operand cardinalities.
	 * 
	 * @return array An array of values from the Cardinality enumeration.
	 */
	public function getAcceptedCardinalities() {
		return $this->acceptedCardinalities;
	}
	
	/**
	 * Set the accepted operand cardinalities.
	 * 
	 * @param array $acceptedCardinalities An array of values from the Cardinality enumeration.
	 * @throws InvalidArgumentException If a value from $acceptedCardinalities is not a value from the Cardinality enumeration.
	 */
	public function setAcceptedCardinalities(array $acceptedCardinalities) {
		foreach ($acceptedCardinalities as $cardinality) {
			if (!in_array($cardinality, OperatorCardinality::asArray())) {
				$msg = "Accepted cardinalities must be values from the Cardinality enumeration, '" . $cardinality . "' given";
				throw new InvalidArgumentException($msg);
			}
		}
		
		$this->acceptedCardinalities = $acceptedCardinalities;
	}
	
	/**
	 * Get the accepted operand baseTypes.
	 * 
	 * @return array An array of values from OperatorBaseType enumeration.
	 */
	public function getAcceptedBaseTypes() {
		return $this->acceptedBaseTypes;
	}
	
	/**
	 * Set the accepted operand accepted baseTypes.
	 * 
	 * @param array $acceptedBaseTypes An array of values from the OperatorBaseType enumeration.
	 * @throws InvalidArgumentException If a value from the $acceptedBaseTypes is not a value from the OperatorBaseType.
	 */
	public function setAcceptedBaseTypes(array $acceptedBaseTypes) {
		foreach ($acceptedBaseTypes as $baseType) {
			if (!in_array($baseType, OperatorBaseType::asArray())) {
				$msg = "Accepted baseTypes must be values from the OperatorBaseType enumeration, '" . $baseType . "' given.";
				throw new InvalidArgumentException($msg);
			}
		}
		
		$this->acceptedBaseTypes = $acceptedBaseTypes;
	}
}