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


namespace Neunerlei\Options\Applier\Pass;


use Closure;
use Neunerlei\Options\Applier\Context\Context;
use Neunerlei\Options\Applier\Node\Node;
use Neunerlei\Options\Applier\Validation\ValidationErrorFactory;

class InitializationPass
{
    /**
     * @var \Neunerlei\Options\Applier\Validation\ValidationErrorFactory
     */
    protected $errorFactory;
    
    public function __construct(?ValidationErrorFactory $errorFactory = null)
    {
        $this->errorFactory = $errorFactory ?? new ValidationErrorFactory();
    }
    
    /**
     * Prepares the given $list by validating required keys and rewriting boolean flags to their actual mapping
     *
     * @param   array    $list
     * @param   Context  $context
     * @param   array    $nodes
     *
     * @return array
     */
    public function initialize(array $list, Context $context, array $nodes): array
    {
        $pathBackup = $context->path;
        try {
            foreach ($nodes as $key => $node) {
                $context->path = $pathBackup;
                $context->path[] = $key;
                
                $list = $this->rewriteBooleanFlagOf($node, $key, $list);
                
                if (array_key_exists($key, $list)) {
                    continue;
                }
                
                $list = $this->applyDefaultsFor($context, $node, $key, $list);
            }
            
            return $list;
        } finally {
            $context->path = $pathBackup;
        }
    }
    
    /**
     * Rewrites the numerically keyed "booleanFlags" to a "key/value" mapping.
     *
     * @param   \Neunerlei\Options\Applier\Node\Node  $node
     * @param                                         $key
     * @param   array                                 $list
     *
     * @return array
     */
    protected function rewriteBooleanFlagOf(Node $node, $key, array $list): array
    {
        if (! $node->canBeBooleanFlag) {
            return $list;
        }
        
        $flagKey = array_search($key, $list, true);
        if (is_numeric($flagKey)) {
            $list[$key] = $list[$key] ?? true;
            unset($list[$flagKey]);
        }
        
        return $list;
    }
    
    /**
     * Is called to apply the default values for a missing key in the given $list
     *
     * @param   \Neunerlei\Options\Applier\Context\Context  $context
     * @param   \Neunerlei\Options\Applier\Node\Node        $node
     * @param                                               $key
     * @param   array                                       $list  The list to add the default value to
     *
     * @return array
     */
    protected function applyDefaultsFor(Context $context, Node $node, $key, array $list): array
    {
        if ($node->isRequired) {
            $context->errors[] = $this->errorFactory->makeMissingRequiredKeyError($context);
            
            return $list;
        }
        
        if ($node->default instanceof Closure) {
            $list[$key] = call_user_func($node->default, $key, $list, $node, $context);
        } else {
            $list[$key] = $node->default;
        }
        
        return $list;
    }
}
