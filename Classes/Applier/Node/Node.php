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


class Node
{
    /**
     * True if no default value has been given
     *
     * @var bool
     */
    public $isRequired = true;
    
    /**
     * The default value to use when the option is not present
     *
     * @var mixed
     */
    public $default;
    
    /**
     * True if this value can be handled as boolean flag
     *
     * @var bool
     */
    public $canBeBooleanFlag = false;
    
    /**
     * The list of types the value of this node must have
     *
     * @var array|null
     */
    public $types;
    
    /**
     * The validator callable/regex to apply to the node value
     *
     * @var callable|string|null
     */
    public $validator;
    
    /**
     * The callable to execute on the value before it runs into "validator"
     *
     * @var callable|null
     */
    public $preFilter;
    
    /**
     * A callback which is called after the type validation took place and can be used to process
     * a given value before the custom validation begins.
     *
     * @var callable|null
     */
    public $filter;
    
    /**
     * The given child definition to be applied to array children
     *
     * @var array|null
     */
    public $children;
}
