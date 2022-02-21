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
 * Last modified: 2022.02.21 at 13:11
 */

declare(strict_types=1);


namespace Neunerlei\Options\Tests;


use Neunerlei\Options\Applier\Context\Context;
use Neunerlei\Options\Applier\Node\Node;
use Neunerlei\Options\Applier\Type\ValueTypes;
use Neunerlei\Options\Exception\InvalidOptionDefinitionException;
use Neunerlei\Options\Exception\OptionValidationException;
use Neunerlei\Options\Options;
use PHPUnit\Framework\TestCase;

class CustomValidatorTest extends TestCase
{

    public function provideTestPositiveValidatorResultsData(): array
    {
        return [
            [
                ['foo' => 123],
                [
                    'foo' => [
                        'validator' => function () {
                            return true;
                        },
                    ],
                ],
            ],
            [
                ['foo' => 'hello world'],
                ['foo' => ['validator' => '~world$~']],
            ],
            [
                ['foo' => 'bar'],
                [
                    'foo' => [
                        'validator' => function () {
                            return ['foo', 'bar', 'baz'];
                        },
                    ],
                ],
            ],
            [
                ['foo' => true],
                [
                    'foo' => [
                        'validator' => function () {
                            return ['foo', true];
                        },
                    ],
                ],
            ],
            [
                ['foo' => true],
                ['foo' => ['validator' => ['true', true, 1]]],
            ],
            [
                ['foo' => 1],
                ['foo' => ['validator' => ['true', true, 1]]],
            ],
            [
                ['foo' => 'true'],
                ['foo' => ['validator' => ['true', true, 1]]],
            ],
        ];
    }

    /**
     * @dataProvider provideTestPositiveValidatorResultsData
     */
    public function testPositiveValidatorResults(array $data, array $definition): void
    {
        static::assertEquals($data, Options::make($data, $definition));
    }

    public function provideTestNegativeValidatorResultsData(): array
    {
        return [
            [
                ['foo' => 'foo'],
                [
                    'foo' => [
                        'validator' => function () {
                            return false;
                        },
                    ],
                ],
                '-Validation failed at: "foo"',
            ],
            [
                ['foo' => 'foo'],
                [
                    'foo' => [
                        'validator' => '~bar|baz~',
                    ],
                ],
                '-Validation failed at: "foo" - The value did not match the required pattern: "~bar|baz~"',
            ],
            [
                ['foo' => 'foo'],
                [
                    'foo' => [
                        'validator' => function () {
                            return ['bar', 123];
                        },
                    ],
                ],
                '-Validation failed at: "foo" - Invalid value "foo" - Only the following values are allowed: "bar", "123"',
            ],
            [
                ['foo' => 'foo'],
                [
                    'foo' => [
                        'validator' => function () {
                            return 'Custom error message';
                        },
                    ],
                ],
                '-Validation failed at: "foo" - Custom error message',
            ],
            [
                ['foo' => '123'],
                [
                    'foo' => [
                        'validator' => ['foo', 123],
                    ],
                ],
                '-Validation failed at: "foo" - Invalid value "123" - Only the following values are allowed: "foo", "123"',
            ],
        ];
    }

    /**
     * @dataProvider provideTestNegativeValidatorResultsData
     */
    public function testNegativeValidatorResults(array $data, array $definition, string $error): void
    {
        $this->expectException(OptionValidationException::class);
        $this->expectExceptionMessage($error);
        Options::make($data, $definition);
    }

    public function testCallbackValidatorParams(): void
    {
        $executed = false;
        Options::make(['foo' => 123], [
            'foo' => [
                'type'      => 'int',
                'validator' => function ($v, $k, $list, $node, $context) use (&$executed) {
                    $executed = true;
                    static::assertEquals(123, $v);
                    static::assertEquals('foo', $k);
                    static::assertEquals(['foo' => 123], $list);
                    static::assertInstanceOf(Node::class, $node);
                    static::assertEquals([ValueTypes::TYPE_INT => 'int'], $node->types);
                    static::assertInstanceOf(Context::class, $context);
                    static::assertEquals(['foo'], $context->path);

                    return true;
                },
            ],
        ]);
        self::assertTrue($executed);
    }

    public function testFailOnInvalidValidator(): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);
        $this->expectExceptionMessage('Definition error at: "foo"; The given validator must either be a callable, array of values or regular expression');
        Options::make(['foo' => '123'], ['foo' => ['validator' => null]]);
    }

    public function testFailOnInvalidValidatorRegex(): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);
        $this->expectExceptionMessage('Definition error at: "foo"; The given regular expression "invalidRegex" used as validator is invalid. Error: 1');
        Options::make(['foo' => '123'], ['foo' => ['validator' => 'invalidRegex']]);
    }

    public function testDeprecatedValuesUsage(): void
    {
        $this->setupDeprecationHandler($executed);
        static::assertEquals(['foo' => 123], Options::make(['foo' => 123], ['foo' => ['values' => [123, 234]]]));
        restore_error_handler();
        static::assertTrue($executed, 'The error handler did not catch the deprecation warning');
    }

    public function testDeprecatedNegativeValuesUsage(): void
    {
        $this->setupDeprecationHandler($executed);
        $this->expectException(OptionValidationException::class);
        $this->expectExceptionMessage('-Validation failed at: "foo" - Invalid value "1" - Only the following values are allowed: "123", "234"');
        Options::make(['foo' => 1], ['foo' => ['values' => [123, 234]]]);
        static::assertTrue($executed, 'The error handler did not catch the deprecation warning');
    }

    protected function setupDeprecationHandler(&$executed): void
    {
        $dv = ini_get('display_errors');
        ini_set('display_errors', 'off');
        set_error_handler(static function ($e, $msg) use (&$executed, $dv) {
            $executed = true;
            static::assertEquals(
                'The usage of the "values" option at: "foo is deprecated. Use the "validator" option instead.',
                $msg);

            ini_set('DISPLAY_ERRORS', $dv);

            return true;
        }, E_USER_DEPRECATED);
    }
}
