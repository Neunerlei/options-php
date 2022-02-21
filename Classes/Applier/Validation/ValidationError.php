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
 * Last modified: 2021.06.09 at 17:01
 */

declare(strict_types=1);

namespace Neunerlei\Options\Applier\Validation;


use Neunerlei\Options\Applier\Node\Node;

class ValidationError
{

    public const TYPE_UNKNOWN_KEY          = 0;
    public const TYPE_VALIDATION_FAILED    = 1;
    public const TYPE_MISSING_REQUIRED_KEY = 2;
    public const TYPE_INVALID_CHILD_VALUE  = 3;

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
     * Additional details about the failed validation that lead to this error
     *
     * @var \Neunerlei\Options\Applier\Validation\ValidatorResult|null
     */
    protected $details;

    /**
     * The node which failed to be validated
     *
     * @var Node|null
     */
    protected $node;

    public function __construct(int $type, string $message, array $path, ?ValidatorResult $details, ?Node $node = null)
    {
        $this->type    = $type;
        $this->message = $message;
        $this->path    = $path;
        $this->details = $details;
        $this->node    = $node;
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

    /**
     * Returns additional details about the failed validation that lead to this error
     *
     * @return \Neunerlei\Options\Applier\Validation\ValidatorResult|null
     */
    public function getDetails(): ?ValidatorResult
    {
        return $this->details;
    }

    /**
     * Returns the node configuration which caused the error.
     * Alternatively returns null if the error was not node related
     *
     * @return \Neunerlei\Options\Applier\Node\Node|null
     */
    public function getNode(): ?Node
    {
        if (isset($this->node)) {
            return $this->node;
        }

        if (isset($this->details)) {
            return $this->details->getNode();
        }

        return null;
    }

}
