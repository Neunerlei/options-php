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


namespace Neunerlei\Options\Tests\Data;


use InvalidArgumentException;
use Neunerlei\Options\Tests\Fixture\FixtureClassA;
use Neunerlei\Options\Tests\Fixture\FixtureExtendedClassA;

class TypeValidationDataProvider
{
    protected static $generators;
    
    protected const TYPE_MAP
        = [
            'boolean' => 'bool',
            'integer' => 'int',
            'double' => 'float',
        ];
    
    protected const IGNORE_OTHER_TYPES_FOR_INVALID_DATA
        = [
            'bool' => ['true', 'false'],
            'true' => ['bool'],
            'false' => ['bool'],
            'int' => ['number', 'numeric'],
            'float' => ['number', 'numeric'],
            'number' => ['float', 'int', 'numeric'],
            'numeric' => ['float', 'int', 'number'],
            'object' => ['callable'],
            'callable' => ['object'],
        ];
    
    protected const IGNORE_VALUES_FOR_INVALID_DATA
        = [
            'string' => ['123', '12.123', '-1', '-12.12', 'trim'],
            'array' => [[FixtureClassA::class, 'foo']],
        ];
    
    protected const ADDITIONAL_INVALID_DATA
        = [
            'number' => ['123', '12.123', '456,123'],
        ];
    
    
    public static function getValidDataFor(string $type): array
    {
        return static::makeData(static::TYPE_MAP[$type] ?? $type);
    }
    
    public static function getInvalidDataFor(string $type): array
    {
        $type = static::TYPE_MAP[$type] ?? $type;
        $data = static::makeData(null, array_merge(
            [$type],
            static::IGNORE_OTHER_TYPES_FOR_INVALID_DATA[$type] ?? []
        ));
        if (isset(static::ADDITIONAL_INVALID_DATA[$type])) {
            return array_merge($data, static::ADDITIONAL_INVALID_DATA[$type]);
        }
        
        if (isset(static::IGNORE_VALUES_FOR_INVALID_DATA[$type])) {
            $ignoredValues = static::IGNORE_VALUES_FOR_INVALID_DATA[$type];
            $data = array_filter($data, static function ($v) use ($ignoredValues) {
                return ! in_array($v, $ignoredValues, true);
            });
        }
        
        return $data;
    }
    
    /**
     * The generators are used to build a list of valid data for their registered "type".
     * We can use all values of other types to be considered "invalid".
     * Some types conflict on the "invalid" part, so we use IGNORE_FOR_INVALID_DATA to ignore some generators
     * for some types.
     *
     * @return \Closure[]
     */
    protected static function makeGenerators(): array
    {
        return static::$generators ?? (
            static::$generators = [
                'bool' => function (array &$data) {
                    $data[] = true;
                    $data[] = false;
                },
                'true' => function (array &$data) {
                    $data[] = true;
                },
                'false' => function (array &$data) {
                    $data[] = false;
                },
                'int' => function (array &$data) {
                    $data[] = 123;
                    $data[] = 546345;
                    $data[] = -123;
                    $data[] = 0;
                    $data[] = 12123;
                },
                'float' => function (array &$data) {
                    $data[] = 123.23;
                    $data[] = 12123.123;
                    $data[] = -123.123;
                    $data[] = 0.0;
                    $data[] = 23.23;
                },
                'string' => function (array &$data) {
                    $data[] = 'hello';
                    $data[] = 'hello world';
                    $data[] = '<html>foo bar!</html>';
                    $data[] = '';
                    $data[] = '123,212';
                },
                'array' => function (array &$data) {
                    $data[] = [];
                    $data[] = ['foo' => []];
                    $data[] = [123, 1231, 123];
                },
                'object' => function (array &$data) {
                    $data[] = (object)[];
                    $data[] = new FixtureClassA();
                    $data[] = new FixtureExtendedClassA();
                    $data[] = static function () { };
                },
                'resource' => function (array &$data) {
                    $data[] = fopen(__FILE__, 'rb');
                },
                'null' => function (array &$data) {
                    $data[] = null;
                },
                'number' => function (array &$data) {
                    $generators = static::makeGenerators();
                    $generators['int']($data);
                    $generators['float']($data);
                },
                'numeric' => function (array &$data) {
                    $generators = static::makeGenerators();
                    $generators['int']($data);
                    $generators['float']($data);
                    $data[] = '123';
                    $data[] = '12.123';
                    $data[] = '-1';
                    $data[] = '-12.12';
                },
                'callable' => function (array &$data) {
                    $data[] = static function () { };
                    $data[] = 'trim';
                    $data[] = [FixtureClassA::class, 'foo'];
                },
            ]);
    }
    
    protected static function makeData(?string $type, ?array $ignoredKeys = null): array
    {
        $data = [];
        $generators = static::makeGenerators();
        if (is_string($type)) {
            if (is_callable($generators[$type] ?? null)) {
                $generators[$type]($data);
            } else {
                throw new InvalidArgumentException('Invalid data type required: "' . $type . '"');
            }
        } else {
            $ignoredKeys = $ignoredKeys ?? [];
            foreach ($generators as $_type => $generator) {
                if (in_array($_type, $ignoredKeys, true)) {
                    continue;
                }
                $generator($data);
            }
        }
        
        return $data;
    }
}
