<?php
namespace qtismtest\runtime\expressions\operators;

use qtismtest\QtiSmTestCase;
use qtism\common\datatypes\QtiIdentifier;
use qtism\common\datatypes\QtiString;
use qtism\common\datatypes\QtiInteger;
use qtism\runtime\expressions\operators\OrderedProcessor;
use qtism\runtime\expressions\operators\OperandsCollection;
use qtism\common\enums\BaseType;
use qtism\runtime\common\OrderedContainer;
use qtism\runtime\common\RecordContainer;
use qtism\runtime\common\MultipleContainer;
use qtism\common\datatypes\QtiDuration;
use qtism\common\datatypes\QtiPoint;

class OrderedProcessorTest extends QtiSmTestCase {
	
	public function testNull() {
		$expression = $this->createFakeExpression();
		$operands = new OperandsCollection();
		$processor = new OrderedProcessor($expression, $operands);
		
		$operands[] = null;
		$result = $processor->process();
		$this->assertSame(null, $result);
		
		$operands = new OperandsCollection(array(new OrderedContainer(BaseType::FLOAT)));
		$processor->setOperands($operands);
		$result = $processor->process();
		$this->assertSame(null, $result);
		
		$operands = new OperandsCollection(array(null, new QtiInteger(25), new OrderedContainer(BaseType::INTEGER)));
		$processor->setOperands($operands);
		$result = $processor->process();
		$this->assertInstanceOf('qtism\\runtime\\common\\OrderedContainer', $result);
		$this->assertEquals(1, count($result));
		$this->assertEquals(BaseType::INTEGER, $result->getBaseType());
		$this->assertEquals(25, $result[0]->getValue());
		
		$operands = new OperandsCollection(array(null, new QtiInteger(25), new OrderedContainer(BaseType::INTEGER, array(new QtiInteger(26)))));
		$processor->setOperands($operands);
		$result = $processor->process();
		$this->assertInstanceOf('qtism\\runtime\\common\\OrderedContainer', $result);
		$this->assertEquals(2, count($result));
		$this->assertEquals(BaseType::INTEGER, $result->getBaseType());
		$this->assertEquals(25, $result[0]->getValue());
		$this->assertEquals(26, $result[1]->getValue());
		
		$operands = new OperandsCollection(array(new OrderedContainer(BaseType::INTEGER), new QtiInteger(25), new OrderedContainer(BaseType::INTEGER, array(new QtiInteger(26)))));
		$processor->setOperands($operands);
		$result = $processor->process();
		$this->assertInstanceOf('qtism\\runtime\\common\\OrderedContainer', $result);
		$this->assertEquals(2, count($result));
		$this->assertEquals(BaseType::INTEGER, $result->getBaseType());
		$this->assertEquals(25, $result[0]->getValue());
		$this->assertEquals(26, $result[1]->getValue());
		
		$operands = new OperandsCollection();
		$processor->setOperands($operands);
		$result = $processor->process();
		$this->assertSame(null, $result);
	}
	
	public function testScalar() {
		$expression = $this->createFakeExpression();
		$operands = new OperandsCollection();
		$operands[] = new QtiString('String1');
		$operands[] = new QtiString('String2');
		$operands[] = new QtiString('String3');
		$processor = new OrderedProcessor($expression, $operands);
		
		$result = $processor->process();
		$this->assertInstanceOf('qtism\\runtime\\common\\OrderedContainer', $result);
		$this->assertEquals(3, count($result));
		$this->assertEquals('String1', $result[0]->getValue());
		$this->assertEquals('String2', $result[1]->getValue());
		$this->assertEquals('String3', $result[2]->getValue());
		
		$operands = new OperandsCollection(array(new QtiString('String!')));
		$processor->setOperands($operands);
		$result = $processor->process();
		$this->assertInstanceOf('qtism\\runtime\\common\\OrderedContainer', $result);
		$this->assertEquals(1, count($result));
	}
	
	public function testContainer() {
		$expression = $this->createFakeExpression();
		$operands = new OperandsCollection();
		$operands[] = new OrderedContainer(BaseType::POINT, array(new QtiPoint(1, 2), new QtiPoint(2, 3)));
		$operands[] = new OrderedContainer(BaseType::POINT);
		$operands[] = new OrderedContainer(BaseType::POINT, array(new QtiPoint(3, 4)));
		$processor = new OrderedProcessor($expression, $operands);
		$result = $processor->process();
		$this->assertInstanceOf('qtism\\runtime\\common\\OrderedContainer', $result);
		$this->assertEquals(3, count($result));
		$this->assertTrue($result[0]->equals(new QtiPoint(1, 2)));
		$this->assertTrue($result[1]->equals(new QtiPoint(2, 3)));
		$this->assertTrue($result[2]->equals(new QtiPoint(3, 4)));
	}
	
	public function testMixed() {
		$expression = $this->createFakeExpression();
		$operands = new OperandsCollection();
		$operands[] = new QtiPoint(1, 2);
		$operands[] = null;
		$operands[] = new OrderedContainer(BaseType::POINT, array(new QtiPoint(3, 4)));
		$processor = new OrderedProcessor($expression, $operands);
		$result = $processor->process();
		$this->assertInstanceOf('qtism\\runtime\\common\\OrderedContainer', $result);
		$this->assertEquals(2, count($result));
		$this->assertTrue($result[0]->equals(new QtiPoint(1, 2)));
		$this->assertTrue($result[1]->equals(new QtiPoint(3, 4)));
	}
	
	public function testWrongBaseTypeOne() {
	    $expression = $this->createFakeExpression();
	    $operands = new OperandsCollection();
	    $operands[] = new OrderedContainer(BaseType::IDENTIFIER, array(new QtiIdentifier('identifier1'), new QtiIdentifier('identifier2')));
	    $operands[] = new QtiIdentifier('identifier3');
	    $operands[] = new OrderedContainer(BaseType::STRING, array(new QtiString('string1'), new QtiString('string2')));
	    $operands[] = null;
	    $processor = new OrderedProcessor($expression, $operands);
	    $this->setExpectedException('qtism\\runtime\\expressions\\ExpressionProcessingException');
	    $result = $processor->process();
	}
	
	public function testWrongBaseTypeTwo() {
		$expression = $this->createFakeExpression();
		$operands = new OperandsCollection();
		$operands[] = new QtiPoint(1, 2);
		$operands[] = new QtiDuration('P2D');
		$operands[] = null;
		$operands[] = new QtiInteger(10);
		$processor = new OrderedProcessor($expression, $operands);
		$this->setExpectedException('qtism\\runtime\\expressions\\ExpressionProcessingException');
		$result = $processor->process();
	}
	
	public function testWrongCardinality() {
		$expression = $this->createFakeExpression();
		$operands = new OperandsCollection();
		$operands[] = new QtiInteger(10);
		$operands[] = null;
		$operands[] = new OrderedContainer(BaseType::INTEGER, array(new QtiInteger(10)));
		$processor = new OrderedProcessor($expression, $operands);
		$result = $processor->process();
		
		$operands[] = new RecordContainer();
		$this->setExpectedException('qtism\\runtime\\expressions\\ExpressionProcessingException');
		$result = $processor->process();
	}
	
	public function createFakeExpression() {
		return $this->createComponentFromXml('
			<ordered>
				<baseValue baseType="boolean">false</baseValue>
				<baseValue baseType="boolean">true</baseValue>
				<ordered>
					<baseValue baseType="boolean">false</baseValue>
				</ordered>
			</ordered>
		');
	}
}