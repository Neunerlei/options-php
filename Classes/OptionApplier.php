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

namespace Neunerlei\Options;

use Closure;

class OptionApplier
{
    public const OPT_ALLOW_UNKNOWN       = 0;
    public const OPT_IGNORE_UNKNOWN      = 1;
    public const OPT_ALLOW_BOOLEAN_FLAGS = 2;

    protected const ALLOWED_DEFINITION_KEYS
        = [
            'default',
            'validator',
            'preFilter',
            'filter',
            'type',
            'children',
            'values',
            'required',
        ];

    protected const TYPE_INT      = 1;
    protected const TYPE_FLOAT    = 2;
    protected const TYPE_STRING   = 3;
    protected const TYPE_ARRAY    = 4;
    protected const TYPE_OBJECT   = 5;
    protected const TYPE_RESOURCE = 6;
    protected const TYPE_NULL     = 7;
    protected const TYPE_NUMBER   = 8;
    protected const TYPE_NUMERIC  = 9;
    protected const TYPE_TRUE     = 10;
    protected const TYPE_FALSE    = 11;
    protected const TYPE_CALLABLE = 12;
    protected const TYPE_BOOL     = 13;

    protected const LIST_TYPE_MAP
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

    /**
     * Can be used in the exact same way as Options::make() is used.
     *
     * @param   array  $input
     * @param   array  $definition
     * @param   array  $options
     *
     * @return array
     * @throws \Neunerlei\Options\OptionValidationException
     * @see \Neunerlei\Options\Options::make()
     */
    public function apply(array $input, array $definition, array $options = []): array
    {
        // Prepare the context
        $context          = new OptionApplierContext();
        $context->options = [
            static::OPT_ALLOW_UNKNOWN       => false,
            static::OPT_IGNORE_UNKNOWN      => false,
            static::OPT_ALLOW_BOOLEAN_FLAGS => true,
        ];
        if (! empty($options['allowUnknown']) || in_array('allowUnknown', $options, true)) {
            $context->options[static::OPT_ALLOW_UNKNOWN] = true;
        }
        if (! empty($options['ignoreUnknown']) || in_array('ignoreUnknown', $options, true)) {
            $context->options[static::OPT_IGNORE_UNKNOWN] = true;
        }
        if (isset($options['allowBooleanFlags']) && $options['allowBooleanFlags'] === false) {
            $context->options[static::OPT_ALLOW_BOOLEAN_FLAGS] = false;
        }

        // Run the recursive applier
        $result = $this->applyInternal($context, $input, $definition);

        // Check if there were errors
        if (empty($context->errors)) {
            return $result;
        }

        // Show them those errors...
        throw new OptionValidationException($context->errors);
    }

