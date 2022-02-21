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
 * Last modified: 2022.02.20 at 13:34
 */

declare(strict_types=1);


namespace Neunerlei\Options\Applier\Validation;


use Neunerlei\Options\Applier\Context\Context;
use Neunerlei\Options\Applier\Node\Node;
use Neunerlei\Options\Exception\InvalidOptionDefinitionException;

class Validator
{
    /**
     * @var \Neunerlei\Options\Applier\Validation\TypeValidator
     */
    protected $typeValidator;

    public function __construct(?TypeValidator $typeValidator = null)
    {
        $this->typeValidator = $typeValidator ?? new TypeValidator();
    }

    /**
     * Executes the custom validation of the node's "validator" option
     *
     * @param   \Neunerlei\Options\Applier\Context\Context  $context
     * @param   \Neunerlei\Options\Applier\Node\Node        $node
     * @param                                               $key
     * @param                                               $value
     * @param   array                                       $list
     *
     * @return \Neunerlei\Options\Applier\Validation\ValidatorResult|true
     */
    public function validate(Context $context, Node $node, $key, $value, array $list)
    {
        if ($node->validator === null) {
            return true;
        }

        $result = $this->runCallableValidator($context, $node, $key, $value, $list);
        if ($result) {
            return $result;
        }

        $result = $this->runRegexValidator($context, $node, $value);
        if ($result) {
            return $result;
        }

        return $this->runValueValidator($node, $value);
    }

    /**
     * Checks if the registered "type" configuration of the provided node matches with the given value
     *
     * @param   \Neunerlei\Options\Applier\Node\Node  $node
     * @param                                         $value
     *
     * @return \Neunerlei\Options\Applier\Validation\ValidatorResult|true
     */
    public function validateType(Node $node, $value)
    {
        if (! isset($node->types) || ($this->typeValidator->isTypeOf($value, $node->types))) {
            return true;
        }

        return new ValidatorResult(ValidatorResult::TYPE_INVALID_TYPE, $node, $node->types, $value);
    }

    /**
     * Validates that (if callable) the value is valid, or if an array is returned by the validator,
     * that the value validator is executed
     *
     * @param   \Neunerlei\Options\Applier\Context\Context  $context
     * @param   \Neunerlei\Options\Applier\Node\Node        $node
     * @param                                               $key
     * @param                                               $value
     * @param   array                                       $list
     *
     * @return bool|\Neunerlei\Options\Applier\Validation\ValidatorResult|null
     */
    protected function runCallableValidator(Context $context, Node $node, $key, $value, array $list)
    {
        if (! is_callable($node->validator)) {
            return null;
        }

        $result = ($node->validator)($value, $key, $list, $node, $context);

        if ($result === false) {
            return new ValidatorResult(ValidatorResult::TYPE_GENERIC, $node, null, $value);
        }

        if (is_array($result)) {
            return $this->runValueValidator($node, $value, $result);
        }

        if (is_string($result)) {
            return new ValidatorResult(ValidatorResult::TYPE_MESSAGE, $node, $result, $value);
        }

        return true;
    }

    /**
     * Validates the regular
     *
     * @param   \Neunerlei\Options\Applier\Context\Context  $context
     * @param   \Neunerlei\Options\Applier\Node\Node        $node
     * @param                                               $value
     *
     * @return bool|ValidatorResult
     * @throws \Neunerlei\Options\Exception\InvalidOptionDefinitionException
     */
    protected function runRegexValidator(Context $context, Node $node, $value)
    {
        if (! is_string($node->validator)) {
            return null;
        }

        if (@preg_match($node->validator, (string)$value)) {
            return true;
        }

        if (preg_last_error()) {
            throw new InvalidOptionDefinitionException(
                'The given regular expression "' . $node->validator . '" used as validator is invalid. Error: '
                . preg_last_error(),
                $context->path
            );
        }

        return new ValidatorResult(ValidatorResult::TYPE_REGEX, $node, $node->validator, $value);
    }

    /**
     * Validates that the given value is either in the validator array of the node, or given as $valueList
     *
     * @param   \Neunerlei\Options\Applier\Node\Node        $node
     * @param                                               $value
     * @param   array|null                                  $valueList
     *
     * @return bool|ValidatorResult
     */
    protected function runValueValidator(Node $node, $value, ?array $valueList = null)
    {
        /** @noinspection ProperNullCoalescingOperatorUsageInspection */
        $list = $valueList ?? $node->validator ?? null;
        if (is_array($list) && in_array($value, $list, true)) {
            return true;
        }

        return new ValidatorResult(ValidatorResult::TYPE_INVALID_VALUE, $node, $list, $value);
    }
}
