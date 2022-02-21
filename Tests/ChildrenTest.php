<?php
/*
 * Copyright 2022 LABOR.digital
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
 * Last modified: 2022.02.21 at 15:33
 */

declare(strict_types=1);


namespace Neunerlei\Options\Tests;


use Neunerlei\Options\Exception\InvalidOptionDefinitionException;
use Neunerlei\Options\Exception\OptionValidationException;
use Neunerlei\Options\Options;
use PHPUnit\Framework\TestCase;

class ChildrenTest extends TestCase
{
    public function testAssociativeChildren(): void
    {
        $c          = 0;
        $expected   = [
            'foo' => 123,
            'bar' => [
                'baz'    => 123,
                'barBaz' => [
                    'fooBar' => true,
                ],
            ],
            'baz' => [
                'bar' => 123,
                'foo' => false,
            ],
        ];
        $initial    = [
            'foo' => 123,
            'baz' => ['bar' => 123],
        ];
        $definition = [
            'foo' => [
                'default'   => 123,
                'validator' => function () use (&$c) {
                    $c++; // 1

                    return true;
                },
            ],
            'bar' => [
                'type'      => 'array',
                'default'   => [],
                'validator' => function () use (&$c) {
                    $c++; // 2

                    return true;
                },
                'children'  => [
                    'baz'    => 123,
                    'barBaz' => [
                        'type'      => 'array',
                        'default'   => [],
                        'validator' => function () use (&$c) {
                            $c++; // 3

                            return true;
                        },
                        'children'  => [
                            'fooBar' => [
                                'type'      => 'bool',
                                'default'   => true,
                                'validator' => function () use (&$c) {
                                    $c++; // 4

                                    return true;
                                },
                            ],
                        ],
                    ],
                ],
            ],
            'baz' => [
                'type'      => 'array',
                'validator' => function () use (&$c) {
                    $c++; // 5

                    return true;
                },
                'children'  => [
                    'bar' => [
                        'type'      => 'number',
                        'validator' => function () use (&$c) {
                            $c++; // 6

                            return true;
                        },
                    ],
                    'foo' => false,
                ],
            ],
        ];
        $v          = Options::make($initial, $definition);
        self::assertEquals($expected, $v);
        self::assertEquals(6, $c, 'Failed to run all validators on child list');
    }

    public function testSequentialChildren(): void
    {
        $expected   = [
            'foo'  => 'string',
            'list' => [
                [
                    'foo' => true,
                    'bar' => '123',
                ],
                [
                    'foo' => false,
                    'bar' => 'asdf',
                ],
                [
                    'foo' => true,
                    'bar' => 'bar',
                ],
            ],
        ];
        $initial    = [
            'list' => [
                ['foo', 'bar' => '123'],
                ['bar' => 'asdf'],
                ['foo' => true],
            ],
        ];
        $definition = [
            'foo'  => 'string',
            'list' => [
                'children' => [
                    '*' => [
                        'foo' => [
                            'type'    => 'boolean',
                            'default' => false,
                        ],
                        'bar' => [
                            'type'    => 'string',
                            'default' => 'bar',
                        ],
                    ],
                ],
            ],
        ];
        self::assertEquals($expected, Options::make($initial, $definition));
    }

    public function testSequentialChildrenNonArrayFail(): void
    {
        $this->expectException(OptionValidationException::class);
        Options::make([
            'options' => [
                [
                    'foo' => true,
                ],
                '',
            ],
        ], [
            'options' => [
                'type'     => 'array',
                'children' => [
                    '*' => [
                        'foo' => true,
                    ],
                ],
            ],
        ]);
    }

    public function provideTestValueListChildrenData(): array
    {
        return [
            [
                [
                    'foo' => [
                        'a',
                        'b',
                        'c',
                    ],
                ],
                [
                    'foo' => [
                        'type'     => 'array',
                        'children' => [
                            '#' => [
                                'type'      => 'string',
                                'validator' => '~^[a-c]$~',
                            ],
                        ],
                    ],
                ],
                null,
            ],
            // Not really the way it was designed to work, but it should work nevertheless
            [
                [
                    'foo' => [
                        [
                            'foo' => 1,
                        ],
                        [
                            'bar' => 2,
                        ],
                        [
                            'baz' => 3,
                        ],
                    ],
                ],
                [
                    'foo' => [
                        'type'     => 'array',
                        'children' => [
                            '#' => [
                                'type'     => 'array',
                                'children' => [
                                    'foo' => 1,
                                    'bar' => 2,
                                    'baz' => 3,
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'foo' => [
                        [
                            'foo' => 1,
                            'bar' => 2,
                            'baz' => 3,
                        ],
                        [
                            'foo' => 1,
                            'bar' => 2,
                            'baz' => 3,
                        ],
                        [
                            'foo' => 1,
                            'bar' => 2,
                            'baz' => 3,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideTestValueListChildrenData
     */
    public function testValueListChildren(array $data, array $definition, ?array $expected): void
    {
        static::assertEquals($expected ?? $data, Options::make($data, $definition));
    }

    public function testInvalidChildDefinition(): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);
        $this->expectExceptionMessage('Definition error at: "foo"; The given children definition must be an array');
        Options::make([], [
            'foo' => [
                'children' => false,
            ],
        ]);
    }
}
