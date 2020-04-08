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

namespace Neunerlei\Options;

use Closure;

class OptionApplier {
	public const OPT_ALLOW_UNKNOWN       = 0;
	public const OPT_IGNORE_UNKNOWN      = 1;
	public const OPT_ALLOW_BOOLEAN_FLAGS = 2;
	
	protected const ALLOWED_DEFINITION_KEYS = ["default", "validator", "preFilter", "filter", "type", "children", "values", "required"];
	
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
	
	protected const LIST_TYPE_MAP = [
		"boolean"  => self::TYPE_BOOL,
		"bool"     => self::TYPE_BOOL,
		"int"      => self::TYPE_INT,
		"integer"  => self::TYPE_INT,
		"double"   => self::TYPE_FLOAT,
		"float"    => self::TYPE_FLOAT,
		"string"   => self::TYPE_STRING,
		"array"    => self::TYPE_ARRAY,
		"object"   => self::TYPE_OBJECT,
		"resource" => self::TYPE_RESOURCE,
		"null"     => self::TYPE_NULL,
		"number"   => self::TYPE_NUMBER,
		"numeric"  => self::TYPE_NUMERIC,
		"true"     => self::TYPE_TRUE,
		"false"    => self::TYPE_FALSE,
		"callable" => self::TYPE_CALLABLE,
	];
	
	/**
	 * Can be used in the exact same way as Options::make() is used.
	 *
	 * @param array $input
	 * @param array $definition
	 * @param array $options
	 *
	 * @return array
	 * @throws \Neunerlei\Options\OptionValidationException
	 * @see \Neunerlei\Options\Options::make()
	 */
	public function apply(array $input, array $definition, array $options = []): array {
		
		// Prepare the context
		$context = new OptionApplierContext();
		$context->options = [
			static::OPT_ALLOW_UNKNOWN       => FALSE,
			static::OPT_IGNORE_UNKNOWN      => FALSE,
			static::OPT_ALLOW_BOOLEAN_FLAGS => TRUE,
		];
		if (!empty($options["allowUnknown"]) || in_array("allowUnknown", $options))
			$context->options[static::OPT_ALLOW_UNKNOWN] = TRUE;
		if (!empty($options["ignoreUnknown"]) || in_array("ignoreUnknown", $options))
			$context->options[static::OPT_IGNORE_UNKNOWN] = TRUE;
		if (isset($options["allowBooleanFlags"]) && $options["allowBooleanFlags"] === FALSE)
			$context->options[static::OPT_ALLOW_BOOLEAN_FLAGS] = FALSE;
		
		// Run the recursive applier
		$result = $this->applyInternal($context, $input, $definition);
		
		// Check if there were errors
		if (empty($context->errors)) return $result;
		
		// Show them those errors...
		throw new OptionValidationException($context->errors);
	}
	
