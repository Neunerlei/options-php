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


namespace Neunerlei\Options\Exception;


use Throwable;

abstract class AbstractPathAwareException extends OptionException
{
    protected $pathErrorMessage = 'An error occurred at: "%s"; ';
    
    /**
     * The path in the node tree to where the exception occurred
     *
     * @var array
     */
    protected $path;
    
    public function __construct($message = '', ?array $path = null, $code = 0, Throwable $previous = null)
    {
        parent::__construct(
            (empty($path) ? '' : (sprintf($this->pathErrorMessage, implode('.', $path)))) .
            $message, $code, $previous);
        $this->path = $path ?? [];
    }
    
    /**
     * Returns the path in the node tree to where the exception occurred
     *
     * @return array
     */
    public function getPath(): array
    {
        return $this->path;
    }
    
}
