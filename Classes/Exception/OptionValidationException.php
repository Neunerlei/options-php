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
 * Last modified: 2022.02.20 at 16:00
 */

declare(strict_types=1);

namespace Neunerlei\Options\Exception;


class OptionValidationException extends OptionException
{

    /**
     * The list of errors that lead to this exception
     *
     * @var \Neunerlei\Options\Applier\Validation\ValidationError[]
     */
    protected $errors;

    /**
     * OptionValidationException constructor.
     *
     * @param   \Neunerlei\Options\Applier\Validation\ValidationError[]  $errors
     */
    public function __construct(array $errors)
    {
        $this->errors = $errors;
        $message      = 'Errors while validating options: ';
        foreach ($errors as $error) {
            $message .= PHP_EOL . ' -' . $error->getMessage();
        }

        parent::__construct($message);
    }

    /**
     * Returns the list of errors that lead to this exception
     *
     * @return \Neunerlei\Options\Applier\Validation\ValidationError[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
