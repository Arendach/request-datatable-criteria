<?php

namespace Arendach\RequestDatatableCriteria;

/** @mixin RequestDatatableCriteria */
trait ApplyOrder
{
    // add orderBy(orderColumn, orderDirection)
    protected function applyOrder(): void
    {
        if (!$this->sortColumn || !$this->sortDirection) return;

        // Розділяємо колонку на частини (наприклад, author.name -> ['author', 'name'])
        $sortParts = explode('.', $this->sortColumn);

        if (count($sortParts) === 1) {
            // Якщо сортування йде по основній таблиці
            $this->builder = $this->builder->orderBy($this->sortColumn, $this->sortDirection);
        } else {
            // Обробка вкладених зв'язків
            $this->applyRelationOrder($sortParts, $this->sortDirection);
        }
    }

    /**
     * Додає сортування по вкладених відносинах.
     *
     * @param array $sortParts Масив з назвами відносин та поля (наприклад, ['author', 'name'])
     * @param string $direction Напрямок сортування (asc або desc)
     */
    private function applyRelationOrder(array $sortParts, string $direction): void
    {
        $relation = array_shift($sortParts); // Перше значення - це назва відносини (наприклад, 'author')
        $column = array_pop($sortParts); // Останнє значення - це поле для сортування (наприклад, 'is_europe')

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
            $this->joinedTables[] = $relatedTable;
        }

        // Обробка вкладених зв'язків (наприклад, author.country)
        foreach ($sortParts as $nextRelation) {
            if (!method_exists($relatedModel, $nextRelation)) {
                return;
            }

            $relationInstance = $relatedModel->{$nextRelation}();
            $relatedModel = $relationInstance->getRelated();
            $nextTable = $relatedModel->getTable();
            $nextKey = $relationInstance->getForeignKeyName();

            if (!in_array($nextTable, $this->joinedTables)) {
                $this->builder = $this->builder->leftJoin(
                    $nextTable,
                    "$relatedTable.$nextKey",
                    '=',
                    "$nextTable.id"
                );
                $this->joinedTables[] = $nextTable;
            }

            // Оновлюємо змінні
            $previousTable = $nextTable;
            $relatedTable = $nextTable;
            $previousKey = $nextKey;
        }

        // Додаємо правильне сортування (по останньому рівню вкладеності)
        $this->builder = $this->builder->orderBy("$relatedTable.$column", $direction);
    }

}