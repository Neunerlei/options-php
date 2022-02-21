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


namespace Neunerlei\Options\Applier\Validation;


use Neunerlei\Options\Applier\Node\Node;

class ValidatorResult
{
    public const TYPE_INVALID_VALUE = 1;
    public const TYPE_REGEX = 2;
    public const TYPE_GENERIC = 3;
    public const TYPE_MESSAGE = 4;
    public const TYPE_INVALID_TYPE = 5;
    
    /**
     * The type of result that occurred
     *
     * @var int
     */
    protected $type;
    
    /**
     * The node which triggered the result
     *
     * @var \Neunerlei\Options\Applier\Node\Node
     */
    protected $node;
    
    /**
     * The content of the result, based on the type
     * TYPE_INVALID_VALUE: array the value list to pass to the value validator
     * TYPE_REGEX: string the regex pattern which failed
     * TYPE_GENERIC: null
     * TYPE_MESSAGE: string the message returned by the validator
     * TYPE_INVALID_TYPE: array the list of types that are considered valid
     *
     * @var mixed
     */
    protected $content;
    
    /**
     * The value that failed to validate
     *
     * @var mixed
     */
    protected $value;
    
    public function __construct(int $type, Node $node, $content, $value)
    {
        $this->type = $type;
        $this->node = $node;
        $this->content = $content;
        $this->value = $value;
    }
    
    /**
     * Returns the type of result that occurred
     *
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }
    
    /**
     * Returns the node which triggered the result
     *
     * @return \Neunerlei\Options\Applier\Node\Node
     */
    public function getNode(): Node
    {
        return $this->node;
    }
    
    /**
     * Returns the content of the result, based on the type
     * TYPE_INVALID_VALUE: array the value list to pass to the value validator
     * TYPE_REGEX: string the regex pattern which failed
     * TYPE_GENERIC: null
     * TYPE_MESSAGE: string the message returned by the validator
     * TYPE_INVALID_TYPE: array the list of types that are considered valid
     *
     * @return mixed
     */
    public function getContent()
    {
        return $this->content;
    }
    
    /**
     * Returns the value that failed to be validated
     *
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }
}
