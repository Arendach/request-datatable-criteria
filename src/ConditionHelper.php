<?php

namespace Arendach\RequestDatatableCriteria;

class ConditionHelper
{
    const CONTAIN = 'contain';
    const NOT_CONTAIN = 'not_contain';
    const EQUAL = 'equal';
    const NOT_EQUAL = 'not_equal';
    const START_WITH = 'start_with';
    const END_WITH = 'end_with';
    const GREATER_THAN = 'greater_than';
    const GREATER_THAN_EQUAL = 'greater_than_equal';
    const LESS_THAN = 'less_than';
    const LESS_THAN_EQUAL = 'less_than_equal';
    const IS_EMPTY = 'is_empty';
    const IS_NOT_EMPTY = 'is_not_empty';
    const BETWEEN = 'between';
    const IN = 'in';

    // const NOT_IN = 'not_in';
    // const NOT_BETWEEN = 'not_between';

    public static function getValueByCondition($value, $condition, ?string $cast = null): mixed
    {
        if (in_array($condition, [self::IN, /*self::NOT_IN*/])) {
            if (is_string($value)) {
                return explode(',', $value);
            }

            return $value;
        }

        $value = self::getValueByCast($value, $cast);

        return match ($condition) {
            self::CONTAIN, self::NOT_CONTAIN => "%$value%",
            self::START_WITH => "$value%",
            self::END_WITH => "%$value",
            default => $value,
        };
    }

    public static function getMysqlCondition($condition): string
    {
        return match ($condition) {
            self::EQUAL => '=',
            self::NOT_EQUAL => '!=',
            self::CONTAIN, self::START_WITH, self::END_WITH => 'like',
            self::NOT_CONTAIN => 'not like',
            self::GREATER_THAN => '>',
            self::GREATER_THAN_EQUAL => '>=',
            self::LESS_THAN => '<',
            self::LESS_THAN_EQUAL => '<=',
        };
    }

    private static function getValueByCast(mixed $value, ?string $cast = null): mixed
    {
        if ($cast === 'bool' || $cast === 'boolean') {
            return match ($value) {
                'true', 'on', '1', 1, true => true,
                'false', 'off', '0', 0, false => false,
            };
        }

        if ($cast === 'int') {
            return (int) $value;
        }

        if ($cast === 'float') {
            return (float) $value;
        }

        if ($cast === 'string') {
            return (string) $value;
        }

        return $value;
    }
}