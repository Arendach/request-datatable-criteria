<?php

namespace Arendach\RequestDatatableCriteria;

use Illuminate\Database\Eloquent\Builder;

/** @mixin RequestDatatableCriteria */
trait ApplyFilters
{
    protected function applyFilters(): void
    {
        $this->builder = $this->builder->where(function ($query) {
            /** @var Builder $query */

            foreach ($this->filters as $filter) {
                $field = $filter['field'];
                $condition = $filter['condition'];
                $value = $filter['value'];

                $cast = $this->builder->getModel()->getCasts()[$field] ?? null;

                $fieldParts = explode('.', $field);

                if (count($fieldParts) === 1) {
                    // Фільтрація по полях основної таблиці
                    $this->applyBasicFilter($query, $field, $condition, $value, $cast);
                } else {
                    // Фільтрація по вкладених зв’язках
                    $this->applyRelationFilter($query, $fieldParts, $condition, $value);
                }
            }
        });
    }

    /** Застосовує базовий фільтр до основної таблиці. */
    private function applyBasicFilter(Builder $query, string $field, string $condition, mixed $value, ?string $cast): void
    {
        $table = $this->builder->getModel()->getTable(); // Основна таблиця

        // Додаємо назву таблиці перед полем
        $fullField = "$table.$field";

        if ($condition === ConditionHelper::IS_EMPTY) {
            $query->whereNull($fullField);
        } elseif ($condition === ConditionHelper::IS_NOT_EMPTY) {
            $query->whereNotNull($fullField);
        } elseif ($condition === ConditionHelper::BETWEEN) {
            $query->whereBetween($fullField, $value);
        } elseif ($condition === ConditionHelper::IN) {
            $query->whereIn($fullField, $value);
        } else {
            $query->where(
                $fullField,
                ConditionHelper::getMysqlCondition($condition),
                ConditionHelper::getValueByCondition($value, $condition, $cast)
            );
        }
    }


    /**
     * Додає фільтрацію по вкладених зв’язках.
     */
    private function applyRelationFilter(Builder $query, array $fieldParts, string $condition, mixed $value): void
    {
        $relation = array_shift($fieldParts); // Перше значення - це назва відносини (наприклад, 'author')
        $column = array_pop($fieldParts); // Останнє значення - це поле для фільтрації (наприклад, 'name')

        if (!method_exists($this->builder->getModel(), $relation)) {
            return; // Якщо зв’язок не знайдено - виходимо
        }

        // Починаємо з першого рівня зв’язку
        $relationInstance = $this->builder->getModel()->{$relation}();
        $previousTable = $this->builder->getModel()->getTable();
        $previousKey = $relationInstance->getForeignKeyName();
        $relatedModel = $relationInstance->getRelated();
        $relatedTable = $relatedModel->getTable();

        // Якщо таблиця ще не приєднана, додаємо LEFT JOIN
        if (!in_array($relatedTable, $this->joinedTables)) {
            $this->builder = $this->builder->leftJoin(
                $relatedTable,
                "$previousTable.$previousKey",
                '=',
                "$relatedTable.id"
            );

            $this->joinedTables[] = $relatedTable; // Додаємо в список приєднаних таблиць
        }

        // Проходимо всі вкладені зв’язки (наприклад, `author.country`)
        foreach ($fieldParts as $nextRelation) {
            if (!method_exists($relatedModel, $nextRelation)) {
                return;
            }

            $relationInstance = $relatedModel->{$nextRelation}();
            $relatedModel = $relationInstance->getRelated();
            $nextTable = $relatedModel->getTable();
            $nextKey = $relationInstance->getForeignKeyName();

            // Якщо таблиця ще не приєднана, додаємо LEFT JOIN
            if (!in_array($nextTable, $this->joinedTables)) {
                $this->builder = $this->builder->leftJoin(
                    $nextTable,
                    "$relatedTable.$nextKey",
                    '=',
                    "$nextTable.id"
                );

                $this->joinedTables[] = $nextTable; // Додаємо в список приєднаних таблиць
            }

            // Оновлюємо змінні для наступного кроку
            $previousTable = $nextTable;
            $relatedTable = $nextTable;
            $previousKey = $nextKey;
        }

        if ($condition === ConditionHelper::IS_EMPTY) {
            $query->whereNull("$relatedTable.$column");
            return;
        } elseif ($condition === ConditionHelper::IS_NOT_EMPTY) {
            $query->whereNotNull("$relatedTable.$column");
            return;
        } elseif ($condition === ConditionHelper::BETWEEN) {
            $query->whereBetween("$relatedTable.$column", $value);
            return;
        } elseif ($condition === ConditionHelper::IN) {
            $query->whereIn("$relatedTable.$column", $value);
            return;
        }

        // Отримуємо casts з кінцевої моделі
        $casts = $relatedModel->getCasts();
        $castType = $casts[$column] ?? null;

        // Приводимо значення до правильного типу
        $value = ConditionHelper::getValueByCondition($value, $condition, $castType);

        // Додаємо фільтр
        $query->where("$relatedTable.$column", ConditionHelper::getMysqlCondition($condition), $value);
    }
}