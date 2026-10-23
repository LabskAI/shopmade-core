<?php

namespace App\Core\Mcp;

/**
 * Validates tool and prompt arguments against the JSON Schema the client was shown, so the published
 * schema is the single source of truth. Supports the subset the tools use: object (properties, required,
 * minProperties), string (minLength, maxLength, pattern, enum), integer and number (minimum, maximum),
 * boolean and array (items, minItems, maxItems, uniqueItems). Strings are trimmed, numeric strings are
 * accepted for integers and numbers, null counts as absent and undeclared keys are dropped.
 */
class ArgumentValidator
{
    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     *
     * @throws McpError
     */
    public function validate(array $schema, array $arguments): array
    {
        $errors = [];
        $validated = $this->object($schema, $arguments, '', $errors);

        if ($errors !== []) {
            throw McpError::invalidParams(
                'Invalid arguments: '.implode('; ', $errors).'.',
                'Fix the arguments to match the input schema from tools/list and call again.',
                ['errors' => $errors],
            );
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $value
     * @param  list<string>  $errors
     * @return array<string, mixed>
     */
    private function object(array $schema, array $value, string $path, array &$errors): array
    {
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        $required = is_array($schema['required'] ?? null) ? $schema['required'] : [];
        $validated = [];

        foreach ($properties as $name => $propertySchema) {
            $field = $path === '' ? (string) $name : "{$path}.{$name}";

            if (! array_key_exists($name, $value) || $value[$name] === null) {
                if (in_array($name, $required, true)) {
                    $errors[] = "{$field} is required";
                }

                continue;
            }

            $checked = $this->value(is_array($propertySchema) ? $propertySchema : [], $value[$name], $field, $errors);

            if ($checked !== null) {
                $validated[$name] = $checked;
            }
        }

        $minProperties = (int) ($schema['minProperties'] ?? 0);

        if ($minProperties > 0 && count($validated) < $minProperties && $errors === []) {
            $errors[] = ($path === '' ? 'arguments' : $path).' needs at least '.$minProperties.' of: '.implode(', ', array_keys($properties));
        }

        return $validated;
    }

    /**
     * The normalized value, or null after recording an error.
     *
     * @param  array<string, mixed>  $schema
     * @param  list<string>  $errors
     */
    private function value(array $schema, mixed $value, string $field, array &$errors): mixed
    {
        $checked = match ($schema['type'] ?? null) {
            'string' => $this->string($schema, $value, $field, $errors),
            'integer' => $this->integer($schema, $value, $field, $errors),
            'number' => $this->number($schema, $value, $field, $errors),
            'boolean' => is_bool($value) ? $value : $this->fail($errors, "{$field} must be true or false"),
            'array' => $this->list($schema, $value, $field, $errors),
            'object' => $this->nested($schema, $value, $field, $errors),
            default => $value,
        };

        if ($checked === null || ! isset($schema['enum']) || ! is_array($schema['enum'])) {
            return $checked;
        }

        if (! in_array($checked, $schema['enum'], true)) {
            return $this->fail($errors, "{$field} must be one of: ".implode(', ', array_map(strval(...), $schema['enum'])));
        }

        return $checked;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  list<string>  $errors
     */
    private function string(array $schema, mixed $value, string $field, array &$errors): ?string
    {
        if (! is_string($value)) {
            return $this->fail($errors, "{$field} must be a string");
        }

        $value = trim($value);
        $length = mb_strlen($value);

        if (isset($schema['minLength']) && $length < (int) $schema['minLength']) {
            return $this->fail($errors, (int) $schema['minLength'] === 1 ? "{$field} must not be empty" : "{$field} must be at least {$schema['minLength']} characters");
        }

        if (isset($schema['maxLength']) && $length > (int) $schema['maxLength']) {
            return $this->fail($errors, "{$field} must be at most {$schema['maxLength']} characters");
        }

        if (isset($schema['pattern']) && preg_match('#'.str_replace('#', '\#', (string) $schema['pattern']).'#u', $value) !== 1) {
            return $this->fail($errors, "{$field} has an invalid format");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  list<string>  $errors
     */
    private function integer(array $schema, mixed $value, string $field, array &$errors): ?int
    {
        if (is_string($value) && preg_match('/^\s*-?\d{1,18}\s*$/', $value) === 1) {
            $value = (int) trim($value);
        } elseif (is_float($value) && floor($value) === $value && abs($value) < PHP_INT_MAX) {
            $value = (int) $value;
        }

        if (! is_int($value)) {
            return $this->fail($errors, "{$field} must be an integer");
        }

        return $this->range($schema, $value, $field, $errors);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  list<string>  $errors
     */
    private function number(array $schema, mixed $value, string $field, array &$errors): int|float|null
    {
        if (is_string($value) && is_numeric(trim($value))) {
            $value = (float) trim($value);
        }

        if (! is_int($value) && ! is_float($value)) {
            return $this->fail($errors, "{$field} must be a number");
        }

        return $this->range($schema, $value, $field, $errors);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  list<string>  $errors
     */
    private function range(array $schema, int|float $value, string $field, array &$errors): int|float|null
    {
        if (isset($schema['minimum']) && $value < $schema['minimum']) {
            return $this->fail($errors, "{$field} must be at least {$schema['minimum']}");
        }

        if (isset($schema['maximum']) && $value > $schema['maximum']) {
            return $this->fail($errors, "{$field} must be at most {$schema['maximum']}");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  list<string>  $errors
     * @return list<mixed>|null
     */
    private function list(array $schema, mixed $value, string $field, array &$errors): ?array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return $this->fail($errors, "{$field} must be an array");
        }

        if (isset($schema['minItems']) && count($value) < (int) $schema['minItems']) {
            return $this->fail($errors, "{$field} must have at least {$schema['minItems']} items");
        }

        if (isset($schema['maxItems']) && count($value) > (int) $schema['maxItems']) {
            return $this->fail($errors, "{$field} must have at most {$schema['maxItems']} items");
        }

        $itemSchema = is_array($schema['items'] ?? null) ? $schema['items'] : [];
        $items = [];
        $failed = false;

        foreach ($value as $index => $item) {
            $checked = $item === null ? $this->fail($errors, "{$field}[{$index}] must not be null") : $this->value($itemSchema, $item, "{$field}[{$index}]", $errors);
            $failed = $failed || $checked === null;
            $items[] = $checked;
        }

        if ($failed) {
            return null;
        }

        return ($schema['uniqueItems'] ?? false) === true ? array_values(array_unique($items, SORT_REGULAR)) : $items;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  list<string>  $errors
     * @return array<string, mixed>|null
     */
    private function nested(array $schema, mixed $value, string $field, array &$errors): ?array
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            return $this->fail($errors, "{$field} must be an object");
        }

        $before = count($errors);
        $validated = $this->object($schema, $value, $field, $errors);

        return count($errors) === $before ? $validated : null;
    }

    /**
     * @param  list<string>  $errors
     */
    private function fail(array &$errors, string $message): null
    {
        $errors[] = $message;

        return null;
    }
}
