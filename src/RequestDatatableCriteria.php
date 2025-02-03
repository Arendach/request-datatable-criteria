<?php

namespace Arendach\RequestDatatableCriteria;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Prettus\Repository\Contracts\CriteriaInterface;
use Prettus\Repository\Contracts\RepositoryInterface;

class RequestDatatableCriteria implements CriteriaInterface
{
    use ApplyFilters;
    use ApplyOrder;
    use ApplyWith;
    use ApplySearch;

    protected Request $request;
    protected ?string $search;
    protected array $filters;
    protected ?string $sortColumn;
    protected ?string $sortDirection;
    protected ?string $with;
    protected ?string $withCount;
    protected ?string $searchJoin;
    protected RepositoryInterface $repository;
    protected $model;
    protected $original;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->search = $this->request->get('search');
        $this->filters = $this->request->get('filters', []);
        $this->sortColumn = $this->request->get('sortColumn');
        $this->sortDirection = $this->request->get('sortDirection');
        $this->with = $this->request->get('with');
        $this->withCount = $this->request->get('withCount');
        $this->searchJoin = $this->request->get('searchJoin', 'and');
    }

    /** @param Builder|Model $model * */
    public function apply($model, RepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->model = $model;
        $this->original = $model;

        $this->applySearch();

        $this->applyWith();

        $this->applyWithCount();

        $this->applyFilters();

        $this->applyOrder();

        return $this->model;
    }
}