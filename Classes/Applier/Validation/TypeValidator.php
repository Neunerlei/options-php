<?php
/*
 * Copyright 2022 Martin Neundorfer (Neunerlei)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * Last modified: 2022.02.21 at 19:13
 */

declare(strict_types=1);


namespace Neunerlei\Options\Applier\Validation;


use Closure;
use Neunerlei\Options\Applier\Type\ValueTypes;

class TypeValidator
{
    /**
     * Checks if the given value can be described with at least one of the given data types
     *
     * @param          $value
     * @param   array  $types
     *
     * @return bool
     * @see ValueTypes
     */
    public function isTypeOf($value, array $types): bool
    {
        // Check if we can validate that type
        $typeString = strtolower(gettype($value));
        
        // @codeCoverageIgnoreStart
        // Fallback should there ever be a PHP type we have not mapped
        if (! isset(ValueTypes::STRING_TYPE_MAP[$typeString])) {
            return false;
        }
        // @codeCoverageIgnoreEnd
        
        $type = ValueTypes::STRING_TYPE_MAP[$typeString];
        
        // Simple lookup
        if (isset($types[$type])) {
            return true;
        }
        
        // Object lookup
        if ($type === ValueTypes::TYPE_OBJECT) {
            if (isset($types[get_class($value)])) {
                return true;
            }
            if (! empty(array_intersect(class_parents($value), array_keys($types)))) {
                return true;
            }
            if (! empty(array_intersect(class_implements($value), array_keys($types)))) {
                return true;
            }
            
            // Closure callable lookup
            if (isset($types[ValueTypes::TYPE_CALLABLE]) && $value instanceof Closure) {
                return true;
            }
            
            return false;
        }
        
        // Boolean lookup
        if ($type === ValueTypes::TYPE_BOOL) {
            if ($value === true && isset($types[ValueTypes::TYPE_TRUE])) {
                return true;
            }
            
            if ($value === false && isset($types[ValueTypes::TYPE_FALSE])) {
                return true;
            }
        }
        
        // Number lookup (Non-string)
        if (($type === ValueTypes::TYPE_INT || $type === ValueTypes::TYPE_FLOAT)
            && isset($types[ValueTypes::TYPE_NUMBER])) {
            return true;
        }
        
        // Numeric lookup (Potential String)
        if (isset($types[ValueTypes::TYPE_NUMERIC]) && is_numeric($value)) {
            return true;
        }
        
        // Callable lookup
        if (isset($types[ValueTypes::TYPE_CALLABLE]) && is_callable($value)) {
            return true;
        }
        
        // Nope...
        return false;
    }
}
