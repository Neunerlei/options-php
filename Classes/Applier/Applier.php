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

namespace Neunerlei\Options\Applier;

use Neunerlei\Options\Applier\Context\Context;
use Neunerlei\Options\Applier\Context\ContextFactory;
use Neunerlei\Options\Applier\Node\Node;
use Neunerlei\Options\Applier\Node\NodeFactory;
use Neunerlei\Options\Applier\Pass\InitializationPass;
use Neunerlei\Options\Applier\Pass\ValidationPass;
use Neunerlei\Options\Applier\Validation\ValidationErrorFactory;
use Neunerlei\Options\Exception\OptionValidationException;

class Applier
{
    /**
     * @var \Neunerlei\Options\Applier\Context\ContextFactory
     */
    protected $contextFactory;
    
    /**
     * @var \Neunerlei\Options\Applier\Node\NodeFactory
     */
    protected $nodeFactory;
    
    /**
     * @var \Neunerlei\Options\Applier\Validation\ValidationErrorFactory
     */
    protected $errorFactory;
    
    /**
     * @var \Neunerlei\Options\Applier\Pass\InitializationPass
     */
    protected $initializer;
    
    /**
     * @var \Neunerlei\Options\Applier\Pass\ValidationPass
     */
    protected $validator;
    
    /**
     * The closure to resolve children definitions
     *
     * @var callable
     */
    protected $childHandler;
    
    public function __construct(
        ?ContextFactory $contextFactory = null,
        ?NodeFactory $nodeFactory = null,
        ?ValidationErrorFactory $errorFactory = null,
        ?InitializationPass $initializer = null,
        ?ValidationPass $validator = null
    )
    {
        $this->contextFactory = $contextFactory ?? new ContextFactory();
        $this->nodeFactory = $nodeFactory ?? new NodeFactory();
        $this->errorFactory = $errorFactory ?? new ValidationErrorFactory();
        $this->initializer = $initializer ?? new InitializationPass($errorFactory);
        $this->validator = $validator ?? new ValidationPass(null, $errorFactory);
        $this->childHandler = function () {
            return $this->applyToChildren(...func_get_args());
        };
    }
    
    /**
     * Can be used in the exact same way as Options::make() is used.
     *
     * @param   array  $input
     * @param   array  $definition
     * @param   array  $options
     *
     * @return array
     * @throws \Neunerlei\Options\Exception\OptionValidationException
     * @see \Neunerlei\Options\Options::make()
     */
    public function apply(array $input, array $definition, array $options = []): array
    {
        $context = $this->contextFactory->makeContext($options);
        $result = $this->applyInternal($context, $input, $definition);
        
        if (empty($context->errors)) {
            return $result;
        }
        
        throw new OptionValidationException($context->errors);
    }
    
    /**
     * Internal helper to apply the definition recursively including the children
     *
     * @param   \Neunerlei\Options\Applier\Context\Context  $context
     * @param   array                                       $list
     * @param   array                                       $definition
     *
     * @return array
     */
    protected function applyInternal(Context $context, array $list, array $definition): array
    {
        $nodes = $this->nodeFactory->makeNodeList($context, $definition);
        
        return $this->validator->validate(
            $this->initializer->initialize($list, $context, $nodes),
            $context, $nodes, $this->childHandler);
    }
    
    /**
     * Handles the recursive iteration of "child" nodes inside the "children" definition using associative keys.
     * If the "*" key is used in the $children array, the request will be forwarded to applyToArrayListChildren()
     * If the "#" key is used in the $children array, the request will be forwarded to applyToValueListChildren()
     *
     * Example: ['foo' => ['foo' => 'bar', 'bar' => 'baz]]
     * Definition: ['foo' => ['type' => 'array', 'children' => ['foo' => ['type' => 'string'], 'bar' => 'baz']]]
     *
     * @param   \Neunerlei\Options\Applier\Context\Context  $context
     * @param   \Neunerlei\Options\Applier\Node\Node        $node
     * @param   array                                       $value
     *
     * @return array
     */
    protected function applyToChildren(Context $context, Node $node, array $value): array
    {
        if (isset($node->children['*']) && is_array($node->children['*'])) {
            return $this->applyToArrayListChildren($context, $node, $node->children['*'], $value);
        }
        
        if (isset($node->children['#']) && is_array($node->children['#'])) {
            return $this->applyToValueListChildren($context, $node->children['#'], $value);
        }
        
        // Associative child array
        return $this->applyInternal($context, $value, $node->children);
    }
    
    /**
     * Handles the iteration of a nested array list for which the given $children definition should be applied.
     * Receives the value of the "*" special key in the "children" node configuration.
     * If the value has associative keys, the request will be forwarded to applyToValueListChildren().
     *
     * Example: ['foo' => [['foo' => 'bar'], ['foo' => 'baz'], ['foo' => 'faz']]
     * Definition: ['foo' => ['type' => 'array', 'children' => ['*' => ['foo' => ['type' => 'string']]]]]
     *
     * @param   \Neunerlei\Options\Applier\Context\Context  $context
     * @param   \Neunerlei\Options\Applier\Node\Node        $node
     * @param   array                                       $children
     * @param   array                                       $value
     *
     * @return array
     */
    protected function applyToArrayListChildren(Context $context, Node $node, array $children, array $value): array
    {
        $pathBackup = $context->path;
        
        try {
            $context->path[] = '*';
            $nodes = $this->nodeFactory->makeNodeList($context, $children);
            
            foreach ($value as $key => $list) {
                $context->path = $pathBackup;
                $context->path[] = $key;
                
                if (! is_array($list)) {
                    $context->errors[] = $this->errorFactory->makeInvalidChildValueError($context, $node, $list);
                    continue;
                }
                
                $value[$key] = $this->validator->validate(
                    $this->initializer->initialize($list, $context, $nodes),
                    $context, $nodes, $this->childHandler);
            }
            
            return $value;
        } finally {
            $context->path = $pathBackup;
        }
    }
    
    /**
     * Handles the iteration of value list definitions under the "*". They are a special variant
     * that only expects the non array values to be defined in a single configuration.
     *
     * Example: ['foo' => ['bar', 'baz', 'faz']], or: ['foo' => [123, 234, 4356]]
     * Definition: ['foo' => ['type' => 'array', 'children' => ['#' => ['type' => 'string']]]]
     *
     * @param   \Neunerlei\Options\Applier\Context\Context  $context
     * @param   array                                       $nodeDefinition
     * @param   array                                       $value
     *
     * @return array
     */
    protected function applyToValueListChildren(
        Context $context,
        array $nodeDefinition,
        array $value
    ): array
    {
        $node = $this->nodeFactory->makeNode($context, $nodeDefinition);
        
        $node->isRequired = false;
        
        $nodes = array_fill_keys(array_keys($value), $node);
        unset($node);
        
        return $this->validator->validate($value, $context, $nodes, $this->childHandler);
    }
}
