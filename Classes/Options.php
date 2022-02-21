<?php

declare(strict_types=1);

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

namespace Neunerlei\Options;

use Neunerlei\Options\Applier\Applier;

class Options
{

    /**
     * The class that is used when the internal option applier is instantiated
     *
     * @var string
     */
    public static $applierClass = Applier::class;

    /**
     * Our internal applier as singleton
     *
     * @var \Neunerlei\Options\Applier\Applier
     */
    protected static $applier;

    /**
     * In general, this does exactly the same as Options::make() but is designed to validate non-array options.
     *
     * An Example:
     * function myFunc($value, $anOption = null){
     *    $anOption = Options::makeSingle("anOption", $anOption, [
     *        "type" => ["string"],
     *        "default" => "foo",
     *    ]);
     *    ...
     * }
     *
     * NOTE: There is one gotcha. As you see in our example we define $anOption as = null in the signature.
     * This will cause the method to use the default value of "foo" if the property is not set. This will not
     * cause issues when not checking for null tho!
     *
     * @param   string       $paramName   The name of the parameter for output purposes
     * @param   mixed        $variable    The variable you want to filter
     * @param   array|mixed  $definition  See Options::make() for a detailed documentation
     * @param   array        $options     See Options::make() for a additional information
     *
     * @return mixed
     */
    public static function makeSingle(string $paramName, $variable, $definition, array $options = [])
    {
        $list   = is_null($variable) ? [] : [$paramName => $variable];
        $result = static::getApplier()->apply($list, [$paramName => $definition], $options);

        return $result[$paramName];
    }

    /**
     * This nifty little helper is used to apply a default definition of options
     * to a given array of options (presumably transferred as a function parameter)
     *
     * An Example:
     * function myFunc($value, array $options = []){
     *    $defaults = [
     *        "foo" => 123,
     *        "bar" => null,
     *    ];
     *    $options = Options::make($options, $defaults);
     *    ...
     * }
     *
     * myFunc("something") => $options will be $defaults
     * myFunc("something", ["foo" => 234]) => $options will be ["foo" => 234, "bar" => null]
     * myFunc("something", ["rumpel" => 234]) => This will cause a Helferlein exception, because the key is not nknown
     * myfunc("something", ["foo" => "rumpel"]) $options will be ["foo" => "rumpel", "bar" => null], because the
     * options were merged
     *
     * IMPORTANT NOTE: When you want to set an array as default value make sure to wrap it in an additional array.
     * Example: $defaults = ["foo" => []] <- This will crash! This will not -> ["foo" => [[]]]
     *
     * Advanced definitions
     * =============================
     * In addition to the simple default values you can also use an array as value in your definitions array.
     * In it, you can set the following options to validate and filter options as you wish.
     *
     * - default (mixed|callable): This is the default value to use when the key in $options is empty.
     * If not set the option key is required! If the default value is a Closure the closure is called,
     * and it's result is used as value.
     * The callback receives $key, $options, $definitionNode {@link \Neunerlei\Options\Applier\Node\Node}
     *
     * - type (string|array): Allows basic type validation of the input. Can either be a string or an array of strings.
     * Possible values are: boolean, bool, true, false, integer, int, double, float, number (int and float) or numeric
     * (both int and float + string numbers), string, resource, null, callable and also class, class-parent and
     * interface names. If multiple values are supplied they will be seen as chained via OR operator.
     *
     * - preFilter (callable): A callback which is called BEFORE the type validation takes place and can be used to
     * cast the incoming value before validating its type.
     *
     * - filter (callable): A callback which is called after the type validation took place and can be used to process
     * a given value before the custom validation begins.
     * The callback receives: $value, $key, $options, $node, $context
     *
     * - validator (callable|string|array): A callback which allows custom validation. Multiple values are allowed:
     *
     *          - callable: Executes a given callable. The function receives: $value, $key, $options, $node, $context.
     *                      If the function returns FALSE the validation is failed.
     *                      If the function returns TRUE the validation is passed.
     *                      If the function returns an array of values, the values will be passed on, and handled
     *                      like an array passed to "validator".
     *                      If the function returns a string, it is considered a custom error message.
     *
     *          - string:   If the given value is a non-callable string, it will be evaluated as regular expression
     *
     *          - array:    A basic validation routine which receives a list of possible values and will check if
     *                      the given value will match at least one of them (OR operator).
     *
     * - children (array): This can be used to apply nested definitions on option trees.
     * The children definition is done exactly the same way as on root level. The children will only
     * be used if the value in $options is an array (or has a default value of an empty array).
     * There are three options on how children will be evaluated:
     *
     *          - assoc:    Allows you to validate a nested, associative array
     *                      Example: ['foo' => ['foo' => 'bar', 'bar' => 'baz]]
     *                      Definition: ['foo' => ['type' => 'array', 'children' =>
     *                              ['foo' => ['type' => 'string'],'bar' => 'baz']]]
     *
     *          - '*':      Allows you to validate a list of associative arrays that all have the same structure
     *                      Example: ['foo' => [['foo' => 'bar'], ['foo' => 'baz'], ['foo' => 'faz']]
     *                      Definition: ['foo' => ['type' => 'array', 'children' =>
     *                              ['*' => ['foo' => ['type' => 'string']]]]]
     *
     *          - '#':      Allows you to validate a list of non-array values
     *                      Example: ['foo' => ['bar', 'baz', 'faz']], or: ['foo' => [123, 234, 4356]]
     *                      Definition: ['foo' => ['type' => 'array', 'children' =>
     *                              ['#' => ['type' => 'string']]]]
     *
     * Boolean flags
     * =============================
     * It is also possible to supply options that have a type of "boolean" as "flags" which means you don't have
     * to supply any values to it.
     *
     * An Example:
     * function myFunc($value, array $options = []){
     *    $defaults = [
     *        "myFlag" => [
     *                "type" => "boolean",
     *                "default" => false
     *          ],
     *        ...
     *    ];
     *    $options = Options::make($options, $defaults);
     *    ...
     * }
     *
     * In action
     * myFunc($foo, ["myFlag"])
     *
     * In this case your $options["myFlag"] will contain a value of TRUE, otherwise it will be FALSE
     *
     * @param   array  $input
     * @param   array  $definition
     * @param   array  $options  Additional options
     *                           - allowUnknown (bool) FALSE: If set to TRUE, unknown keys will be kept in the result
     *                           - ignoreUnknown (bool) FALSE: If set to TRUE, unknown keys will be ignored but removed
     *                           from the result.
     *                           - allowBooleanFlags (bool) TRUE: If set to FALSE it is not allowed to use boolean
     *                           flags in your input array. Useful when validating API inputs
     *
     * @return array
     */
    public static function make(array $input, array $definition, array $options = []): array
    {
        return static::getApplier()->apply($input, $definition, $options);
    }

    /**
     * Returns the singleton instance of our internal option applier
     *
     * @return \Neunerlei\Options\Applier\Applier
     */
    public static function getApplier(): Applier
    {
        if (! empty(static::$applier) && static::$applier instanceof static::$applierClass) {
            return static::$applier;
        }

        return static::$applier = new static::$applierClass();
    }
}
