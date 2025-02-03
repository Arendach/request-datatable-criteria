<?php

namespace Arendach\RequestDatatableCriteria;

use Illuminate\Database\Eloquent\Builder;

/** @mixin RequestDatatableCriteria */
trait ApplyFilters
{
    protected function applyFilters(): void
    {
        $this->model = $this->model->where(function ($query) {
            /** @var Builder $query */

            foreach ($this->filters as $filter) {
                $field = $filter['field'];
                $condition = $filter['condition'];
                $value = $filter['value'];

                $cast = $this->original->getCasts()[$field] ?? null;

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

    /**
     * Додає фільтрацію по вкладених зв’язках.
     */
    private function applyRelationFilter(Builder $query, array $fieldParts, string $condition, mixed $value): void
    {
        $relation = array_shift($fieldParts); // Перше значення - це назва відносини (наприклад, 'author')
        $column = array_pop($fieldParts); // Останнє значення - це поле для фільтрації (наприклад, 'is_europe')

        if (!method_exists($this->original, $relation)) {
            return; // Якщо зв’язок не знайдено - виходимо
        }

        // Починаємо з першого рівня зв’язку
        $relationInstance = $this->original->{$relation}();
        $previousTable = $this->original->getTable();
        $previousKey = $relationInstance->getForeignKeyName();
        $relatedModel = $relationInstance->getRelated();

        // Проходимо всі вкладені зв’язки
        foreach ($fieldParts as $nextRelation) {
            if (!method_exists($relatedModel, $nextRelation)) {
                return;
            }

            $relationInstance = $relatedModel->{$nextRelation}();
            $relatedModel = $relationInstance->getRelated();
            $relatedTable = $relatedModel->getTable();
            $foreignKey = $relationInstance->getForeignKeyName();

            // Додаємо LEFT JOIN між попередньою і поточною таблицею
            $this->model = $this->model->leftJoin(
                $relatedTable,
                "$previousTable.$previousKey",
                '=',
                "$relatedTable.id"
            );

            // Оновлюємо змінні для наступного кроку
            $previousTable = $relatedTable;
            $previousKey = $foreignKey;
        }

        if ($condition === ConditionHelper::IS_EMPTY) {
            $query->whereNull("$previousTable.$column");
            return;
        } elseif ($condition === ConditionHelper::IS_NOT_EMPTY) {
            $query->whereNotNull("$previousTable.$column");
            return;
        }

        // Отримуємо casts з кінцевої моделі
        $casts = $relatedModel->getCasts();
        $castType = $casts[$column] ?? null;

        // Приводимо значення до правильного типу
        $value = ConditionHelper::getValueByCondition($value, $condition, $castType);

        // Додаємо фільтр
        $query->where("$previousTable.$column", ConditionHelper::getMysqlCondition($condition), $value);
    }
}