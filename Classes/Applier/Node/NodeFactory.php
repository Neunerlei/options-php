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


namespace Neunerlei\Options\Applier\Node;


use Neunerlei\Options\Applier\Context\Context;
use Neunerlei\Options\Applier\Type\InvalidTypeException;
use Neunerlei\Options\Applier\Type\TypeConverter;
use Neunerlei\Options\Applier\Type\ValueTypes;
use Neunerlei\Options\Exception\InvalidOptionDefinitionException;

class NodeFactory
{
    /**
     * @var \Neunerlei\Options\Applier\Type\TypeConverter
     */
    protected $typeConverter;
    
    public function __construct(?TypeConverter $typeConverter = null)
    {
        $this->typeConverter = $typeConverter ?? new TypeConverter();
    }
    
    /**
     * Creates a new node instance based on the given definition
     *
     * @param   \Neunerlei\Options\Applier\Context\Context  $context
     * @param   mixed                                       $definition
     *
     * @return \Neunerlei\Options\Applier\Node\Node
     */
    public function makeNode(Context $context, $definition): Node
    {
        $definition = $this->prepareDefinition($context, $definition);
        
        return $this->applyDefinition(new Node(), $context, $definition);
    }
    
    /**
     * Creates a list of nodes based on the given list of definitions
     *
     * @param   \Neunerlei\Options\Applier\Context\Context  $context
     * @param   array                                       $definition
     *
     * @return \Neunerlei\Options\Applier\Node\Node[]
     */
    public function makeNodeList(Context $context, array $definition): array
    {
        $pathBackup = $context->path;
        
        try {
            $nodes = [];
            
            foreach ($definition as $k => $nodeDefinition) {
                $context->path = $pathBackup;
                $context->path[] = $k;
                
                $nodes[$k] = $this->makeNode($context, $nodeDefinition);
            }
            
            return $nodes;
        } finally {
            $context->path = $pathBackup;
        }
    }
    
    /**
     * Internal helper which is used to convert the given definition into an array.
     *
     * @param   \Neunerlei\Options\Applier\Context\Context  $context
     * @param   mixed                                       $definition  Either a value or an array of the definition
     *
     * @return array
     * @throws \Neunerlei\Options\Exception\InvalidOptionDefinitionException
     */
    protected function prepareDefinition(Context $context, $definition): array
    {
        if (! is_array($definition)) {
            // Default simple definition -> The value is the default value
            $definition = ['default' => $definition];
        } elseif (count($definition) === 1 && is_numeric(key($definition))
                  && is_array(($firstDef = reset($definition)))) {
            // Array simple definition -> The first value in the array is the default value
            $definition = ['default' => $firstDef];
            // @codeCoverageIgnoreStart
        } elseif (empty($definition)) {
            // @codeCoverageIgnoreEnd
            // Failed array simple definition
            throw new InvalidOptionDefinitionException(
                'An empty array was given as definition. If you want an array as default value make sure to ' .
                'pass it like: ' . '"key" => [[]] or like "key" => ["default" => []]', $context->path);
        }
        
        // Handle "values" deprecation
        if (isset($definition['values'])) {
            trigger_error(
                'The usage of the "values" option at: "' . implode('.', $context->path) .
                ' is deprecated. Use the "validator" option instead.',
                E_USER_DEPRECATED
            );
            
            if (! isset($definition['validator'])) {
                $definition['validator'] = $definition['values'];
            }
            
            unset($definition['values']);
        }
        
        return $definition;
    }
    
    /**
     * Applies the prepared definition on the given node object
     *
     * @param   \Neunerlei\Options\Applier\Node\Node        $node
     * @param   \Neunerlei\Options\Applier\Context\Context  $context
     * @param   array                                       $definition
     *
     * @return Node
     * @throws \Neunerlei\Options\Exception\InvalidOptionDefinitionException
     */
    protected function applyDefinition(Node $node, Context $context, array $definition): Node
    {
        foreach ($definition as $key => $value) {
            switch ($key) {
                case 'default':
                    $node->default = $value;
                    $node->isRequired = false;
                    break;
                
                case 'type':
                    try {
                        $node->types = $this->typeConverter->convertList($value);
                        if ($context->allowBooleanFlags && isset($node->types)) {
                            $node->canBeBooleanFlag = isset($node->types[ValueTypes::TYPE_BOOL]);
                        }
                    } catch (InvalidTypeException $e) {
                        throw new InvalidOptionDefinitionException(
                            'Invalid type definition; ' . $e->getMessage(), $context->path);
                    }
                    break;
                
                case 'validator':
                    if (! is_callable($value) && ! is_string($value) && ! is_array($value)) {
                        throw new InvalidOptionDefinitionException(
                            'The given validator must either be a callable, array of values or regular expression',
                            $context->path);
                    }
                    $node->validator = $value;
                    break;
                
                case 'preFilter':
                case 'filter':
                    if (! is_callable($value)) {
                        throw new InvalidOptionDefinitionException(
                            'The given ' . $key . ' is not callable', $context->path);
                    }
                    $node->$key = $value;
                    break;
                
                case 'children':
                    if (! is_array($value)) {
                        throw new InvalidOptionDefinitionException(
                            'The given children definition must be an array', $context->path);
                    }
                    $node->children = $value;
                    break;
                
                default:
                    throw new InvalidOptionDefinitionException(
                        'Found invalid key: "' . $key
                        . '" - Make sure to wrap arrays in definitions in an outer array!',
                        $context->path);
            }
        }
        
        return $node;
    }
}
