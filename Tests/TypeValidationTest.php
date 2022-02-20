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
 * Last modified: 2022.02.20 at 11:45
 */

declare(strict_types=1);


namespace Neunerlei\Options\Tests\Assets;


use Neunerlei\Options\OptionException;
use Neunerlei\Options\Options;
use Neunerlei\Options\OptionValidationError;
use Neunerlei\Options\OptionValidationException;
use Neunerlei\Options\Tests\Fixture\TypeValidationDataProvider;
use PHPUnit\Framework\TestCase;

class TypeValidationTest extends TestCase
{
    protected const SINGLE_TYPES
        = [
            'bool',
            'boolean',
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
        $data[] = [DummyClassA::class, new DummyClassA()];
        $data[] = [DummyClassA::class, new DummyExtendedClassA()];
        $data[] = [DummyExtendedClassA::class, new DummyExtendedClassA()];
        $data[] = [DummyInterfaceA::class, new DummyExtendedClassA()];

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
        $data[] = [DummyInterfaceA::class, (object)[]];
        $data[] = [DummyInterfaceA::class, 'non existing class'];
        $data[] = [DummyInterfaceA::class, new DummyClassA()];

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
            self::assertInstanceOf(OptionValidationError::class, $exception->getErrors()[0]);
            self::assertEquals(OptionValidationError::TYPE_INVALID_TYPE, $exception->getErrors()[0]->getType());
        }
    }
}
