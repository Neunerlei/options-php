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
 * Last modified: 2022.02.20 at 20:50
 */

declare(strict_types=1);


namespace Neunerlei\Options\Applier\Pass;


use Neunerlei\Options\Applier\Context\Context;
use Neunerlei\Options\Applier\Node\Node;
use Neunerlei\Options\Applier\Validation\ValidationErrorFactory;
use Neunerlei\Options\Applier\Validation\Validator;
use Neunerlei\Options\Applier\Validation\ValidatorResult;

class ValidationPass
{
    /**
     * @var \Neunerlei\Options\Applier\Validation\Validator
     */
    protected $validator;

    /**
     * @var \Neunerlei\Options\Applier\Validation\ValidationErrorFactory
     */
    protected $errorFactory;

    public function __construct(?Validator $validator = null, ?ValidationErrorFactory $errorFactory = null)
    {
        $this->validator    = $validator ?? new Validator();
        $this->errorFactory = $errorFactory ?? new ValidationErrorFactory();
    }

    /**
     * Executes all major validation steps based on the given $list of values
     *
     * @param   array     $list
     * @param   Context   $context
     * @param   array     $nodes
     * @param   callable  $childHandler
     *
     * @return array
     */
    public function validate(array $list, Context $context, array $nodes, callable $childHandler): array
    {
        $pathBackup = $context->path;
        try {
            foreach ($list as $key => $value) {
                $context->path   = $pathBackup;
                $context->path[] = $key;

                if (! isset($nodes[$key])) {
                    $list = $this->handleUnknownValue($context, $key, $value, $list, $nodes);
                    continue;
                }

                $node = $nodes[$key];

                $list[$key] = $value = $this->runFilter('preFilter', $key, $value, $context, $node, $list);

                $vRes = $this->validator->validateType($node, $value);
                if ($vRes instanceof ValidatorResult) {
                    $context->errors[] = $this->errorFactory->makeValidationFailedError($context, $vRes);
                    continue;
                }

                $list[$key] = $value = $this->runFilter('filter', $key, $value, $context, $node, $list);

                $vRes = $this->validator->validate($context, $node, $key, $value, $list);
                if ($vRes instanceof ValidatorResult) {
                    $context->errors[] = $this->errorFactory->makeValidationFailedError($context, $vRes);
                    continue;
                }

                if (isset($node->children) && is_array($value)) {
                    $list[$key] = $childHandler($context, $node, $value);
                }
            }

            return $list;

        } finally {
            $context->path = $pathBackup;
        }
    }


    /**
     * Handles the resolution of unknown values based on the current context settings
     *
     * @param   \Neunerlei\Options\Applier\Context\Context  $context
     * @param   mixed                                       $k
     * @param   mixed                                       $value
     * @param   array                                       $list
     * @param   array                                       $nodes
     *
     * @return array
     */
    protected function handleUnknownValue(Context $context, $k, $value, array $list, array $nodes): array
    {
        if ($context->allowUnknown) {
            return $list;
        }

        if ($context->ignoreUnknown) {
            unset($list[$k]);

            return $list;
        }

        $context->errors[] = $this->errorFactory->makeUnknownKeyError($context, $k, $value, $nodes);

        return $list;
    }

    /**
     * Helper to execute either the "filter" or the "preFilter" callables
     *
     * @param   string                                      $type
     * @param                                               $key
     * @param                                               $value
     * @param   \Neunerlei\Options\Applier\Context\Context  $context
     * @param   \Neunerlei\Options\Applier\Node\Node        $node
     * @param   array                                       $list
     *
     * @return mixed
     */
    protected function runFilter(string $type, $key, $value, Context $context, Node $node, array $list)
    {
        if (! isset($node->$type)) {
            return $value;
        }

        return ($node->$type)($value, $key, $list, $node, $context);
    }
}
