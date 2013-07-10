<?php

namespace qtism\data\expressions\operators;

use qtism\common\enums\Cardinality;
use qtism\data\expressions\ExpressionCollection;

/**
 * From IMS QTI:
 * 
 * The multiple operator takes 0 or more sub-expressions all of which must 
 * have either single or multiple cardinality. Although the sub-expressions 
 * may be of any base-type they must all be of the same base-type. The 
 * result is a container with multiple cardinality containing the values 
 * of the sub-expressions, sub-expressions with multiple cardinality have 
 * their individual values added to the result: containers cannot contain 
 * other containers. For example, when applied to A, B and {C,D} the 
 * multiple operator results in {A,B,C,D}. All sub-expressions with NULL 
 * values are ignored. If no sub-expressions are given (or all are NULL) 
 * then the result is NULL.
 * 
 * @author Jérôme Bogaerts <jerome@taotesting.com>
 *
 */
class Multiple extends Operator {
	
	public function __construct(ExpressionCollection $expressions) {
		parent::__construct($expressions, 0, -1, array(Cardinality::SINGLE, Cardinality::MULTIPLE), array(OperatorBaseType::SAME));
	}
	
	public function getQTIClassName() {
		return 'multiple';
	}
}