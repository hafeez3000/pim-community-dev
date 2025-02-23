<?php

namespace spec\Pim\Bundle\CatalogBundle\Doctrine\MongoDBODM\Filter;

use Doctrine\ODM\MongoDB\Query\Builder;
use PhpSpec\ObjectBehavior;

/**
 * @require Doctrine\ODM\MongoDB\Query\Builder
 */
class CompletenessFilterSpec extends ObjectBehavior
{
    function let(Builder $queryBuilder)
    {
        $this->beConstructedWith(['completeness'], ['=', '<']);
        $this->setQueryBuilder($queryBuilder);
    }

    function it_is_a_field_filter()
    {
        $this->shouldImplement('Pim\Bundle\CatalogBundle\Doctrine\Query\FieldFilterInterface');
    }

    function it_supports_operators()
    {
        $this->getOperators()->shouldReturn(['=', '<']);
        $this->supportsOperator('=')->shouldReturn(true);
        $this->supportsOperator('FAKE')->shouldReturn(false);
    }

    function it_adds_a_equals_filter_on_completeness_in_the_query(Builder $queryBuilder)
    {
        $queryBuilder->field('normalizedData.completenesses.mobile-en_US')->willReturn($queryBuilder);
        $queryBuilder->equals('100')->willReturn($queryBuilder);

        $this->addFieldFilter('completenesses', '=', '100', ['locale' => 'en_US', 'scope' => 'mobile']);
    }

    function it_adds_a_less_than_filter_on_completeness_in_the_query(Builder $queryBuilder)
    {
        $queryBuilder->field('normalizedData.completenesses.mobile-en_US')->willReturn($queryBuilder);
        $queryBuilder->lt('100')->willReturn($queryBuilder);

        $this->addFieldFilter('completenesses', '<', '100', ['locale' => 'en_US', 'scope' => 'mobile']);
    }

    function it_throws_an_exception_when_the_locale_and_scope_are_not_provided(Builder $queryBuilder)
    {
        $this
            ->shouldThrow('\InvalidArgumentException')
            ->duringAddFieldFilter('completenesses', '=', 100, []);
        $this
            ->shouldThrow('\InvalidArgumentException')
            ->duringAddFieldFilter('completenesses', '=', 100, ['locale' => null, 'scope' => 'ecommerce']);
        $this
            ->shouldThrow('\InvalidArgumentException')
            ->duringAddFieldFilter('completenesses', '=', 100, ['locale' => 'fr_FR', 'scope' => null]);
    }
}
