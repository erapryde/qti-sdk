<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * Copyright (c) 2013-2015 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 *
 * @author Jérôme Bogaerts <jerome@taotesting.com>
 * @license GPLv2
 */

namespace qtism\data\storage\xml\marshalling;

use qtism\common\collections\IdentifierCollection;
use qtism\common\utils\Version;
use qtism\data\ShowHide;
use qtism\data\content\FlowStaticCollection;
use qtism\data\QtiComponentCollection;
use qtism\data\QtiComponent;
use \DOMElement;

/**
 * The Marshaller implementation for SimpleAssociableChoice elements of the content model.
 *
 * @author Jérôme Bogaerts <jerome@taotesting.com>
 *
 */
class SimpleAssociableChoiceMarshaller extends ContentMarshaller
{
    /**
     * @see \qtism\data\storage\xml\marshalling\RecursiveMarshaller::unmarshallChildrenKnown()
     */
    protected function unmarshallChildrenKnown(DOMElement $element, QtiComponentCollection $children)
    {
        $version = $this->getVersion();
        
        if (($identifier = $this->getDOMElementAttributeAs($element, 'identifier')) !== null) {

            if (($matchMax = $this->getDOMElementAttributeAs($element, 'matchMax', 'integer')) !== null) {

                $fqClass = $this->lookupClass($element);
                $component = new $fqClass($identifier, $matchMax);

                if (($fixed = $this->getDOMElementAttributeAs($element, 'fixed', 'boolean')) !== null) {
                    $component->setFixed($fixed);
                }

                if (Version::compare($version, '2.1.0', '>=') === true && ($templateIdentifier = $this->getDOMElementAttributeAs($element, 'templateIdentifier')) !== null) {
                    $component->setTemplateIdentifier($templateIdentifier);
                }

                if (Version::compare($version, '2.1.0', '>=') === true && ($showHide = $this->getDOMElementAttributeAs($element, 'showHide')) !== null) {
                    $component->setShowHide(ShowHide::getConstantByName($showHide));
                }

                if (Version::compare($version, '2.1.0', '>=') === true && ($matchMin = $this->getDOMElementAttributeAs($element, 'matchMin', 'integer')) !== null) {
                    $component->setMatchMin($matchMin);
                }
                
                if (Version::compare($version, '2.1.0', '<') === true && ($matchGroup = $this->getDOMElementAttributeAs($element, 'matchGroup')) !== null) {
                    $component->setMatchGroup(new IdentifierCollection(explode("\x20", $matchGroup)));
                }

                $component->setContent(new FlowStaticCollection($children->getArrayCopy()));
                $this->fillBodyElement($component, $element);

                return $component;
            } else {
                $msg = "The mandatory 'matchMax' attribute is missing from the 'simpleAssociableChoice' element.";
                throw new UnmarshallingException($msg, $element);
            }
        } else {
            $msg = "The mandatory 'identifier' attribute is missing from the 'simpleAssociableChoice' element.";
            throw new UnmarshallingException($msg, $element);
        }
    }

    /**
     * @see \qtism\data\storage\xml\marshalling\RecursiveMarshaller::marshallChildrenKnown()
     */
    protected function marshallChildrenKnown(QtiComponent $component, array $elements)
    {
        $version = $this->getVersion();
        $element = self::getDOMCradle()->createElement($component->getQtiClassName());
        $this->fillElement($element, $component);

        self::setDOMElementAttribute($element, 'identifier', $component->getIdentifier());
        self::setDOMElementAttribute($element, 'matchMax', $component->getMatchMax());

        if ($component->isFixed() === true) {
            self::setDOMElementAttribute($element, 'fixed', true);
        }

        if ($component->hasTemplateIdentifier() === true && Version::compare($version, '2.1.0', '>=') === true) {
            self::setDOMElementAttribute($element, 'templateIdentifier', $component->getTemplateIdentifier());
        }

        if ($component->getShowHide() !== ShowHide::SHOW && Version::compare($version, '2.1.0', '>=') === true) {
            self::setDOMElementAttribute($element, 'showHide', ShowHide::getNameByConstant(ShowHide::HIDE));
        }

        if ($component->getMatchMin() !== 0 && Version::compare($version, '2.1.0', '>=') === true) {
            self::setDOMElementAttribute($element, 'matchMin', $component->getMatchMin());
        }
        
        if (Version::compare($version, '2.1.0', '<') === true) {
            $matchGroup = $component->getMatchGroup();
            if (count($matchGroup) > 0) {
                self::setDOMElementAttribute($element, 'matchGroup', implode(' ', $matchGroup->getArrayCopy()));
            }
        }

        foreach ($elements as $e) {
            $element->appendChild($e);
        }

        return $element;
    }

    /**
     * @see \qtism\data\storage\xml\marshalling\ContentMarshaller::setLookupClasses()
     */
    protected function setLookupClasses()
    {
        $this->lookupClasses = array("qtism\\data\\content\\interactions");
    }
}
