<?php
namespace qtismtest\data\storage\xml\marshalling;

use qtismtest\QtiSmTestCase;
use qtism\data\content\FlowStaticCollection;
use qtism\data\content\TextRun;
use qtism\data\content\InlineStaticCollection;
use qtism\data\content\interactions\Prompt;
use qtism\data\content\xhtml\Object;
use qtism\data\content\interactions\SelectPointInteraction;
use \DOMDocument;

class SelectPointInteractionMarshallerTest extends QtiSmTestCase {

	public function testMarshall21() {
	    $object = new Object('./myimg.png', 'image/png');
	    $prompt = new Prompt();
	    $prompt->setContent(new FlowStaticCollection(array(new TextRun('Prompt...'))));
	    $selectPointInteraction = new SelectPointInteraction('RESPONSE', $object);
	    $selectPointInteraction->setPrompt($prompt);
	    $selectPointInteraction->setMinChoices(1);
        $selectPointInteraction->setMaxChoices(1);
        $selectPointInteraction->setXmlBase('/home/jerome');
	    
        $element = $this->getMarshallerFactory('2.1.0')->createMarshaller($selectPointInteraction)->marshall($selectPointInteraction);
        
        $dom = new DOMDocument('1.0', 'UTF-8');
        $element = $dom->importNode($element, true);
        $this->assertEquals('<selectPointInteraction responseIdentifier="RESPONSE" maxChoices="1" minChoices="1" xml:base="/home/jerome"><prompt>Prompt...</prompt><object data="./myimg.png" type="image/png"/></selectPointInteraction>', $dom->saveXML($element));
	}
	
	/**
	 * @depends testMarshall21
	 */
	public function testMarshall20() {
	    // Make sure minChoices is not in the output in a QTI 2.0 context.
	    $object = new Object('./myimg.png', 'image/png');
	    $selectPointInteraction = new SelectPointInteraction('RESPONSE', $object);
	    $selectPointInteraction->setMinChoices(1);
        $selectPointInteraction->setMaxChoices(1);
	     
	    $element = $this->getMarshallerFactory('2.0.0')->createMarshaller($selectPointInteraction)->marshall($selectPointInteraction);
	
	    $dom = new DOMDocument('1.0', 'UTF-8');
	    $element = $dom->importNode($element, true);
	    $this->assertEquals('<selectPointInteraction responseIdentifier="RESPONSE" maxChoices="1"><object data="./myimg.png" type="image/png"/></selectPointInteraction>', $dom->saveXML($element));
	}
	
	public function testUnmarshall21() {
        $element = $this->createDOMElement('
            <selectPointInteraction responseIdentifier="RESPONSE" minChoices="1" maxChoices="1" xml:base="/home/jerome">
              <prompt>Prompt...</prompt>
              <object data="./myimg.png" type="image/png"/>
            </selectPointInteraction>
        ');
        
        $component = $this->getMarshallerFactory('2.1.0')->createMarshaller($element)->unmarshall($element);
        $this->assertInstanceOf('qtism\\data\\content\\interactions\\SelectPointInteraction', $component);
        $this->assertEquals('RESPONSE', $component->getResponseIdentifier());
        $this->assertEquals(1, $component->getMaxChoices());
        $this->assertEquals(1, $component->getMinChoices());
        $this->assertEquals('/home/jerome', $component->getXmlBase());
        
        $this->assertTrue($component->hasPrompt());
        $promptContent = $component->getPrompt()->getContent();
        $this->assertEquals('Prompt...', $promptContent[0]->getContent());
        
        $object = $component->getObject();
        $this->assertEquals('./myimg.png', $object->getData());
        $this->assertEquals('image/png', $object->getType());
	}
    
    /**
     * @depends testUnmarshall21
     */
    public function testUnmarshall21NoObject() {
        $element = $this->createDOMElement('
            <selectPointInteraction responseIdentifier="RESPONSE" minChoices="1" maxChoices="1" xml:base="/home/jerome">
              <prompt>Prompt...</prompt>
            </selectPointInteraction>
        ');
        
        $this->setExpectedException(
            'qtism\\data\\storage\\xml\\marshalling\\UnmarshallingException',
            "A 'selectPointInteraction' element must contain exactly one 'object' element, none given."
        );
        
        $component = $this->getMarshallerFactory('2.1.0')->createMarshaller($element)->unmarshall($element);
	}
	
    /**
     * @depends testUnmarshall21
     */
    public function testUnmarshall21NoResponseIdentifier() {
        $element = $this->createDOMElement('
            <selectPointInteraction minChoices="1" maxChoices="1" xml:base="/home/jerome">
              <prompt>Prompt...</prompt>
              <object data="./myimg.png" type="image/png"/>
            </selectPointInteraction>
        ');
        
        $this->setExpectedException(
            'qtism\\data\\storage\\xml\\marshalling\\UnmarshallingException',
            "The mandatory 'responseIdentifier' attribute is missing from the 'selectPointInteraction' element."
        );
        
        $component = $this->getMarshallerFactory('2.1.0')->createMarshaller($element)->unmarshall($element);
	}
    
	/**
	 * @depends testUnmarshall21
	 */
	public function testUnmarshall20() {
	    // Make sure minChoices is not taken into account.
	    $element = $this->createDOMElement('
            <selectPointInteraction responseIdentifier="RESPONSE" minChoices="1" maxChoices="1">
              <object data="./myimg.png" type="image/png"/>
            </selectPointInteraction>
        ');
	    
	    $component = $this->getMarshallerFactory('2.0.0')->createMarshaller($element)->unmarshall($element);
	    $this->assertEquals(0, $component->getMinChoices());
	}
    
    /**
	 * @depends testUnmarshall21
	 */
	public function testUnmarshall20MissingMaxChoices() {
	    $element = $this->createDOMElement('
            <selectPointInteraction responseIdentifier="RESPONSE" minChoices="1">
              <object data="./myimg.png" type="image/png"/>
            </selectPointInteraction>
        ');
	    
        $this->setExpectedException(
            'qtism\\data\\storage\\xml\\marshalling\\UnmarshallingException',
            "The mandatory 'maxChoices' attribute is missing from the 'selectPointInteraction' element."
        );
        
	    $component = $this->getMarshallerFactory('2.0.0')->createMarshaller($element)->unmarshall($element);
	}
}