    /**
     * Internal helper to apply the definition recursively including the children
     *
     * @param   \Neunerlei\Options\OptionApplierContext  $context
     * @param   array                                    $list
     * @param   array                                    $definition
     *
     * @return array
     */
    protected function applyInternal(OptionApplierContext $context, array $list, array $definition): array
    {
        $result      = $list;
        $initialPath = $context->path;

        // Apply defaults
        $popKey             = false;
        $definitionPrepared = [];
        foreach ($definition as $k => $def) {
            // Prepare path
            if ($popKey) {
                array_pop($context->path);
            }
            $context->path[] = $k;
            $popKey          = true;

            // Prepare the definition
            $definitionPrepared[$k] = $def = $this->prepareDefinition($context, $def);

            // Check if this is a boolean flag
            if ($context->options[static::OPT_ALLOW_BOOLEAN_FLAGS] && in_array($k, $result, true)
                && is_numeric(($flagKey = array_search($k, $result, true)))) {
                $result[$k] = $result[$k] ?? true;
                unset($result[$flagKey]);
                continue;
            }

            // Check if we have work to do
            if (array_key_exists($k, $result)) {
                continue;
            }

            // Apply the defaults
            $result = $this->applyDefaultsFor($context, $result, $k, $def);
        }

        // Update the definition with the prepared variant
        $definition = $definitionPrepared;
        unset($definitionPrepared);

        // Reset the path
        $context->path = $initialPath;

        // Traverse the list
        $popKey = false;
        foreach ($result as $k => $v) {
            // Prepare path
            if ($popKey) {
                array_pop($context->path);
            }
            $context->path[] = $k;
            $popKey          = true;

            // Check if we know this key
            if (! array_key_exists($k, $definition)) {
                // Ignore if we keep unknown values
                if ($context->options[static::OPT_ALLOW_UNKNOWN]) {
                    continue;
                }

                // Remove if we ignore unknown values
                if ($context->options[static::OPT_IGNORE_UNKNOWN]) {
                    unset($result[$k]);
                    continue;
                }

                // Rewrite stuff that looks like boolean flags
                $readablePath = $context->path;
                if ($context->options[static::OPT_ALLOW_BOOLEAN_FLAGS] && is_numeric($k) && is_string($v)
                    && strlen($v) < 100) {
                    $lastPathPart   = array_pop($readablePath);
                    $readablePath[] = $v . ' (' . $lastPathPart . ')';
                    $k              = $v;
                }

                // Handle not found key
                $e              = 'Invalid option key: "' . implode('.', $readablePath) . '" given!';
                $alternativeKey = $this->getSimilarKey($definition, (string)$k);
                if (! empty($alternativeKey)) {
                    $e .= " Did you mean: \"$alternativeKey\" instead?";
                }
                $context->errors[] = new OptionValidationError(OptionValidationError::TYPE_UNKNOWN_KEY, $e,
                    $context->path);
                continue;
            }

            // Get the definition
            $def = $definition[$k];

            // Apply pre-filter
            if (isset($def['preFilter'])) {
                $v = $this->applyPreFilter($context, $k, $v, $def, $result);
            }

            // Check type-validation
            if (isset($def['type']) && ! $this->checkTypeValidation($context, $v, $def)) {
                continue;
            }

            // Apply filter
            if (isset($def['filter'])) {
                $v = $this->applyFilter($context, $k, $v, $def, $result);
            }

            // Check custom validation
            if (isset($def['validator'])
                && ! $this->checkCustomValidation($context, $k, $v, $def, $result)) {
                continue;
            }

            // Check value validation
            if (isset($def['values']) && ! $this->checkValueValidation($context, $v, $def)) {
                continue;
            }

            // Handle children
            if (isset($def['children']) && is_array($v)) {
                // Check if we should handle a list of children
                if (isset($def['children']['*']) && is_array($def['children']['*'])) {
                    $vFiltered = [];
                    foreach ($v as $_k => $_v) {
                        // Check if the child is an array before trying to nest the applier
                        if (! is_array($_v)) {
                            $path   = $context->path;
                            $path[] = $_k;
                            $e      = 'Invalid child at path: ' . implode('.', $path)
                                      . ' it has to be an array but is instead a ' . gettype($_v);
                            $context->errors[]
                                    = new OptionValidationError(OptionValidationError::TYPE_INVALID_CHILD_VALUE, $e,
                                $path);
                            continue;
                        }

                        // Follow the rabbit hole
                        $context->path[] = $_k;
                        $vFiltered[$_k]  = $this->applyInternal($context, $_v, $def['children']['*']);
                        array_pop($context->path);
                    }
                    $v = $vFiltered;
                } else {
                    // Handle as associative child definition
                    $v = $this->applyInternal($context, $v, $def['children']);
                }
            }

            // Add the value to the result
            $result[$k] = $v;
        }

        // Reset the path
        $context->path = $initialPath;

        // Done
        return $result;
    }

