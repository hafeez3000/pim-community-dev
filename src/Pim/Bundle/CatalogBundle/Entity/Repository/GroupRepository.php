<?php

namespace Pim\Bundle\CatalogBundle\Entity\Repository;

use Pim\Bundle\CatalogBundle\Doctrine\ReferableEntityRepository;
use Pim\Bundle\CatalogBundle\Entity\GroupType;
use Pim\Bundle\CatalogBundle\Model\AbstractAttribute;

/**
 * Group repository
 *
 * @author    Nicolas Dupont <nicolas@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class GroupRepository extends ReferableEntityRepository
{
    /**
     * Get ordered groups associative array id to label
     * @param GroupType $type
     *
     * @return array
     */
    public function getChoicesByType(GroupType $type)
    {
        $groups = $this->getGroupsByType($type);

        $choices = array();
        foreach ($groups as $group) {
            $choices[$group->getId()] = $group->getCode();
        }

        return $choices;
    }

    /**
     * Get groups
     *
     * @return array
     */
    public function getChoices()
    {
        $groups = $this
            ->createQueryBuilder($this->getAlias())
            ->addOrderBy($this->getAlias() .'.code', 'ASC')
            ->getQuery()
            ->getResult();

        $choices = array();
        foreach ($groups as $group) {
            $choices[$group->getId()] = $group->getLabel();
        }

        return $choices;
    }

    /**
     * Return the number of groups containing the provided attribute
     * @param AbstractAttribute $attribute
     *
     * @return interger
     */
    public function countVariantGroupAxis(AbstractAttribute $attribute)
    {
        $qb = $this->createQueryBuilder('g');

        return $qb
            ->select('count(g.id)')
            ->join('g.attributes', 'attributes')
            ->where(
                $qb->expr()->in('attributes', ':attribute')
            )
            ->setParameter('attribute', $attribute)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get ordered groups by type
     *
     * @param GroupType $type
     *
     * @return array
     */
    protected function getGroupsByType(GroupType $type)
    {
        return $this
            ->getGroupsByTypeQB($type)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get ordered groups query builder
     *
     * @param GroupType $type
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    protected function getGroupsByTypeQB(GroupType $type)
    {
        $alias = $this->getAlias();

        return $this->createQueryBuilder($alias)
            ->where($alias.'.type = :groupType')
            ->addOrderBy($alias.'.code', 'ASC')
            ->setParameter('groupType', $type);
    }

    /**
     * @return QueryBuilder
     */
    public function createDatagridQueryBuilder()
    {
        $qb = $this->createQueryBuilder('g');

        $groupLabelExpr = '(CASE WHEN translation.label IS NULL THEN g.code ELSE translation.label END)';
        $typeLabelExpr = '(CASE WHEN typTrans.label IS NULL THEN typ.code ELSE typTrans.label END)';
        $typeExpr = $qb->expr()->in('type.id', ':groupTypes');

        $qb
            ->addSelect(sprintf('%s AS groupLabel', $groupLabelExpr))
            ->addSelect(sprintf('%s AS typeLabel', $typeLabelExpr))
            ->addSelect('translation.label');

        $qb
            ->leftJoin('g.translations', 'translation', 'WITH', 'translation.locale = :localeCode')
            ->leftJoin('g.type', 'typ')
            ->leftJoin('typ.translations', 'typTrans', 'WITH', 'typTrans.locale = :localeCode')
            ->leftJoin('g.attributes', 'attribute')
            ->innerJoin('g.type', 'type', 'WITH', $typeExpr);

        $qb->groupBy('g');

        return $qb;
    }

    /**
     * @return QueryBuilder
     */
    public function createAssociationDatagridQueryBuilder()
    {
        $qb = $this->createQueryBuilder('g');

        $groupLabelExpr = '(CASE WHEN translation.label IS NULL THEN g.code ELSE translation.label END)';
        $typeLabelExpr = '(CASE WHEN typTrans.label IS NULL THEN typ.code ELSE typTrans.label END)';

        $isCheckecExpr =
            'CASE WHEN (g.id IN (:associatedIds) OR g.id IN (:data_in)) AND g.id NOT IN (:data_not_in) ' .
            'THEN true ELSE false END';

        $isAssociatedExpr = 'CASE WHEN g.id IN (:associatedIds) THEN true ELSE false END';

        $qb
            ->addSelect(sprintf('%s AS groupLabel', $groupLabelExpr))
            ->addSelect(sprintf('%s AS typeLabel', $typeLabelExpr))
            ->addSelect('translation.label')
            ->addSelect($isCheckecExpr.' AS is_checked')
            ->addSelect($isAssociatedExpr.' AS is_associated');

        $qb
            ->leftJoin('g.translations', 'translation', 'WITH', 'translation.locale = :dataLocale')
            ->leftJoin('g.type', 'typ')
            ->leftJoin('typ.translations', 'typTrans', 'WITH', 'typTrans.locale = :dataLocale');

        return $qb;
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions($dataLocale, $collectionId = null, $search = '', array $options = array())
    {
        $qb = $this->createQueryBuilder('o')
            ->select('o.id as id, COALESCE(t.label, CONCAT(\'[\', o.code, \']\')) as text')
            ->leftJoin('o.translations', 't', 'WITH', 't.locale=:locale')
            ->addOrderBy('text', 'ASC')
            ->setParameter('locale', $dataLocale);

        if ($search) {
            $qb->andWhere('t.label like :search OR o.code LIKE :search')
                ->setParameter('search', "%$search%");
        }

        if (isset($options['ids'])) {
            $qb
                ->andWhere(
                    $qb->expr()->in('o.id', ':ids')
                )
                ->setParameter('ids', $options['ids']);
        }

        if (isset($options['limit']) && isset($options['page'])) {
            $qb->setFirstResult($options['limit'] * ($options['page'] - 1))
                ->setMaxResults($options['limit']);
        }

        $results = $qb->getQuery()->getArrayResult();

        return array(
            'results' => $results
        );
    }

    /**
     * @return string
     */
    protected function getAlias()
    {
        return 'ProductGroup';
    }
}
