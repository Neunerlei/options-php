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
 * Last modified: 2020.02.27 at 10:57
 */

declare(strict_types=1);

namespace Neunerlei\Options\Applier\Context;

class Context
{
    /**
     * The list of errors that occurred while running the applier
     *
     * @var \Neunerlei\Options\Applier\Validation\ValidationError[]
     */
    public $errors = [];

    /**
     * Defines the path through the given input array
     *
     * @var array
     */
    public $path = [];

    /**
     * True if the "allowUnknown" option was set to true
     *
     * @var bool
     */
    public $allowUnknown = false;

    /**
     * True if the "ignoreUnknown" option was set to true
     *
     * @var bool
     */
    public $ignoreUnknown = false;

    /**
     * True as long "allowBooleanFlags" was not set to false
     *
     * @var bool
     */
    public $allowBooleanFlags = true;
}
