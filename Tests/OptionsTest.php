<?php
/**
 * Copyright 2020 Martin Neundorfer (Neunerlei)
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
 * Last modified: 2020.02.28 at 20:26
 */

namespace Neunerlei\Options\Tests;


use Neunerlei\Options\InvalidOptionDefinitionException;
use Neunerlei\Options\OptionApplier;
use Neunerlei\Options\Options;
use Neunerlei\Options\OptionValidationError;
use Neunerlei\Options\OptionValidationException;
use Neunerlei\Options\Tests\Assets\DummyClassA;
use Neunerlei\Options\Tests\Assets\DummyExtendedApplier;
use Neunerlei\Options\Tests\Assets\DummyExtendedClassA;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class OptionsTest extends TestCase
{
    public function testApplierGeneration(): void
    {
        $ref = new ReflectionClass(Options::class);
        self::assertEquals(OptionApplier::class, Options::$applierClass);

        // Test default applier instantiation
        Options::make([], []);
        $props   = $ref->getStaticProperties();
        $applier = $props['applier'];
        self::assertInstanceOf(OptionApplier::class, $props['applier']);

        // Test if singleton works
        Options::make([], []);
        $props    = $ref->getStaticProperties();
        $applier2 = $props['applier'];
        self::assertSame($applier, $applier2);

        // Check if the class can be overwritten and will automatically update the instance
        Options::$applierClass = DummyExtendedApplier::class;
        Options::make([], []);
        $props    = $ref->getStaticProperties();
        $applier3 = $props['applier'];
        self::assertInstanceOf(DummyExtendedApplier::class, $applier3);
        self::assertSame($applier, $applier2);
        self::assertNotSame($applier, $applier3);
        Options::$applierClass = OptionApplier::class;
    }

    public function testDefaultValue(): void
    {
        // Simple default value
        self::assertEquals(['foo' => true], Options::make([], ['foo' => true]));
        self::assertEquals(['foo' => 123123], Options::make([], [
            'foo' => function ($k, $options, $definition, $path) {
                $this->assertEquals('foo', $k);
                $this->assertEquals([], $options);
                $this->assertArrayHasKey('default', $definition);
                $this->assertIsCallable($definition['default']);
                $this->assertEquals(['foo'], $path);

                return 123123;
            },
        ]));
        self::assertEquals(['foo' => 123], Options::make([], ['foo' => 123]));
        self::assertEquals(['foo' => 123, 'bar' => 'baz'], Options::make([], ['foo' => 123, 'bar' => 'baz']));

        // Complex default
        self::assertEquals(['foo' => true], Options::make([], ['foo' => ['default' => true]]));
        self::assertEquals(['foo' => 123], Options::make([], ['foo' => ['default' => 123]]));
        self::assertEquals(['foo' => 123, 'bar' => 'baz'],
            Options::make([], ['foo' => ['default' => 123], 'bar' => ['default' => 'baz']]));

        // Check if simple array definition works
        self::assertEquals(['foo' => []], Options::make([], ['foo' => [[]]]));
        self::assertEquals(['foo' => ['foo' => 'bar']], Options::make([], ['foo' => [['foo' => 'bar']]]));

        // Check if invalid array default definition fails
        try {
            Options::make([], ['foo' => []]);
            self::fail('Invalid array default value did not throw an exception!');
        } catch (InvalidOptionDefinitionException $e) {
            self::assertInstanceOf(InvalidOptionDefinitionException::class, $e);
            self::assertStringContainsString('Definition error at: "foo"; An empty array was given as definition.',
                $e->getMessage());
        }

    }

    public function testInvalidDefinitionKey(): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);
        $this->expectExceptionMessage('Definition error at: "foo"; found invalid keys: "faz" - Make sure to wrap arrays in definitions in an outer array!');

        Options::make([], ['foo' => ['faz' => true]]);
    }

    public function testMissingRequiredValue(): void
    {
        // Check if missing required value fails
        try {
            Options::make([], ['foo' => ['type' => 'string']]);
            self::fail('Missing required value did not throw an exception!');
        } catch (OptionValidationException $e) {
            self::assertInstanceOf(OptionValidationException::class, $e);
            self::assertStringContainsString('-The option key: "foo" is required!', $e->getMessage());
        }

        // Check if the "required" pseudo option works
        try {
            Options::make([], ['foo' => ['required' => true, 'default' => null]]);
            self::fail('Missing required value did not throw an exception!');
        } catch (OptionValidationException $e) {
            self::assertInstanceOf(OptionValidationException::class, $e);
            self::assertStringContainsString('-The option key: "foo" is required!', $e->getMessage());
        }
    }

    public function testUnknownKeyValidation(): void
    {
        // Check if an unknown key fails
        try {
            Options::make(['bar' => 123], ['foo' => true]);
            self::fail('Unknown key did not throw an exception!');
        } catch (OptionValidationException $e) {
            self::assertInstanceOf(OptionValidationException::class, $e);
            self::assertStringContainsString('-Invalid option key: "bar" given!', $e->getMessage());
        }

        // Check if an unknown key can be allowed
        $v = Options::make(['bar' => 123], ['foo' => true], ['allowUnknown']);
        self::assertEquals(['bar' => 123, 'foo' => true], $v);

        // Check if an unknown key can be ignored
        $v = Options::make(['bar' => 123], ['foo' => true], ['ignoreUnknown']);
        self::assertEquals(['foo' => true], $v);

    }

    public function testBooleanFlags(): void
    {
        $flagDefinition = ['foo' => ['type' => 'bool', 'default' => false]];
        self::assertEquals(['foo' => true], Options::make(['foo'], $flagDefinition));
        self::assertEquals(['foo' => false], Options::make([], $flagDefinition));

        // Fail if boolean flags are disabled
        try {
            Options::make(['foo'], $flagDefinition, ['allowBooleanFlags' => false]);
            self::fail('Did not fail when boolean flags were disabled!');
        } catch (OptionValidationException $e) {
            self::assertInstanceOf(OptionValidationException::class, $e);
            self::assertStringContainsString('-Invalid option key: "0" given!', $e->getMessage());
        }

        // Test if flags and direct definitions are handled correctly
        self::assertEquals(['foo' => true], Options::make(['foo', 'foo' => true], $flagDefinition));

        // The direct definition has priority over the flag value
        self::assertEquals(['foo' => false], Options::make(['foo', 'foo' => false], $flagDefinition));
    }

    public function testBooleanFlagMissingFail(): void
    {
        $this->expectException(OptionValidationException::class);
        $this->expectExceptionMessage('-Invalid option key: "foo (0)" given! Did you mean: "bar" instead?');
        Options::make(['foo'], [
            'bar' => [
                'type' => 'bool',
            ],
        ]);
    }

    public function testInvalidSingleTypeDefinitionFail(): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);
        $this->expectExceptionMessage('Definition error at: "foo" - Type definitions have to be an array of strings, or a single string!');
        Options::make(['foo' => false], [
            'foo' => ['type' => false],
        ]);
    }

    public function testMultiTypeValidation(): void
    {
        self::assertEquals(['foo' => 'bar'],
            Options::make(['foo' => 'bar'], ['foo' => ['type' => ['string', 'bool']]]));
        self::assertEquals(['foo' => true], Options::make(['foo' => true], ['foo' => ['type' => ['string', 'bool']]]));
        self::assertEquals(['foo' => false],
            Options::make(['foo' => false], ['foo' => ['type' => ['string', 'bool']]]));
        $a = new DummyExtendedClassA();
        self::assertEquals(['foo' => $a],
            Options::make(['foo' => $a], ['foo' => ['type' => [DummyClassA::class, 'bool']]]));

        try {
            self::assertEquals(['foo' => $a], Options::make(['foo' => $a], ['foo' => ['type' => ['string', 'bool']]]));
            self::fail('Missing required value did not throw an exception!');
        } catch (OptionValidationException $e) {
            self::assertInstanceOf(OptionValidationException::class, $e);
            self::assertStringContainsString('-Invalid value type at: "foo" given; Allowed types: "string" or "bool"',
                $e->getMessage());
        }
        try {
            self::assertEquals(['foo' => $a], Options::make(['foo' => $a], ['foo' => ['type' => ['null', 'bool']]]));
            self::fail('Missing required value did not throw an exception!');
        } catch (OptionValidationException $e) {
            self::assertInstanceOf(OptionValidationException::class, $e);
            self::assertStringContainsString('-Invalid value type at: "foo" given; Allowed types: "null" or "bool"',
                $e->getMessage());
        }
    }

    public function testInvalidMultipleTypeDefinitionFail(): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);
        $this->expectExceptionMessage('Definition error at: "foo" - Type definitions have to be an array of strings, or a single string!');
        Options::make(['foo' => false], [
            'foo' => ['type' => [false]],
        ]);
    }

    public function testDefaultTypeValidation(): void
    {
        self::assertEquals(['foo' => true], Options::make([], ['foo' => ['type' => 'bool', 'default' => true]]));
        try {
            Options::make([], ['foo' => ['type' => 'string', 'default' => true]]);
            self::fail('Did not fail when default value did not match the variable type!');
        } catch (OptionValidationException $e) {
            self::assertInstanceOf(OptionValidationException::class, $e);
            self::assertStringContainsString('-Invalid value type at: "foo" given; Allowed types: "string"',
                $e->getMessage());
        }
    }

    public function testPreFilter(): void
    {
        $executed = false;
        $v        = Options::make(['foo' => '123'], [
            'foo' => [
                'type'      => 'int',
                'preFilter' => function ($v, $k, $list, $definition, $path) use (&$executed) {
                    $executed = true;
                    static::assertEquals('123', $v);
                    static::assertEquals('foo', $k);
                    static::assertEquals(['foo' => '123'], $list);
                    static::assertIsArray($definition);
                    static::assertArrayHasKey('type', $definition);
                    static::assertEquals('int', $definition['type']);
                    static::assertArrayHasKey('preFilter', $definition);
                    static::assertIsCallable($definition['preFilter']);
                    static::assertEquals(['foo'], $path);

                    return (int)$v;
                },
            ],
        ]);
        self::assertTrue($executed);
        self::assertEquals(['foo' => 123], $v);

        // Check validation if invalid callable is given
        try {
            Options::make(['foo' => '123'], ['foo' => ['preFilter' => 'notExistingFunction__']]);
            self::fail('Invalid preFilter did not throw an exception!');
        } catch (InvalidOptionDefinitionException $e) {
            self::assertInstanceOf(InvalidOptionDefinitionException::class, $e);
            self::assertStringContainsString('The preFilter is not callable!', $e->getMessage());
        }
    }

    public function testFilter(): void
    {
        $executed = false;
        $v        = Options::make(['foo' => 123], [
            'foo' => [
                'type'   => 'int',
                'filter' => function ($v, $k, $list, $definition, $path) use (&$executed) {
                    $executed = true;
                    static::assertEquals('123', $v);
                    static::assertEquals('foo', $k);
                    static::assertEquals(['foo' => '123'], $list);
                    static::assertIsArray($definition);
                    static::assertArrayHasKey('type', $definition);
                    static::assertEquals('int', $definition['type']);
                    static::assertArrayHasKey('filter', $definition);
                    static::assertIsCallable($definition['filter']);
                    static::assertEquals(['foo'], $path);

                    return $v;
                },
            ],
        ]);
        self::assertTrue($executed);
        self::assertEquals(['foo' => 123], $v);

        // Check validation if invalid callable is given
        try {
            Options::make(['foo' => '123'], ['foo' => ['filter' => 'notExistingFunction__']]);
            self::fail('Invalid filter did not throw an exception!');
        } catch (InvalidOptionDefinitionException $e) {
            self::assertInstanceOf(InvalidOptionDefinitionException::class, $e);
            self::assertStringContainsString('The filter is not callable!', $e->getMessage());
        }
    }

    public function testValidator(): void
    {
        $executed = false;
        $v        = Options::make(['foo' => 123], [
            'foo' => [
                'type'      => 'int',
                'validator' => function ($v, $k, $list, $definition, $path) use (&$executed) {
                    $executed = true;
                    static::assertEquals('123', $v);
                    static::assertEquals('foo', $k);
                    static::assertEquals(['foo' => '123'], $list);
                    static::assertIsArray($definition);
                    static::assertArrayHasKey('type', $definition);
                    static::assertEquals('int', $definition['type']);
                    static::assertArrayHasKey('validator', $definition);
                    static::assertIsCallable($definition['validator']);
                    static::assertEquals(['foo'], $path);

                    return true;
                },
            ],
        ]);
        self::assertTrue($executed);
        self::assertEquals(['foo' => 123], $v);

        // Check if invalid validator fails
        try {
            Options::make(['foo' => '123'], ['foo' => ['validator' => 'notExistingFunction__']]);
            self::fail('Invalid validator did not throw an exception!');
        } catch (InvalidOptionDefinitionException $e) {
            self::assertInstanceOf(InvalidOptionDefinitionException::class, $e);
            self::assertStringContainsString('The validator is not callable!', $e->getMessage());
        }

        // Check if validation exception is thrown
        try {
            Options::make(['foo' => '123'], [
                'foo' => [
                    'validator' => function () {
                        return false;
                    },
                ],
            ]);
            self::fail('Failed validator did not throw an exception!');
        } catch (OptionValidationException $e) {
            self::assertInstanceOf(OptionValidationException::class, $e);
            self::assertStringContainsString('-Invalid option: "foo" given!', $e->getMessage());
        }

        // Check if validation exception with custom message is thrown
        try {
            Options::make(['foo' => '123'], [
                'foo' => [
                    'validator' => function () {
                        return 'Custom error message';
                    },
                ],
            ]);
            self::fail('Failed validator did not throw an exception!');
        } catch (OptionValidationException $e) {
            self::assertInstanceOf(OptionValidationException::class, $e);
            self::assertStringContainsString('-Validation failed at: "foo" - Custom error message',
                $e->getMessage());
        }
    }

    public function testValueValidation(): void
    {
        // Positive validation
        self::assertEquals(['foo' => true],
            Options::make(['foo' => true], ['foo' => ['values' => ['true', true, 1]]]));
        self::assertEquals(['foo' => 1], Options::make(['foo' => 1], ['foo' => ['values' => ['true', true, 1]]]));
        self::assertEquals(['foo' => 'true'],
            Options::make(['foo' => 'true'], ['foo' => ['values' => ['true', true, 1]]]));

        // Positive validation by validator callback
        self::assertEquals(['foo' => true], Options::make(['foo' => true], [
            'foo' => [
                'validator' => function () {
                    return ['foo', true];
                },
            ],
        ]));

        // Check if validation exception is thrown
        try {
            Options::make(['foo' => '123'], [
                'foo' => [
                    'values' => ['foo', 123],
                ],
            ]);
            self::fail('Failed value validation did not throw an exception!');
        } catch (OptionValidationException $e) {
            self::assertInstanceOf(OptionValidationException::class, $e);
            self::assertStringContainsString('-Validation failed at: "foo" - Only the following values are allowed: "foo", "123"',
                $e->getMessage());
        }
    }

    public function testValueValidationDefinitionFail(): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);
        $this->expectExceptionMessage('Definition error at: "foo" - The values to validate should be an array!');
        Options::make(['foo' => false], [
            'foo' => ['values' => false],
        ]);
    }

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

    public function testSingleOptionApplication(): void
    {
        self::assertEquals('string', Options::makeSingle('myParam', null, ['default' => 'string']));
        self::assertEquals('123', Options::makeSingle('myParam', '123', ['default' => 'string']));
    }

    public function testOptionValidationErrorGetters(): void
    {
        $o = new OptionValidationError(1, 'foo', ['foo', 'bar']);
        self::assertEquals(1, $o->getType());
        self::assertEquals('foo', $o->getMessage());
        self::assertEquals(['foo', 'bar'], $o->getPath());
    }

    public function testEmptySimilarKey(): void
    {
        $this->expectException(OptionValidationException::class);
        $this->expectExceptionMessageMatches('~-Invalid option key: "foo" given!$~');
        Options::make(['foo' => true], []);
    }

    public function testValueStringification(): void
    {
        $o    = new OptionApplier();
        $fRef = (new \ReflectionObject($o))->getMethod('stringifyValue');
        $fRef->setAccessible(true);

        self::assertEquals('TRUE', $fRef->invoke($o, true));
        self::assertEquals('FALSE', $fRef->invoke($o, false));
        self::assertEquals('123', $fRef->invoke($o, 123));
        self::assertEquals('Value of type: array', $fRef->invoke($o, ['foo', 'bar']));
        self::assertEquals('Value of type: NULL', $fRef->invoke($o, null));
        self::assertEquals('Object of type: stdClass', $fRef->invoke($o, new \stdClass()));

        $m = new class() {
            public $val = '';

            public function __toString(): string
            {
                return $this->val;
            }
        };

        $m->val = 'Short value';
        self::assertEquals('Short value', $fRef->invoke($o, $m));

        $m->val = 'Long value, that gets cropped down to 50 chars, to avoid super long error messages';
        self::assertEquals('Long value, that gets cropped down to 50 chars, to...', $fRef->invoke($o, $m));
    }
}