    /**
     * Is called to apply the default values for a missing key in the given $list
     *
     * @param   OptionApplierContext  $context
     * @param   array                 $list  The list to add the default value to
     * @param   mixed                 $k     The key to add the default value for
     * @param   mixed                 $def   The definition to read the default value from
     *
     * @return array
     */
    protected function applyDefaultsFor(OptionApplierContext $context, array $list, $k, $def): array
    {
        // Check if we have a default value
        if (! array_key_exists('default', $def)) {
            $e                 = 'The option key: "' . implode('.', $context->path) . '" is required!';
            $context->errors[] = new OptionValidationError(
                OptionValidationError::TYPE_MISSING_REQUIRED_KEY, $e, $context->path);

            return $list;
        }

        // Apply the default value
        if ($def['default'] instanceof Closure) {
            $list[$k] = call_user_func($def['default'], $k, $list, $def, $context->path);
        } else {
            $list[$k] = $def['default'];
        }

        return $list;
    }

    /**
     * Internal helper which is used to convert the given definition into an array.
     * It will also validate that only allowed keys are given
     *
     * @param   OptionApplierContext  $context
     * @param   mixed                 $def  Either a value or an array of the definition
     *
     * @return array
     * @throws \Neunerlei\Options\InvalidOptionDefinitionException
     */
    protected function prepareDefinition(OptionApplierContext $context, $def): array
    {
        if (! is_array($def)) {
            // Default simple definition -> The value is the default value
            $def = ['default' => $def];
        } elseif (count($def) === 1 && is_numeric(key($def)) && is_array(($firstDef = reset($def)))) {
            // Array simple definition -> The first value in the array is the default value
            $def = ['default' => $firstDef];
            // @codeCoverageIgnoreStart
        } elseif (empty($def)) {
            // @codeCoverageIgnoreEnd
            // Failed array simple definition
            throw new InvalidOptionDefinitionException(
                'Definition error at: "' . implode('.', $context->path) .
                '"; An empty array was given as definition. If you want an array as default value make sure to ' .
                ' pass it like: ' . '"key" => [[]] or like "key" => ["default" => []]');
        }

        // Remove default for required keys
        if (! empty($def['required'])) {
            unset($def['default']);
        }

        // Validate that all keys in the definition are valid
        if (is_array($def) && ! empty($unknownConfig = array_diff(array_keys($def), static::ALLOWED_DEFINITION_KEYS))) {
            throw new InvalidOptionDefinitionException(
                'Definition error at: "' . implode('.', $context->path) . '"; found invalid keys: ' .
                implode(', ', $unknownConfig) . ' - Make sure to wrap arrays in definitions in an outer array!');
        }

        // Done
        return $def;
    }

    /**
     * Internal helper to apply the given pre-filter callback
     *
     * @param   OptionApplierContext  $context
     * @param   mixed                 $k     The key of the value to filter for the callback
     * @param   mixed                 $v     The value to filter
     * @param   array                 $def   The definition of the value to filter
     * @param   array                 $list  The whole list for the callback
     *
     * @return mixed
     * @throws \Neunerlei\Options\InvalidOptionDefinitionException
     */
    protected function applyPreFilter(OptionApplierContext $context, $k, $v, array $def, array $list)
    {
        // Validate config
        if (! is_callable($def['preFilter'])) {
            throw new InvalidOptionDefinitionException(
                'Definition error at: ' . implode('.', $context->path) . ' - The preFilter is not callable!');
        }

        // Apply filter
        return call_user_func($def['preFilter'], $v, $k, $list, $def, $context->path);
    }

