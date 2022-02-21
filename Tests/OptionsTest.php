<?php
declare(strict_types=1);
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


use Neunerlei\Options\Applier\Applier;
use Neunerlei\Options\Applier\Node\Node;
use Neunerlei\Options\Applier\Validation\ValidationError;
use Neunerlei\Options\Applier\Validation\ValidationErrorFactory;
use Neunerlei\Options\Applier\Validation\ValidatorResult;
use Neunerlei\Options\Exception\InvalidOptionDefinitionException;
use Neunerlei\Options\Exception\OptionValidationException;
use Neunerlei\Options\Options;
use Neunerlei\Options\Tests\Fixture\FixtureExtendedApplier;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class OptionsTest extends TestCase
{
    /** @noinspection PhpConditionAlreadyCheckedInspection */
    public function testApplierGeneration(): void
    {
        $ref = new ReflectionClass(Options::class);
        self::assertEquals(Applier::class, Options::$applierClass);

        // Test default applier instantiation
        Options::make([], []);
        $props   = $ref->getStaticProperties();
        $applier = $props['applier'];
        self::assertInstanceOf(Applier::class, $props['applier']);

        // Test if singleton works
        Options::make([], []);
        $props    = $ref->getStaticProperties();
        $applier2 = $props['applier'];
        self::assertSame($applier, $applier2);

        // Check if the class can be overwritten and will automatically update the instance
        Options::$applierClass = FixtureExtendedApplier::class;
        Options::make([], []);
        $props    = $ref->getStaticProperties();
        $applier3 = $props['applier'];
        self::assertInstanceOf(FixtureExtendedApplier::class, $applier3);
        self::assertSame($applier, $applier2);
        self::assertNotSame($applier, $applier3);
        Options::$applierClass = Applier::class;
    }

    public function testInvalidDefinitionKey(): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);
        $this->expectExceptionMessage('Definition error at: "foo"; Found invalid key: "faz" - Make sure to wrap arrays in definitions in an outer array');

        Options::make([], ['foo' => ['faz' => true]]);
    }

    public function testUnknownKeyValidation(): void
    {
        // Check if an unknown key fails
        try {
            Options::make(['bar' => 123], ['foo' => true]);
            self::fail('Unknown key did not throw an exception!');
        } catch (OptionValidationException $e) {
            self::assertInstanceOf(OptionValidationException::class, $e);
            self::assertStringContainsString('-Invalid option key: "bar" given', $e->getMessage());
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

    public function testSingleOptionApplication(): void
    {
        self::assertEquals('string', Options::makeSingle('myParam', null, ['default' => 'string']));
        self::assertEquals('123', Options::makeSingle('myParam', '123', ['default' => 'string']));
    }

    public function testOptionValidationErrorGetters(): void
    {
        $n = new Node();
        $o = new ValidationError(1, 'foo', ['foo', 'bar'],
            new ValidatorResult(ValidatorResult::TYPE_GENERIC, $n, null, null));
        self::assertEquals(1, $o->getType());
        self::assertEquals('foo', $o->getMessage());
        self::assertEquals(['foo', 'bar'], $o->getPath());
        self::assertSame($n, $o->getNode());
        self::assertInstanceOf(ValidatorResult::class, $o->getDetails());

        // Node from details
        self::assertSame($n, $o->getDetails()->getNode());

        // Node from given node
        $o = new ValidationError(1, 'foo', ['foo', 'bar'], null, $n);
        self::assertSame($n, $o->getNode());
        self::assertNull($o->getDetails());

        // No node and no details
        $o = new ValidationError(1, 'foo', ['foo', 'bar'], null, null);
        self::assertNull($o->getNode());
    }

    public function testEmptySimilarKey(): void
    {
        $this->expectException(OptionValidationException::class);
        $this->expectExceptionMessageMatches('~-Invalid option key: "foo" given!$~');
        Options::make(['foo' => true], []);
    }

    public function testInvalidOptionDefinitionPath(): void
    {
        try {
            Options::make(['foo' => ['bar' => ['foo', 'bar', 'baz']]], [
                'foo' => [
                    'children' => [
                        'bar' => [
                            'children' => [
                                '#' => [
                                    'foo' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        } catch (InvalidOptionDefinitionException $e) {
            self::assertEquals(['foo', 'bar'], $e->getPath());
            self::assertEquals('Definition error at: "foo.bar"; Found invalid key: "foo" - Make sure to wrap arrays in definitions in an outer array!',
                $e->getMessage());
        }
    }

    public function testValueStringification(): void
    {
        $o    = new ValidationErrorFactory();
        $fRef = (new \ReflectionObject($o))->getMethod('stringifyValue');
        $fRef->setAccessible(true);

        self::assertEquals('TRUE', $fRef->invoke($o, true));
        self::assertEquals('FALSE', $fRef->invoke($o, false));
        self::assertEquals('123', $fRef->invoke($o, 123));
        self::assertEquals('Value of type: array', $fRef->invoke($o, ['foo', 'bar']));
        self::assertEquals('NULL', $fRef->invoke($o, null));
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
