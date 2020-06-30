<?php
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
 * Last modified: 2020.02.28 at 20:49
 */

namespace Neunerlei\Options;


class OptionValidationError
{
    
    public const TYPE_UNKNOWN_KEY          = 0;
    public const TYPE_INVALID_TYPE         = 1;
    public const TYPE_VALIDATION_FAILED    = 2;
    public const TYPE_INVALID_VALUE        = 4;
    public const TYPE_MISSING_REQUIRED_KEY = 16;
    public const TYPE_INVALID_CHILD_VALUE  = 32;
    
    /**
     * The type of the validation error
     *
     * @var int
     */
    protected $type;
    
    /**
     * The readable error message based on the given error type
     *
     * @var string
     */
    protected $message;
    
    /**
     * The path through the given array to the failed child
     *
     * @var array
     */
    protected $path;
    
    /**
     * OptionValidationError constructor.
     *
     * @param   int     $type
     * @param   string  $message
     * @param   array   $path
     */
    public function __construct(int $type, string $message, array $path)
    {
        $this->type    = $type;
        $this->message = $message;
        $this->path    = $path;
    }
    
    /**
     * Returns the type of the validation error
     *
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }
    
    /**
     * Returns the readable error message based on the given error type
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }
    
    /**
     * Returns the path through the given array to the failed child
     *
     * @return array
     */
    public function getPath(): array
    {
        return $this->path;
    }
}
