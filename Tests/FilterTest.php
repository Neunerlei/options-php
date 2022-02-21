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


namespace Neunerlei\Options\Tests;


use Neunerlei\Options\Applier\Context\Context;
use Neunerlei\Options\Applier\Node\Node;
use Neunerlei\Options\Applier\Type\ValueTypes;
use Neunerlei\Options\Exception\InvalidOptionDefinitionException;
use Neunerlei\Options\Options;
use PHPUnit\Framework\TestCase;

class FilterTest extends TestCase
{
    
    public function provideTestPreFilterData(): array
    {
        return [
            [
                ['foo' => '123'],
                ['foo' => 123],
                [
                    'foo' => [
                        'type' => 'string',
                        'preFilter' => function ($v) {
                            return (string)$v;
                        },
                    ],
                ],
            ],
            [
                ['foo' => 123],
                ['foo' => '123'],
                [
                    'foo' => [
                        'type' => 'int',
                        'preFilter' => function ($v) {
                            return (int)$v;
                        },
                    ],
                ],
            ],
        ];
    }
    
    /**
     * @dataProvider provideTestPreFilterData
     */
    public function testPreFilter(array $expected, array $data, array $definition): void
    {
        static::assertEquals($expected, Options::make($data, $definition));
    }
    
    public function testPreFilterParams(): void
    {
        $executed = false;
        Options::make(['foo' => '123'], [
            'foo' => [
                'type' => 'int',
                'preFilter' => function ($v, $k, $list, $node, $context) use (&$executed) {
                    $executed = true;
                    static::assertEquals('123', $v);
                    static::assertEquals('foo', $k);
                    static::assertEquals(['foo' => '123'], $list);
                    static::assertInstanceOf(Node::class, $node);
                    static::assertEquals([ValueTypes::TYPE_INT => 'int'], $node->types);
                    static::assertInstanceOf(Context::class, $context);
                    static::assertEquals(['foo'], $context->path);
                    
                    return (int)$v;
                },
            ],
        ]);
        self::assertTrue($executed);
    }
    
    public function testFailOnInvalidPreFilter(): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);
        $this->expectExceptionMessage('Definition error at: "foo"; The given preFilter is not callable');
        Options::make(['foo' => '123'], ['foo' => ['preFilter' => 'notExistingFunction__']]);
    }
    
    public function provideTestFilterData(): array
    {
        return [
            [
                ['foo' => '123'],
                ['foo' => 123],
                [
                    'foo' => [
                        'type' => 'int',
                        'filter' => function ($v) {
                            return (string)$v;
                        },
                    ],
                ],
            ],
            [
                ['foo' => 123],
                ['foo' => '123'],
                [
                    'foo' => [
                        'type' => 'string',
                        'filter' => function ($v) {
                            return (int)$v;
                        },
                    ],
                ],
            ],
        ];
    }
    
    /**
     * @dataProvider provideTestFilterData
     */
    public function testFilter(array $expected, array $data, array $definition): void
    {
        static::assertEquals($expected, Options::make($data, $definition));
    }
    
    public function testFilterParams(): void
    {
        $executed = false;
        Options::make(['foo' => '123'], [
            'foo' => [
                'type' => 'string',
                'filter' => function ($v, $k, $list, $node, $context) use (&$executed) {
                    $executed = true;
                    static::assertEquals('123', $v);
                    static::assertEquals('foo', $k);
                    static::assertEquals(['foo' => '123'], $list);
                    static::assertInstanceOf(Node::class, $node);
                    static::assertEquals([ValueTypes::TYPE_STRING => 'string'], $node->types);
                    static::assertInstanceOf(Context::class, $context);
                    static::assertEquals(['foo'], $context->path);
                    
                    return (int)$v;
                },
            ],
        ]);
        self::assertTrue($executed);
    }
    
    public function testFailOnInvalidFilter(): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);
        $this->expectExceptionMessage('Definition error at: "foo"; The given filter is not callable');
        Options::make(['foo' => '123'], ['foo' => ['filter' => 'notExistingFunction__']]);
    }
}
