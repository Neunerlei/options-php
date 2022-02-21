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
 * Last modified: 2022.02.20 at 13:44
 */

declare(strict_types=1);


namespace Neunerlei\Options\Applier\Type;


interface ValueTypes
{
    public const TYPE_INT      = 1;
    public const TYPE_FLOAT    = 2;
    public const TYPE_STRING   = 3;
    public const TYPE_ARRAY    = 4;
    public const TYPE_OBJECT   = 5;
    public const TYPE_RESOURCE = 6;
    public const TYPE_NULL     = 7;
    public const TYPE_NUMBER   = 8;
    public const TYPE_NUMERIC  = 9;
    public const TYPE_TRUE     = 10;
    public const TYPE_FALSE    = 11;
    public const TYPE_CALLABLE = 12;
    public const TYPE_BOOL     = 13;
    public const STRING_TYPE_MAP
                               = [
            'boolean'  => self::TYPE_BOOL,
            'bool'     => self::TYPE_BOOL,
            'int'      => self::TYPE_INT,
            'integer'  => self::TYPE_INT,
            'double'   => self::TYPE_FLOAT,
            'float'    => self::TYPE_FLOAT,
            'string'   => self::TYPE_STRING,
            'array'    => self::TYPE_ARRAY,
            'object'   => self::TYPE_OBJECT,
            'resource' => self::TYPE_RESOURCE,
            'null'     => self::TYPE_NULL,
            'number'   => self::TYPE_NUMBER,
            'numeric'  => self::TYPE_NUMERIC,
            'true'     => self::TYPE_TRUE,
            'false'    => self::TYPE_FALSE,
            'callable' => self::TYPE_CALLABLE,
        ];

}