    /**
     * Internal helper to check the "type" validation of the definition
     *
     * @param   OptionApplierContext  $context
     * @param   mixed                 $v    The value to validate
     * @param   array                 $def  The definition to validate with
     *
     * @return bool
     * @throws \Neunerlei\Options\InvalidOptionDefinitionException
     */
    protected function checkTypeValidation(OptionApplierContext $context, $v, array $def): bool
    {
        // Validate input
        if (! is_array($def['type'])) {
            // Resolve shorthand
            if (is_string($def['type'])) {
                $def['type'] = [$def['type']];
            } else {
                throw new InvalidOptionDefinitionException(
                    'Definition error at: "' . implode('.', $context->path)
                    . '" - Type definitions have to be an array of strings, or a single string!');
            }
        }

        // Build internal list
        $typeList = array_flip(array_map(static function ($type) use ($context) {
            if (! is_string($type)) {
                throw new InvalidOptionDefinitionException(
                    'Definition error at: "' . implode('.', $context->path)
                    . '" - Type definitions have to be an array of strings, or a single string!');
            }

            return static::LIST_TYPE_MAP[$type] ?? $type;
        }, $def['type']));

        // Validate the value types
        if (! $this->validateTypesOf($v, $typeList)) {
            $type = strtolower(gettype($v));
            if ($type === 'object') {
                $type = 'Instance of: ' . get_class($v);
            }
            $e                 = 'Invalid value type at: "' . implode('.', $context->path)
                                 . '" given; Allowed types: "' .
                                 implode('" or "', $def['type']) . '". Given type: "' . $type . '"!';
            $context->errors[] = new OptionValidationError(OptionValidationError::TYPE_INVALID_TYPE, $e,
                $context->path);
            array_pop($context->path);

            return false;
        }

        return true;
    }

    /**
     * Internal helper to apply the given filter callback
     *
     * @param   OptionApplierContext  $context
     * @param   mixed                 $k     The key of the value to filter for the callback
     * @param   mixed                 $v     The value to filter
     * @param   array                 $def   The definition of the value to filter
     * @param   array                 $list  The whole list for the callback
     *
     * @return mixed
     * @throws \Neunerlei\Options\InvalidOptionDefinitionException
     */
    protected function applyFilter(OptionApplierContext $context, $k, $v, array $def, array $list)
    {
        // Validate config
        if (! is_callable($def['filter'])) {
            throw new InvalidOptionDefinitionException(
                'Definition error at: "' . implode('.', $context->path) . '" - The filter is not callable!');
        }

        // Apply filter
        return call_user_func($def['filter'], $v, $k, $list, $def, $context->path);
    }

    /**
     * Internal helper to apply the given, custom validation for a given value
     *
     * @param   OptionApplierContext  $context
     * @param   mixed                 $k     The key of the value to validate for the callback
     * @param   mixed                 $v     The value to validate
     * @param   array                 $def   The definition to validate with
     * @param   array                 $list  The whole list for the callback
     *
     * @return bool
     * @throws \Neunerlei\Options\InvalidOptionDefinitionException
     */
    protected function checkCustomValidation(OptionApplierContext $context, $k, $v, array &$def, array $list): bool
    {
        // Check if validator can be called
        if (! is_callable($def['validator'])) {
            throw new InvalidOptionDefinitionException(
                'Definition error at: "' . implode('.', $context->path) . '" - The validator is not callable!');
        }

        // Call the validator
        $validatorResult = call_user_func($def['validator'], $v, $k, $list, $def, $context->path);
        if ($validatorResult === true) {
            return true;
        }

        // Hand over to the value validation
        if (is_array($validatorResult)) {
            $def['values'] = $validatorResult;

            return true;
        }

        // Create the error message
        if (! is_string($validatorResult)) {
            $e = 'Invalid option: "' . implode('.', $context->path) . '" given!';
        } else {
            $e = 'Validation failed at: "' . implode('.', $context->path) . '" - ' . $validatorResult;
        }
        $context->errors[] = new OptionValidationError(OptionValidationError::TYPE_VALIDATION_FAILED, $e,
            $context->path);

        return false;
    }

