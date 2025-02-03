<?php

namespace Arendach\RequestDatatableCriteria;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Prettus\Repository\Contracts\CriteriaInterface;
use Prettus\Repository\Contracts\RepositoryInterface;

class RequestDatatableCriteria implements CriteriaInterface
{
    /** @var Request */
    protected $request;

    private ?string $search;
    private array $filters;
    private ?string $sortColumn;
    private ?string $sortDirection;
    private ?string $with;
    private ?string $withCount;
    private ?string $searchJoin;
    private ?string $select;
    private RepositoryInterface $repository;
    private $model;
    private array $casts = [];
    private ?int $limit;

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
        $this->select = $this->request->get('select');
        $this->limit = $this->request->get('limit', 10);
    }

    /** @param Builder|Model $model * */
    public function apply($model, RepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->model = $model;

        $this->casts = $this->model->getCasts();

        $this->applySearch();

        $this->applyWith();

        $this->applyWithCount();

        $this->applyFilters();

        $this->applyOrder();

        $this->applySelect();

        $this->applyLimit();

        return $this->model;
    }

    // add ->where condition
    private function applySearch(): void
    {
        $this->model->where(function (Builder $query) {
            $fieldsSearchable = $this->repository->getFieldsSearchable();

            foreach ($fieldsSearchable as $field) {
                $query->where($field, 'like', '%' . $this->search . '%');
            }
        });
    }

    // add ->with(relations)
    private function applyWith(): void
    {
        if (!$this->with) return;

        $with = explode(';', $this->with);
        $this->model = $this->model->with($with);
    }

    // add ->withCount(relations)
    private function applyWithCount(): void
    {
        if (!$this->withCount) return;

        $withCount = explode(';', $this->withCount);
        $this->model = $this->model->withCount($withCount);
    }

    // add orderBy(orderColumn, orderDirection)
    private function applyOrder(): void
    {
        if (!$this->sortColumn || !$this->sortDirection) return;

        $this->model = $this->model->orderBy($this->sortColumn, $this->sortDirection);
    }

    // add ->select(columns)
    private function applySelect(): void
    {
        if ($this->select === null) return;

        $this->model->select($this->select);
    }

    private function applyFilters(): void
    {
        $this->model = $this->model->where(function ($query) {
            /** @var Builder $query */

            foreach ($this->filters as $filter) {
                $field = $filter['field'];
                $condition = $filter['condition'];
                $value = $filter['value'];
                $cast = $this->casts[$field] ?? null;

                if ($condition === ConditionHelper::IS_EMPTY) {
                    $query->whereNull($field);
                } elseif ($condition === ConditionHelper::IS_NOT_EMPTY) {
                    $query->whereNotNull($field);
                } else {
                    $query->where(
                        $field,
                        ConditionHelper::getMysqlCondition($condition),
                        ConditionHelper::getValueByCondition($value, $condition, $cast)
                    );
                }
            }
        });
    }

    private function applyLimit(): void
    {
        if (!$this->limit) return;

        $this->model = $this->model->limit($this->limit);
    }
}