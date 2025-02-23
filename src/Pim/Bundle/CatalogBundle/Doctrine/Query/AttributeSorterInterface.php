<?php

namespace Pim\Bundle\CatalogBundle\Doctrine\Query;

use Pim\Bundle\CatalogBundle\Model\AttributeInterface;

/**
 * Sorter interface
 *
 * @author    Nicolas Dupont <nicolas@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
interface AttributeSorterInterface extends SorterInterface
{
    /**
     * Sort by attribute value
     *
     * @param AttributeInterface $attribute the attribute to sort on
     * @param string             $direction the direction to use
     * @param array              $context   the sorter context, used for locale and scope
     *
     * @return AttributeSorterInterface
     */
    public function addAttributeSorter(AttributeInterface $attribute, $direction, array $context = []);

    /**
     * This filter supports the attribute
     *
     * @param AttributeInterface $attribute
     *
     * @return boolean
     */
    public function supportsAttribute(AttributeInterface $attribute);
}
