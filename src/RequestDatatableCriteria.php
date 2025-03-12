<?php

namespace Arendach\RequestDatatableCriteria;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Prettus\Repository\Contracts\CriteriaInterface;
use Prettus\Repository\Contracts\RepositoryInterface;

class RequestDatatableCriteria implements CriteriaInterface
{
    protected Request $request;
    protected ?string $search;
    protected array $filters;
    protected ?string $sortColumn;
    protected ?string $sortDirection;
    protected ?string $with;
    protected ?string $withCount;
    protected RepositoryInterface $repository;
    protected Builder $builder;
    protected Model $model;
    protected array $joinedTables = [];

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->search = $request->get('search');
        $this->filters = $request->get('filters', []);
        $this->sortColumn = $request->get('sortColumn');
        $this->sortDirection = $request->get('sortDirection');
        $this->with = $request->get('with');
        $this->withCount = $request->get('withCount');
    }

    public function apply($model, RepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->model = $model->getModel();
        $this->builder = $model;

        $this->applySearch();
        $this->applyWith();
        $this->applyWithCount();
        $this->applyFilters();
        $this->applyOrder();

        return $this->builder;
    }

    protected function applyOrder(): void
    {
        if (!$this->sortColumn || !$this->sortDirection) {
            return;
        }

        $sortParts = explode('.', $this->sortColumn);

        if (count($sortParts) === 1) {
            $this->builder->orderBy($this->sortColumn, $this->sortDirection);
        } else {
            $this->applyRelationOrder($sortParts, $this->sortDirection);
        }
    }

    private function applyRelationOrder(array $sortParts, string $direction): void
    {
        $relatedTable = $this->resolveRelation($sortParts);
        if ($relatedTable) {
            $this->builder->orderBy("$relatedTable.".end($sortParts), $direction);
        }
    }

    protected function applyFilters(): void
    {
        $this->builder->where(function ($query) {
            foreach ($this->filters as $filter) {
                $fieldParts = explode('.', $filter['field']);
                $column = array_pop($fieldParts);

                if (count($fieldParts) === 0) {
                    $this->applyBasicFilter($query, $column, $filter['condition'], $filter['value']);
                } else {
                    $this->applyRelationFilter($query, [...$fieldParts, $column], $filter['condition'], $filter['value']);
                }
            }
        });
    }

    protected function applyBasicFilter(Builder $query, string $field, string $condition, mixed $value): void
    {
        $table = $this->model->getTable();
        $fullField = "$table.$field";

        $castType = $this->getCastType($this->model, $field);

        $value = $this->convertValue($value, $condition, $castType);

        $this->applyCondition($query, $fullField, $condition, $value);
    }

    private function applyRelationFilter(Builder $query, array $fieldParts, string $condition, mixed $value): void
    {
        $column = array_pop($fieldParts);
        $relatedTable = $this->resolveRelation([...$fieldParts, $column]);

        if (!$relatedTable) {
            return;
        }

        $relatedModel = $this->getRelationInstance($this->model, $fieldParts[0])?->getRelated();
        foreach (array_slice($fieldParts, 1) as $relation) {
            $relatedModel = $this->getRelationInstance($relatedModel, $relation)?->getRelated();
        }

        if (!$relatedModel) {
            return;
        }

        $castType = $this->getCastType($relatedModel, $column);
        $value = $this->convertValue($value, $condition, $castType);

        $this->applyCondition($query, "$relatedTable.$column", $condition, $value);
    }

    private function getCastType(Model $model, string $column): ?string
    {
        $casts = $model->getCasts();
        return $casts[$column] ?? null;
    }

    private function convertValue(mixed $value, string $condition, ?string $castType): mixed
    {
        return ConditionHelper::getValueByCondition($value, $condition, $castType);
    }

    private function resolveRelation(array $fieldParts): ?string
    {
        $column = array_pop($fieldParts);

        if (empty($fieldParts)) {
            return null;
        }

        $relation = array_shift($fieldParts);
        $relationInstance = $this->getRelationInstance($this->model, $relation);

        if (!$relationInstance) {
            return null;
        }

        $previousTable = $this->model->getTable();
        $relatedModel = $relationInstance->getRelated();
        $relatedTable = $relatedModel->getTable();

        $this->addJoinIfNotExists($previousTable, $relatedTable, $relationInstance->getForeignKeyName());

        foreach ($fieldParts as $nextRelation) {
            $relationInstance = $this->getRelationInstance($relatedModel, $nextRelation);
            if (!$relationInstance) {
                return null;
            }

            $previousTable = $relatedTable;
            $relatedModel = $relationInstance->getRelated();
            $relatedTable = $relatedModel->getTable();

            $this->addJoinIfNotExists($previousTable, $relatedTable, $relationInstance->getForeignKeyName());
        }

        return $relatedTable;
    }

    private function getRelationInstance(Model $model, string $relation)
    {
        return method_exists($model, $relation) ? $model->{$relation}() : null;
    }

    private function addJoinIfNotExists(string $previousTable, string $relatedTable, string $foreignKey): void
    {
        if (!in_array($relatedTable, $this->joinedTables)) {
            $this->builder->leftJoin($relatedTable, "$previousTable.$foreignKey", '=', "$relatedTable.id");
            $this->joinedTables[] = $relatedTable;
        }
    }

    private function applyCondition(Builder $query, string $field, string $condition, mixed $value): void
    {
        match ($condition) {
            ConditionHelper::IS_EMPTY => $query->whereNull($field),
            ConditionHelper::IS_NOT_EMPTY => $query->whereNotNull($field),
            ConditionHelper::BETWEEN => $query->whereBetween($field, $value),
            ConditionHelper::IN => $query->whereIn($field, $value),
            default => $query->where(
                $field,
                ConditionHelper::getMysqlCondition($condition),
                ConditionHelper::getValueByCondition($value, $condition)
            ),
        };
    }

    protected function applySearch(): void
    {
        if (!$this->search) {
            return;
        }

        $this->builder->where(function (Builder $query) {
            foreach ($this->repository->getFieldsSearchable() as $field => $condition) {
                if (is_numeric($field)) {
                    $field = $condition;
                    $condition = '=';
                }

                $this->applyCondition($query, $field, $condition, $this->search);
            }
        });
    }

    protected function applyWith(): void
    {
        if ($this->with) {
            $this->builder->with(explode(';', $this->with));
        }
    }

    protected function applyWithCount(): void
    {
        if ($this->withCount) {
            $this->builder->withCount(explode(';', $this->withCount));
        }
    }
}
