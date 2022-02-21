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
use Neunerlei\Options\Exception\InvalidOptionDefinitionException;
use Neunerlei\Options\Exception\OptionValidationException;
use Neunerlei\Options\Options;
use PHPUnit\Framework\TestCase;

class DefaultValueTest extends TestCase
{
    
    public function provideTestThatDefaultValueIsBeingSetData(): array
    {
        return [
            [['foo' => true], ['foo' => true]],
            [['foo' => true], ['foo' => ['default' => true]]],
            [['foo' => 123], ['foo' => 123]],
            [['foo' => 123], ['foo' => ['default' => 123]]],
            [['foo' => 123, 'bar' => 'baz'], ['foo' => 123, 'bar' => 'baz']],
            [['foo' => 123, 'bar' => 'baz'], ['foo' => ['default' => 123], 'bar' => ['default' => 'baz']]],
            [['foo' => []], ['foo' => [[]]]],
            [['foo' => ['foo' => 'bar']], ['foo' => [['foo' => 'bar']]]],
            [
                ['foo' => 123123],
                [
                    'foo' => function ($k, $options, $node, $context) {
                        $this->assertEquals('foo', $k);
                        $this->assertEquals([], $options);
                        static::assertInstanceOf(Node::class, $node);
                        static::assertInstanceOf(Context::class, $context);
                        $this->assertFalse($node->isRequired);
                        $this->assertIsCallable($node->default);
                        $this->assertEquals(['foo'], $context->path);
                        
                        return 123123;
                    },
                ],
            ],
        ];
    }
    
    /**
     * @dataProvider provideTestThatDefaultValueIsBeingSetData
     */
    public function testThatDefaultValueIsBeingSet(array $expect, array $definition): void
    {
        self::assertEquals($expect, Options::make([], $definition));
    }
    
    public function testIfInvalidDefaultArrayFails(): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);
        $this->expectExceptionMessage('Definition error at: "foo"; An empty array was given as definition.');
        Options::make([], ['foo' => []]);
    }
    
    public function testIfMissingRequiredValueFails(): void
    {
        $this->expectException(OptionValidationException::class);
        $this->expectExceptionMessage('-The value at: "foo" is required');
        Options::make([], ['foo' => ['type' => 'string']]);
    }
}
