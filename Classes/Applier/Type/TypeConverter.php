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


namespace Neunerlei\Options\Applier\Type;


class TypeConverter
{
    /**
     * Converts a list of type definitions into an array or null if the given type list is empty
     *
     * @param $types
     *
     * @return array|null
     * @throws \Neunerlei\Options\Applier\Type\InvalidTypeException
     */
    public function convertList($types): ?array
    {
        if (! is_array($types)) {
            if (is_string($types)) {
                $types = [$types];
            } else {
                throw new InvalidTypeException(
                    'Type definitions have to be an array of strings, or a single string!');
            }
        }
        
        $out = [];
        foreach ($types as $type) {
            $out[$this->convertSingle($type)] = $type;
        }
        
        return empty($out) ? null : $out;
    }
    
    /**
     * Converts a single type definition into its numeric representation or keeps strings for classes
     *
     * @param $type
     *
     * @return int|string
     * @throws \Neunerlei\Options\Applier\Type\InvalidTypeException
     */
    public function convertSingle($type)
    {
        if (! is_string($type)) {
            throw new InvalidTypeException(
                'Type definitions have to be an array of strings, or a single string!');
        }
        
        /** @noinspection ProperNullCoalescingOperatorUsageInspection */
        return ValueTypes::STRING_TYPE_MAP[$type] ?? $type;
    }
}
