<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Suggest;

use Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class ProductSuggestGateway implements ProductSuggestGatewayInterface
{
    /**
     * @var SalesChannelRepository
     */
    private $repository;

    /**
     * @var ProductSearchTermInterpreterInterface
     */
    private $interpreter;

    public function __construct(
        SalesChannelRepository $repository,
        ProductSearchTermInterpreterInterface $interpreter
    ) {
        $this->repository = $repository;
        $this->interpreter = $interpreter;
    }

    public function suggest(Request $request, SalesChannelContext $context): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->setLimit(15);

        $term = trim((string) $request->query->get('search'));

        if (empty($term)) {
            throw new MissingRequestParameterException('search');
        }

        $pattern = $this->interpreter->interpret(
            $term,
            $context->getContext()
        );

        foreach ($pattern->getTerms() as $searchTerm) {
            $criteria->addQuery(
                new ScoreQuery(
                    new EqualsFilter('product.searchKeywords.keyword', $searchTerm->getTerm()),
                    $searchTerm->getScore(),
                    'product.searchKeywords.ranking'
                )
            );
        }
        $criteria->addQuery(
            new ScoreQuery(
                new ContainsFilter('product.searchKeywords.keyword', $pattern->getOriginal()->getTerm()),
                $pattern->getOriginal()->getScore(),
                'product.searchKeywords.ranking'
            )
        );

        $criteria->addFilter(new EqualsAnyFilter('product.searchKeywords.keyword', array_values($pattern->getAllTerms())));
        $criteria->addFilter(new EqualsFilter('product.searchKeywords.languageId', $context->getContext()->getLanguageId()));

        return $this->repository->search($criteria, $context);
    }
}
