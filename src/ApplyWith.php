<?php

namespace Arendach\RequestDatatableCriteria;

/** @mixin RequestDatatableCriteria */
trait ApplyWith
{
    protected function applyWith(): void
    {
        if (!$this->with) return;

        $with = explode(';', $this->with);
        $this->model = $this->model->with($with);
    }

    protected function applyWithCount(): void
    {
        if (!$this->withCount) return;

        $withCount = explode(';', $this->withCount);
        $this->model = $this->model->withCount($withCount);
    }
}