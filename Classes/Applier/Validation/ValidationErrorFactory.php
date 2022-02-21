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


use Neunerlei\Options\Applier\Context\Context;
use Neunerlei\Options\Applier\Node\Node;

class ValidationErrorFactory
{
    public function makeMissingRequiredKeyError(Context $context): ValidationError
    {
        return new ValidationError(
            ValidationError::TYPE_MISSING_REQUIRED_KEY,
            'The value at: "' . implode('.', $context->path) . '" is required!',
            $context->path,
            null);
    }
    
    public function makeUnknownKeyError(Context $context, $k, $value, array $nodes): ValidationError
    {
        // If we allow boolean flags we try to make the path a bit more readable
        $readablePath = $context->path;
        if ($context->allowBooleanFlags && is_numeric($k) && is_string($value) && strlen($value) < 100) {
            $lastPathPart = array_pop($readablePath);
            $readablePath[] = $value . ' (' . $lastPathPart . ')';
            $k = $value;
        }
        
        $e = 'Invalid option key: "' . implode('.', $readablePath) . '" given!';
        $alternativeKey = $this->getSimilarKey($nodes, (string)$k);
        if (! empty($alternativeKey)) {
            $e .= ' Did you mean: "' . $alternativeKey . '" instead?';
        }
        
        return new ValidationError(ValidationError::TYPE_UNKNOWN_KEY, $e, $context->path, null);
    }
    
    public function makeValidationFailedError(Context $context, ValidatorResult $result): ValidationError
    {
        $message = null;
        
        switch ($result->getType()) {
            case ValidatorResult::TYPE_INVALID_VALUE:
                $allowedValues = array_map([$this, 'stringifyValue'], $result->getContent());
                $message = 'Invalid value "' . $this->stringifyValue($result->getValue()) . '" ' .
                           '- Only the following values are allowed: "' . implode('", "', $allowedValues) . '"';
                break;
            case ValidatorResult::TYPE_REGEX:
                $message = 'The value did not match the required pattern: "' . $result->getContent() . '"';
                break;
            case ValidatorResult::TYPE_MESSAGE:
                $message = $result->getContent();
                break;
            case ValidatorResult::TYPE_INVALID_TYPE:
                $type = strtolower(gettype($result->getValue()));
                $type = $type === 'object' ? 'Instance of: ' . get_class($result->getValue()) : $type;
                $message = 'Invalid value type "' . $type
                           . '" given; only values with the following types are allowed: "' .
                           implode('" or "', $result->getNode()->types) . '"';
                break;
        }
        
        return new ValidationError(ValidationError::TYPE_VALIDATION_FAILED,
            'Validation failed at: "' . implode('.', $context->path) . '"' .
            (empty($message) ? '' : (' - ' . $message)),
            $context->path,
            $result);
    }
    
    public function makeInvalidChildValueError(Context $context, Node $node, $value): ValidationError
    {
        $e = 'Invalid child at path: ' . implode('.', $context->path)
             . ' it has to be an array but is instead: ' . $this->stringifyValue($value);
        
        return new ValidationError(ValidationError::TYPE_INVALID_CHILD_VALUE, $e, $context->path, null, $node);
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
        if ($value === null) {
            return 'NULL';
        }
        
        if (is_string($value) || is_numeric($value)) {
            return $this->cropString((string)$value);
        }
        
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }
        
        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return $this->cropString((string)$value);
            }
            
            return 'Object of type: ' . get_class($value);
        }
        
        return 'Value of type: ' . gettype($value);
    }
    
    /**
     * Helper to crop a string to the maximum length
     *
     * @param   string  $value
     * @param   int     $maxLength
     *
     * @return string
     */
    protected function cropString(string $value, int $maxLength = 50): string
    {
        $v = trim($value);
        
        if (strlen($v) <= $maxLength) {
            return $v;
        }
        
        return trim(substr($v, 0, $maxLength)) . '...';
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