    /**
     * Internal helper to check the "value" validation of the definition
     *
     * @param   OptionApplierContext  $context
     * @param   mixed                 $v    The value to validate
     * @param   array                 $def  The definition to validate with
     *
     * @return bool
     * @throws \Neunerlei\Options\InvalidOptionDefinitionException
     */
    protected function checkValueValidation(OptionApplierContext $context, $v, array $def): bool
    {
        // Validate config
        if (! is_array($def['values'])) {
            throw new InvalidOptionDefinitionException(
                'Definition error at: "' . implode('.', $context->path)
                . '" - The values to validate should be an array!');
        }

        // Check if the value is in the list
        if (in_array($v, $def['values'], true)) {
            return true;
        }

        // Build error message
        $allowedValues     = array_map([$this, 'stringifyValue'], $def['values']);
        $e                 = 'Validation failed at: "' . implode('.', $context->path) .
                             '" - Only the following values are allowed: "' . implode('", "', $allowedValues)
                             . '"';
        $context->errors[] = new OptionValidationError(OptionValidationError::TYPE_INVALID_VALUE, $e, $context->path);

        return false;
    }

    /**
     * Internal helper which can be used to convert any value into a string representation
     *
     * @param   mixed  $value  The value to convert into a string version
     *
     * @return string
     */
    protected function stringifyValue($value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return (string)$value;
        }
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }
        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                $s        = (string)$value;
                $sCropped = substr($s, 0, 50);
                if (strlen($s) === 50) {
                    $sCropped .= '...';
                }

                return $sCropped;
            }

            return 'Object of type: ' . get_class($value);
        }

        return 'Value of type: ' . gettype($value);
    }

    /**
     * Internal helper which validates the type of a given value against a list of valid types
     *
     * @param   mixed  $value  the value to validate
     * @param   array  $types  The list of types to validate $value against
     *
     * @return bool
     */
    protected function validateTypesOf($value, array $types): bool
    {
        // Check if we can validate that type
        $typeString = strtolower(gettype($value));
        if (! isset(static::LIST_TYPE_MAP[$typeString])) {
            return false;
        }
        $type = static::LIST_TYPE_MAP[$typeString];

        // Simple lookup
        if (isset($types[$type])) {
            return true;
        }

        // Object lookup
        if ($type === static::TYPE_OBJECT) {
            if (isset($types[get_class($value)])) {
                return true;
            }
            if (! empty(array_intersect(class_parents($value), array_keys($types)))) {
                return true;
            }
            if (! empty(array_intersect(class_implements($value), array_keys($types)))) {
                return true;
            }

            // Closure callable lookup
            if (isset($types[static::TYPE_CALLABLE]) && $value instanceof Closure) {
                return true;
            }

            return false;
        }

        // Boolean lookup
        if ($type === static::TYPE_BOOL) {
            if ($value === true && isset($types[static::TYPE_TRUE])) {
                return true;
            }

            if (isset($types[static::TYPE_FALSE])) {
                return true;
            }
        }

        // Number lookup (Non-string)
        if (($type === static::TYPE_INT || $type === static::TYPE_FLOAT) && isset($types[static::TYPE_NUMBER])) {
            return true;
        }

        // Numeric lookup (Potential String)
        if (isset($types[static::TYPE_NUMERIC]) && is_numeric($value)) {
            return true;
        }

        // Callable lookup
        if (isset($types[static::TYPE_CALLABLE]) && is_callable($value)) {
            return true;
        }

        // Nope...
        return false;
    }

    /**
     * Searches the most similar key to the given needle from the haystack
     *
     * @param   array   $haystack  The array to search similar keys in
     * @param   string  $needle    The needle to search similar keys for
     *
     * @return string|null The best matching key or null if the given haystack was empty
     */
    protected function getSimilarKey(array $haystack, string $needle): ?string
    {
        // Check if the needle exists
        if (isset($haystack[$needle])) {
            return $needle;
        }

        // Generate alternative keys
        $alternativeKeys = array_keys($haystack);

        // Search for a similar key
        $similarKeys = [];
        foreach ($alternativeKeys as $alternativeKey) {
            similar_text(strtolower($needle), strtolower($alternativeKey), $percent);
            $similarKeys[(int)ceil($percent)] = $alternativeKey;
        }
        ksort($similarKeys);

        // Check for empty keys
        if (empty($similarKeys)) {
            return null;
        }

        return array_pop($similarKeys);
    }

}