	/**
	 * Internal helper to apply the definition recursively including the children
	 *
	 * @param \Neunerlei\Options\OptionApplierContext $context
	 * @param array                                   $list
	 * @param array                                   $definition
	 *
	 * @return array
	 */
	protected function applyInternal(OptionApplierContext $context, array $list, array $definition): array {
		$result = $list;
		$initialPath = $context->path;
		
		// Apply defaults
		$popKey = FALSE;
		foreach ($definition as $k => $def) {
			// Check if we have work to do
			if (array_key_exists($k, $result)) continue;
			
			// Check if this is a boolean flag
			if ($context->options[static::OPT_ALLOW_BOOLEAN_FLAGS] &&
				in_array($k, $result) && is_numeric(array_search($k, $result))) {
				$result[$k] = TRUE;
				unset($result[array_search($k, $result)]);
				continue;
			}
			
			// Prepare path
			if ($popKey) array_pop($context->path);
			$context->path[] = $k;
			$popKey = TRUE;
			
			// Apply the defaults
			$result = $this->applyDefaultsFor($context, $result, $k, $def);
		}
		
		// Reset the path
		$context->path = $initialPath;
		
		// Traverse the list
		$popKey = FALSE;
		foreach ($result as $k => $v) {
			// Prepare path
			if ($popKey) array_pop($context->path);
			$context->path[] = $k;
			$popKey = TRUE;
			
			// Check if we know this key
			if (!array_key_exists($k, $definition)) {
				// Ignore if we keep unknown values
				if ($context->options[static::OPT_ALLOW_UNKNOWN]) continue;
				
				// Remove if we ignore unknown values
				if ($context->options[static::OPT_IGNORE_UNKNOWN]) {
					unset($result[$k]);
					continue;
				}
				
				// Rewrite stuff that looks like boolean flags
				$readablePath = $context->path;
				if ($context->options[static::OPT_ALLOW_BOOLEAN_FLAGS] &&
					is_numeric($k) && is_string($v) && strlen($v) < 100) {
					$lastPathPart = array_pop($readablePath);
					$readablePath[] = $v . " (" . $lastPathPart . ")";
					$k = $v;
				}
				
				// Handle not found key
				$e = "Invalid option key: \"" . implode(".", $readablePath) . "\" given!";
				$alternativeKey = $this->getSimilarKey($definition, $k);
				if (!empty($alternativeKey)) $e .= " Did you mean: \"$alternativeKey\" instead?";
				$context->errors[] = new OptionValidationError(OptionValidationError::TYPE_UNKNOWN_KEY, $e, $context->path);
				continue;
			}
			
			// Prepare the definition
			$def = $this->prepareDefinition($context, $definition[$k]);
			
			// Apply pre-filter
			$v = $this->applyPreFilter($context, $k, $v, $def, $result);
			
			// Check type-validation
			if (!$this->checkTypeValidation($context, $v, $def)) continue;
			
			// Apply filter
			$v = $this->applyFilter($context, $k, $v, $def, $result);
			
			// Check custom validation
			if (!$this->checkCustomValidation($context, $k, $v, $def, $result)) continue;
			
			// Check value validation
			if (!$this->checkValueValidation($context, $v, $def)) continue;
			
			// Handle children
			if (is_array($v) && isset($def["children"])) {
				// Check if we should handle a list of children
				if (isset($def["children"]["*"]) && is_array($def["children"]["*"])) {
					$vFiltered = [];
					foreach ($v as $_k => $_v) {
						// Check if the child is an array before trying to nest the applier
						if (!is_array($_v)) {
							$path = $context->path;
							$path[] = $_k;
							$e = "Invalid child at path: " . implode(".", $path) . " it has to be an array but is instead a " . gettype($_v);
							$context->errors[] = new OptionValidationError(OptionValidationError::TYPE_INVALID_CHILD_VALUE, $e, $path);
							continue;
						}
						
						// Follow the rabbit hole
						$context->path[] = $_k;
						$vFiltered[$_k] = $this->applyInternal($context, $_v, $def["children"]["*"]);
						array_pop($context->path);
					}
					$v = $vFiltered;
				} else {
					// Handle a associative child definition
					$v = $this->applyInternal($context, $v, $def["children"]);
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
	 * @param OptionApplierContext $context
	 * @param array                $list The list to add the default value to
	 * @param mixed                $k    The key to add the default value for
	 * @param mixed                $def  The definition to read the default value from
	 *
	 * @return array
	 */
	protected function applyDefaultsFor(OptionApplierContext $context, array $list, $k, $def): array {
		// Prepare the definition
		$def = $this->prepareDefinition($context, $def);
		
		// Check if we have a default value
		if (!array_key_exists("default", $def)) {
			$e = "The option key: \"" . implode(".", $context->path) . "\" is required!";
			$context->errors[] = new OptionValidationError(
				OptionValidationError::TYPE_MISSING_REQUIRED_KEY, $e, $context->path);
			return $list;
		}
		
		// Apply the default value
		if ($def["default"] instanceof Closure)
			$list[$k] = call_user_func($def["default"], $k, $list, $def, $context->path);
		else $list[$k] = $def["default"];
		return $list;
	}
	
	/**
	 * Internal helper which is used to convert the given definition into an array.
	 * It will also validate that only allowed keys are given
	 *
	 * @param OptionApplierContext $context
	 * @param mixed                $def Either a value or an array of the definition
	 *
	 * @return array
	 * @throws \Neunerlei\Options\InvalidOptionDefinitionException
	 */
	protected function prepareDefinition(OptionApplierContext $context, $def): array {
		// Make sure we don't recreate definitions if we iterate a numeric list of recurring elements
		$cachePath = $context->path;
		$pathLength = count($context->path);
		if (isset($cachePath[$pathLength - 2]) && $cachePath[$pathLength - 2] === "*")
			$cachePath = array_slice($cachePath, 0, $pathLength - 2);
		$cacheKey = implode(".", $cachePath);
		
		// Serve cache value if possible
		if (isset($context->preparedDefinitions[$cacheKey])) return $context->preparedDefinitions[$cacheKey];
		
		// Default simple definition -> The value is the default value
		if (!is_array($def)) $def = ["default" => $def];
		
		// Array simple definition -> The first value in the array is the default value
		else if (is_array($def) && count($def) === 1 && is_numeric(key($def)) && is_array(reset($def)))
			$def = ["default" => reset($def)];
		
		// Failed array simple definition
		else if (is_array($def) && empty($def))
			throw new InvalidOptionDefinitionException("Definition error at: \"" . implode(".", $context->path) .
				"\"; An empty array was given as definition. If you want an array as default value make sure to pass it like: " .
				"\"key\" => [[]] or like \"key\" => [\"default\" => []]");
		
		// Remove default for required keys
		if (!empty($def["required"])) unset($def["default"]);
		
		// Validate that all keys in the definition are valid
		if (is_array($def) && count($unknownConfig = array_diff(array_keys($def), static::ALLOWED_DEFINITION_KEYS)) > 0)
			throw new InvalidOptionDefinitionException(
				"Definition error at: \"" . implode(".", $context->path) . "\"; found invalid keys: " .
				implode(", ", $unknownConfig) . " - Make sure to wrap arrays in definitions in an outer array!");
		
		// Done
		return $context->preparedDefinitions[$cacheKey] = $def;
	}
	
	/**
	 * Internal helper to apply the given pre-filter callback
	 *
	 * @param OptionApplierContext $context
	 * @param mixed                $k    The key of the value to filter for the callback
	 * @param mixed                $v    The value to filter
	 * @param array                $def  The definition of the value to filter
	 * @param array                $list The whole list for the callback
	 *
	 * @return mixed
	 * @throws \Neunerlei\Options\InvalidOptionDefinitionException
	 */
	protected function applyPreFilter(OptionApplierContext $context, $k, $v, array $def, array $list) {
		// Ignore if there is nothing to do
		if (empty($def["preFilter"])) return $v;
		
		// Validate config
		if (!is_callable($def["preFilter"]))
			throw new InvalidOptionDefinitionException(
				"Definition error at: " . implode(".", $context->path) . " - The preFilter is not callable!");
		
		// Apply filter
		return call_user_func($def["preFilter"], $v, $k, $list, $def, $context->path);
	}
	
	/**
	 * Internal helper to check the "type" validation of the definition
	 *
	 * @param OptionApplierContext $context
	 * @param mixed                $v   The value to validate
	 * @param array                $def The definition to validate with
	 *
	 * @return bool
	 * @throws \Neunerlei\Options\InvalidOptionDefinitionException
	 */
	protected function checkTypeValidation(OptionApplierContext $context, $v, array $def): bool {
		// Skip, if there is no validation required
		if (empty($def["type"])) return TRUE;
		
		// Resolve shorthand
		if (is_string($def["type"])) $def["type"] = [$def["type"]];
		
		// Validate input
		if (!is_array($def["type"]))
			throw new InvalidOptionDefinitionException(
				"Definition error at: \"" . implode(".", $context->path) . "\" - Type definitions have to be an array or a string!");
		
		// Build internal list
		$typeList = array_unique(array_filter(array_map(function ($type) {
			$typeLc = trim(strtolower($type));
			if (isset(static::LIST_TYPE_MAP[$typeLc])) return static::LIST_TYPE_MAP[$typeLc];
			return $type;
		}, $def["type"])));
		
		// Validate the value types
		if (!$this->validateTypesOf($v, $typeList)) {
			$type = strtolower(gettype($v));
			if ($type === "object") $type = "Instance of: " . get_class($v);
			$e = "Invalid value type at: \"" . implode(".", $context->path) . "\" given; Allowed types: \"" .
				implode("\" or \"", array_values($def["type"])) . "\". Given type: \"" . $type . "\"!";
			$context->errors[] = new OptionValidationError(OptionValidationError::TYPE_INVALID_TYPE, $e, $context->path);
			array_pop($context->path);
			return FALSE;
		}
		return TRUE;
	}
	
	/**
	 * Internal helper to apply the given filter callback
	 *
	 * @param OptionApplierContext $context
	 * @param mixed                $k    The key of the value to filter for the callback
	 * @param mixed                $v    The value to filter
	 * @param array                $def  The definition of the value to filter
	 * @param array                $list The whole list for the callback
	 *
	 * @return mixed
	 * @throws \Neunerlei\Options\InvalidOptionDefinitionException
	 */
	protected function applyFilter(OptionApplierContext $context, $k, $v, array $def, array $list) {
		// Ignore if there is nothing to do
		if (empty($def["filter"])) return $v;
		
		// Validate config
		if (!is_callable($def["filter"]))
			throw new InvalidOptionDefinitionException(
				"Definition error at: \"" . implode(".", $context->path) . "\" - The filter is not callable!");
		
		// Apply filter
		return call_user_func($def["filter"], $v, $k, $list, $def, $context->path);
	}
	
	/**
	 * Internal helper to apply the given, custom validation for a given value
	 *
	 * @param OptionApplierContext $context
	 * @param mixed                $k    The key of the value to validate for the callback
	 * @param mixed                $v    The value to validate
	 * @param array                $def  The definition to validate with
	 * @param array                $list The whole list for the callback
	 *
	 * @return bool
	 * @throws \Neunerlei\Options\InvalidOptionDefinitionException
	 */
	protected function checkCustomValidation(OptionApplierContext $context, $k, $v, array &$def, array $list): bool {
		// Skip, if there is no validation required
		if (empty($def["validator"])) return TRUE;
		
		// Check if validator can be called
		if (!is_callable($def["validator"]))
			throw new InvalidOptionDefinitionException(
				"Definition error at: \"" . implode(".", $context->path) . "\" - The validator is not callable!");
		
		// Call the validator
		$validatorResult = call_user_func($def["validator"], $v, $k, $list, $def, $context->path);
		if ($validatorResult === TRUE) return TRUE;
		
		// Hand over to the value validation
		if (is_array($validatorResult)) {
			$def["values"] = $validatorResult;
			return TRUE;
		}
		
		// Create the error message
		if (!is_string($validatorResult)) $e = "Invalid option: \"" . implode(".", $context->path) . "\" given!";
		else $e = "Validation failed at: \"" . implode(".", $context->path) . "\" - " . $validatorResult;
		$context->errors[] = new OptionValidationError(OptionValidationError::TYPE_VALIDATION_FAILED, $e, $context->path);
		return FALSE;
	}
	
	/**
	 * Internal helper to check the "value" validation of the definition
	 *
	 * @param OptionApplierContext $context
	 * @param mixed                $v   The value to validate
	 * @param array                $def The definition to validate with
	 *
	 * @return bool
	 * @throws \Neunerlei\Options\InvalidOptionDefinitionException
	 */
	protected function checkValueValidation(OptionApplierContext $context, $v, array $def): bool {
		// Ignore if there is nothing to do
		if (empty($def["values"])) return TRUE;
		
		// Validate config
		if (!is_array($def["values"]))
			throw new InvalidOptionDefinitionException(
				"Definition error at: \"" . implode(".", $context->path) . "\" - The values to validate should be an array!");
		
		// Check if the value is in the list
		if (in_array($v, $def["values"], TRUE)) return TRUE;
		
		// Build error message
		$allowedValues = array_map([$this, "stringifyValue"], $def["values"]);
		$e = "Validation failed at: \"" . implode(".", $context->path) .
			"\" - Only the following values are allowed: \"" . implode("\", \"", $allowedValues) . "\"";
		$context->errors[] = new OptionValidationError(OptionValidationError::TYPE_INVALID_VALUE, $e, $context->path);
		return FALSE;
	}
	
	/**
	 * Internal helper which can be used to convert any value into a string representation
	 *
	 * @param mixed $value The value to convert into a string version
	 *
	 * @return string
	 */
	protected function stringifyValue($value): string {
		if (is_string($value) || is_numeric($value)) return (string)$value;
		if (is_object($value)) {
			if (method_exists($value, "__toString")) {
				$s = (string)$value;
				$sCropped = substr($s, 0, 50);
				if (strlen($s) === 50) $sCropped .= "...";
				return $sCropped;
			}
			return "Object of type: " . get_class($value);
		}
		return "Value of type: " . gettype($value);
	}
	
	/**
	 * Internal helper which validates the type of a given value against a list of valid types
	 *
	 * @param mixed $value the value to validate
	 * @param array $types The list of types to validate $value against
	 *
	 * @return bool
	 */
	protected function validateTypesOf($value, array $types): bool {
		// Check if we can validate that type
		$typeString = strtolower(gettype($value));
		if (!isset(static::LIST_TYPE_MAP[$typeString])) return FALSE;
		$type = static::LIST_TYPE_MAP[$typeString];
		
		// Simple lookup
		if (in_array($type, $types)) return TRUE;
		
		// Object lookup
		if ($type === static::TYPE_OBJECT) {
			if (in_array(get_class($value), $types)) return TRUE;
			if (count(array_intersect(class_parents($value), array_values($types))) > 0) return TRUE;
			if (count(array_intersect(class_implements($value), array_values($types))) > 0) return TRUE;
			
			// Closure callable lookup
			if (in_array(static::TYPE_CALLABLE, $types) && $value instanceof Closure) return TRUE;
			return FALSE;
		}
		
		// Boolean lookup
		if ($type === static::TYPE_BOOL) {
			if (in_array(static::TYPE_BOOL, $types)) return TRUE;
			if ($value === TRUE && in_array(static::TYPE_TRUE, $types)) return TRUE;
			else if (in_array(static::TYPE_FALSE, $types)) return TRUE;
		}
		
		// Numeric lookup
		if (is_numeric($value) && in_array(static::TYPE_NUMERIC, $types)) return TRUE;
		
		// Number lookup (Non-string)
		if ($type === static::TYPE_INT || $type === static::TYPE_FLOAT && in_array(static::TYPE_NUMBER, $types)) return TRUE;
		
		// Callable lookup
		if (is_callable($value) && in_array(static::TYPE_CALLABLE, $types)) return TRUE;
		
		// Nope...
		return FALSE;
	}
	
	/**
	 * Searches the most similar key to the given needle from the haystack
	 *
	 * @param array  $haystack The array to search similar keys in
	 * @param string $needle   The needle to search similar keys for
	 *
	 * @return string|null The best matching key or null if the given haystack was empty
	 */
	protected function getSimilarKey(array $haystack, string $needle) {
		// Check if the needle exists
		if (isset($haystack[$needle])) return $needle;
		
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
		if (empty($similarKeys)) return NULL;
		return array_pop($similarKeys);
	}
	
}