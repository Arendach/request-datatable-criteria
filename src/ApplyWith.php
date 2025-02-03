<?php

namespace Arendach\RequestDatatableCriteria;

/** @mixin RequestDatatableCriteria */
trait ApplyWith
{
    protected function applyWith(): void
    {
        if (!$this->with) return;

        $with = explode(';', $this->with);
        $this->builder = $this->builder->with($with);
    }

    protected function applyWithCount(): void
    {
        if (!$this->withCount) return;

        $withCount = explode(';', $this->withCount);
        $this->builder = $this->builder->withCount($withCount);
    }
}