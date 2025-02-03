<?php

namespace Arendach\RequestDatatableCriteria;

use Illuminate\Database\Eloquent\Builder;

/** @mixin RequestDatatableCriteria */
trait ApplySearch
{
    private function applySearch(): void
    {
        if (!$this->search) return;

        $this->model = $this->model->where(function (Builder $query) {
            $fieldsSearchable = $this->repository->getFieldsSearchable();

            foreach ($fieldsSearchable as $field => $condition) {
                // Якщо ключ числовий, поле передано без оператора
                if (is_numeric($field)) {
                    $field = $condition;
                    $condition = '=';
                }

                // Обробляємо умову
                switch (strtolower($condition)) {
                    case '=':
                        $query->orWhere($field, '=', $this->search);
                        break;

                    case 'like':
                        $query->orWhere($field, 'LIKE', '%' . $this->search . '%');
                        break;

                    case 'ilike':
                        $query->orWhereRaw("LOWER($field) LIKE LOWER(?)", ['%' . $this->search . '%']);
                        break;

                    case 'in':
                        $query->orWhereIn($field, explode(',', $this->search)); // Очікується список через кому
                        break;

                    case 'between':
                        $range = explode(',', $this->search);
                        if (count($range) === 2) {
                            $query->orWhereBetween($field, [$range[0], $range[1]]);
                        }
                        break;

                    default:
                        // Якщо оператор невідомий, використовуємо `LIKE`
                        $query->orWhere($field, 'LIKE', '%' . $this->search . '%');
                        break;
                }
            }
        });
    }
}