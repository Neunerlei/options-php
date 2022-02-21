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


use Neunerlei\Options\Applier\Node\Node;
use Neunerlei\Options\Applier\Validation\ValidationError;
use Neunerlei\Options\Applier\Validation\ValidatorResult;
use Neunerlei\Options\Exception\InvalidOptionDefinitionException;
use Neunerlei\Options\Exception\OptionException;
use Neunerlei\Options\Exception\OptionValidationException;
use Neunerlei\Options\Options;
use Neunerlei\Options\Tests\Data\TypeValidationDataProvider;
use Neunerlei\Options\Tests\Fixture\FixtureClassA;
use Neunerlei\Options\Tests\Fixture\FixtureExtendedClassA;
use Neunerlei\Options\Tests\Fixture\FixtureInterfaceA;
use PHPUnit\Framework\TestCase;

class TypeValidationTest extends TestCase
{
    protected const SINGLE_TYPES
        = [
            'bool',
            'boolean',
            'true',
            'false',
            'int',
            'integer',
            'float',
            'double',
            'string',
            'array',
            'object',
            'resource',
            'null',
            'number',
            'numeric',
            'callable',
        ];
    
    public function provideTestPositiveSingleTypeValidationData(): array
    {
        $data = [];
        foreach (static::SINGLE_TYPES as $type) {
            foreach (TypeValidationDataProvider::getValidDataFor($type) as $value) {
                $data[] = [$type, $value];
            }
        }
        
        // Special cases for class object validation
        $data[] = [FixtureClassA::class, new FixtureClassA()];
        $data[] = [FixtureClassA::class, new FixtureExtendedClassA()];
        $data[] = [FixtureExtendedClassA::class, new FixtureExtendedClassA()];
        $data[] = [FixtureInterfaceA::class, new FixtureExtendedClassA()];
        
        return $data;
    }
    
    /**
     * @dataProvider provideTestPositiveSingleTypeValidationData
     */
    public function testPositiveSingleTypeValidation(string $type, $value): void
    {
        self::assertEquals(['foo' => $value], Options::make(['foo' => $value], ['foo' => ['type' => $type]]));
    }
    
    public function provideTestNegativeSingleTypeValidationData(): array
    {
        $data = [];
        foreach (static::SINGLE_TYPES as $type) {
            foreach (TypeValidationDataProvider::getInvalidDataFor($type) as $value) {
                $data[] = [$type, $value];
            }
        }
        
        // Special cases for class object validation
        $data[] = [FixtureInterfaceA::class, (object)[]];
        $data[] = [FixtureInterfaceA::class, 'non existing class'];
        $data[] = [FixtureInterfaceA::class, new FixtureClassA()];
        
        return $data;
    }
    
    /**
     * @dataProvider provideTestNegativeSingleTypeValidationData
     */
    public function testNegativeSingleTypeValidation(string $type, $value): void
    {
        try {
            Options::make(['foo' => $value], ['foo' => ['type' => $type]]);
            self::fail('A Validation that should fail for type: ' . $type . ' did not fail as expected!');
        } catch (OptionException $exception) {
            /** @var OptionValidationException $exception */
            self::assertInstanceOf(OptionValidationException::class, $exception);
            self::assertCount(1, $exception->getErrors());
            self::assertInstanceOf(ValidationError::class, $exception->getErrors()[0]);
            self::assertEquals(ValidationError::TYPE_VALIDATION_FAILED, $exception->getErrors()[0]->getType());
            self::assertInstanceOf(ValidatorResult::class, $exception->getErrors()[0]->getDetails());
            self::assertInstanceOf(Node::class, $exception->getErrors()[0]->getNode());
        }
    }
    
    public function testInvalidSingleTypeDefinitionFail(): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);
        $this->expectExceptionMessage('Definition error at: "foo"; Invalid type definition; Type definitions have to be an array of strings, or a single string!');
        Options::make(['foo' => false], ['foo' => ['type' => false]]);
    }
    
    public function provideTestPositiveMultiTypeValidationData(): array
    {
        return [
            [
                ['foo' => 'bar'],
                ['foo' => ['type' => ['string', 'bool']]],
            ],
            [
                ['foo' => true],
                ['foo' => ['type' => ['string', 'bool']]],
            ],
            [
                ['foo' => false],
                ['foo' => ['type' => ['string', 'bool']]],
            ],
            [
                ['foo' => new FixtureExtendedClassA()],
                ['foo' => ['type' => [FixtureClassA::class, 'bool']]],
            ],
        ];
    }
    
    /**
     * @dataProvider provideTestPositiveMultiTypeValidationData
     */
    public function testPositiveMultiTypeValidation(array $expect, array $definition): void
    {
        static::assertEquals($expect, Options::make($expect, $definition));
    }
    
    public function provideTestNegativeMultiTypeValidationData(): array
    {
        return [
            [
                ['foo' => new FixtureExtendedClassA()],
                ['foo' => ['type' => ['string', 'bool']]],
                '-Validation failed at: "foo" - Invalid value type "Instance of: ' . FixtureExtendedClassA::class
                . '" given; only values with the following types are allowed: "string" or "bool"',
            ],
            [
                ['foo' => 'hello world'],
                ['foo' => ['type' => ['null', 'bool']]],
                '-Validation failed at: "foo" - Invalid value type "string" given; only values with the following types are allowed: "null" or "bool"',
            ],
        ];
    }
    
    /**
     * @dataProvider provideTestNegativeMultiTypeValidationData
     */
    public function testNegativeMultiTypeValidation(array $data, array $definition, string $error): void
    {
        $this->expectException(OptionValidationException::class);
        $this->expectExceptionMessage($error);
        Options::make($data, $definition);
    }
    
    public function testInvalidMultipleTypeDefinitionFail(): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);
        $this->expectExceptionMessage(
            'Definition error at: "foo"; Invalid type definition; Type definitions have to be an array of strings, or a single string!');
        Options::make(['foo' => false], ['foo' => ['type' => [false]]]);
    }
    
    public function testPositiveDefaultTypeValidation(): void
    {
        self::assertEquals(['foo' => true], Options::make([], ['foo' => ['type' => 'bool', 'default' => true]]));
    }
    
    public function testNegativeDefaultTypeValidation(): void
    {
        $this->expectException(OptionValidationException::class);
        $this->expectExceptionMessage('-Validation failed at: "foo" - Invalid value type "boolean" given; only values with the following types are allowed: "string"');
        Options::make([], ['foo' => ['type' => 'string', 'default' => true]]);
    }
}
