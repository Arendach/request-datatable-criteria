<?php

namespace Arendach\RequestDatatableCriteria;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
            $this->model = $this->model->orderBy($this->sortColumn, $this->sortDirection);
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
        $relation = $sortParts[0]; // Перше значення - це назва відносини (наприклад, 'author')
        $column = end($sortParts); // Останнє значення - це поле для сортування (наприклад, 'name')

        // Отримуємо визначення зв'язку в моделі
        $relationInstance = $this->original->{$relation}();

        if ($relationInstance instanceof BelongsTo) {
            // Для зв'язків типу BelongsTo (наприклад, author.name)
            $foreignKey = $relationInstance->getForeignKeyName(); // отримуємо поле зовнішнього ключа
            $relatedTable = $relationInstance->getRelated()->getTable(); // отримуємо ім'я таблиці зв'язку

            // Додаємо join для таблиці зв'язку
            $this->model = $this->model
                ->leftJoin($relatedTable, $this->original->getTable() . '.' . $foreignKey, '=', "$relatedTable.id")
                ->orderBy("$relatedTable.$column", $direction);
        } elseif ($relationInstance instanceof HasOne) {
            // Для зв'язків типу HasOne (наприклад, profile.city)
            $foreignKey = $relationInstance->getForeignKeyName();
            $relatedTable = $relationInstance->getRelated()->getTable();

            $this->model = $this->model
                ->leftJoin($relatedTable, "$relatedTable.$foreignKey", '=', $this->original->getTable() . '.id')
                ->orderBy("$relatedTable.$column", $direction);
        }
    }
}