<?php

namespace Pim\Bundle\CatalogBundle\Updater\Setter;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Pim\Bundle\CatalogBundle\Doctrine\SmartManagerRegistry;
use Pim\Bundle\CatalogBundle\Model\AttributeInterface;
use Pim\Bundle\CatalogBundle\Builder\ProductBuilder;
use Pim\Bundle\CatalogBundle\Updater\Util\AttributeUtility;

/**
 * Sets a multi select value in many products
 *
 * @author    Olivier Soulet <olivier.soulet@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class MultiSelectValueSetter implements SetterInterface
{
    /** @var ProductBuilder */
    protected $productBuilder;

    /** @var array */
    protected $types;

    /** @var SmartManagerRegistry */
    protected $em;

    /**
     * @param ProductBuilder $builder
     * @param EntityManager  $entityManager
     * @param array          $supportedTypes
     */
    public function __construct(ProductBuilder $builder, SmartManagerRegistry $entityManager, array $supportedTypes)
    {
        $this->productBuilder = $builder;
        $this->types = $supportedTypes;
        $this->em = $entityManager;
    }

    /**
     * {@inheritdoc}
     */
    public function setValue(array $products, AttributeInterface $attribute, $data, $locale = null, $scope = null)
    {
        AttributeUtility::validateLocale($attribute, $locale);
        AttributeUtility::validateScope($attribute, $scope);

        if (!is_array($data)) {
            throw new \InvalidArgumentException('$data have to be an array');
        }

//        if (!array_key_exists('attribute', $data)) {
//            throw new \LogicException('Missing "attribute" key in array');
//        }
//
//        if (!array_key_exists('code', $data)) {
//            throw new \LogicException('Missing "code" key in array');
//        }
//
//        if (!array_key_exists('label', $data)) {
//            throw new \LogicException('Missing "label" key in array');
//        }
//
//        if (!is_array($data['label'])) {
//            throw new \LogicException('Invalid data type for the "label" key');
//        }

        $attributeOptions = [];

        foreach ($data as $attributeOption) {
            $attributeOptions[] = $this->em
                ->getRepository('AttributeOption')
                ->findOneBy(['code' => $attributeOption['code']]);
        }

        foreach ($products as $product) {
            $value = $product->getValue($attribute->getCode(), $locale, $scope);
            if (null === $value) {
                $value = $this->productBuilder->addProductValue($product, $attribute, $locale, $scope);
            }
            $value->setOptions(new ArrayCollection($attributeOptions));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports(AttributeInterface $attribute)
    {
        return in_array($attribute->getAttributeType(), $this->types);
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedTypes()
    {
        return $this->types;
    }
}
