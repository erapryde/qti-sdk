<?php

namespace qtism\data\state;

use qtism\data\QtiComponentCollection;
use qtism\data\QtiComponent;
use qtism\data\expressions\Expression as Expression;
use qtism\common\utils\Format as Format;
use \InvalidArgumentException as InvalidArgumentException;

/**
 * From IMS QTI:
 * 
 * The default value of a template variable in an item can be overridden based 
 * on the test context in which the template is instantiated. The value is obtained 
 * by evaluating an expression defined within the reference to the item at test 
 * level and which may therefore depend on the values of variables taken from 
 * other items in the test or from outcomes defined at test level itself.
 * 
 * @author Jérôme Bogaerts <jerome@taotesting.com>
 *
 */
class TemplateDefault extends QtiComponent {
	
	/**
	 * The identifier of the template variable affected.
	 * 
	 * @var string
	 */
	private $templateIdentifier;
	
	/**
	 * An expression which must result in a value with baseType and cardinality matching
	 * the declaration of the assossiated variable's templateDeclaration.
	 * 
	 * @var Expression
	 */
	private $expression;
	
	/**
	 * Create a new instance of TemplateDefault.
	 * 
	 * @param string $templateIdentifier The identifier of the template variable affected.
	 * @param Expression $expression The expression that produces the new template default.
	 * @throws InvalidArgumentException If $templateIdentifier is not a valid QTI Identifier.
	 */
	public function __construct($templateIdentifier, Expression $expression) {
		$this->setTemplateIdentifier($templateIdentifier);
		$this->setExpression($expression);
	}
	
	/**
	 * Get the identifier of the template variable affected.
	 * 
	 * @return string A QTI identifier.
	 */
	public function getTemplateIdentifier() {
		return $this->templateIdentifier;
	}
	
	/**
	 * Set the identifier of the template variable affected.
	 * 
	 * @param string $templateIdentifier A QTI identifier.
	 * @throws InvalidArgumentException If $templateIdentifier is not a valid QTI Identifier.
	 */
	public function setTemplateIdentifier($templateIdentifier) {
		if (Format::isIdentifier($templateIdentifier)) {
			$this->templateIdentifier = $templateIdentifier;
		}
		else {
			$msg = "'${templateIdentifier}' is not a valid QTI Identifier.";
			throw new InvalidArgumentException($msg);
		}
	}
	
	/**
	 * Get the expression that produces the new template default.
	 * 
	 * @return Expression A QTI Expression.
	 */
	public function getExpression() {
		return $this->expression;
	}
	
	/**
	 * Set the expression that produces the new template defaul.
	 * 
	 * @param Expression $expression A QTI Expression.
	 */
	public function setExpression(Expression $expression) {
		$this->expression = $expression;
	}
	
	public function getQTIClassName() {
		return 'templateDefault';
	}
	
	public function getComponents() {
		return new QtiComponentCollection(array($this->getExpression()));
	}
}