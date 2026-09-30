<?php

declare(strict_types=1);

namespace App\Support\Settings;

use App\Enums\SettingType;
use InvalidArgumentException;

final readonly class SettingDefinition
{
    /**
     * @param  list<string|int>  $options  allowed values (ENUM) or allowed members (INT_LIST; empty = any within bounds)
     */
    public function __construct(
        public string $key,
        public SettingType $type,
        public mixed $default,
        public string $group,
        public string $description,
        public ?int $min = null,
        public ?int $max = null,
        public array $options = [],
    ) {}

    public function validate(mixed $value): void
    {
        $problem = match ($this->type) {
            SettingType::Int => $this->intProblem($value),
            SettingType::Bool => is_bool($value) ? null : 'must be true or false',
            SettingType::Enum => in_array($value, $this->options, true) ? null : 'must be one of: '.implode(', ', $this->options),
            SettingType::IntList => $this->intListProblem($value),
            SettingType::Time => is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1 ? null : 'must be a 24-hour time such as 09:00',
            SettingType::Version => is_string($value) && preg_match('/^\d+\.\d+\.\d+$/', $value) === 1 ? null : 'must be a version such as 1.4.0',
        };

        if ($problem !== null) {
            throw new InvalidArgumentException("Setting [{$this->key}] {$problem}.");
        }
    }

    private function intProblem(mixed $value): ?string
    {
        if (! is_int($value)) {
            return 'must be a whole number';
        }

        if ($this->min !== null && $value < $this->min) {
            return "must be at least {$this->min}";
        }

        if ($this->max !== null && $value > $this->max) {
            return "must be at most {$this->max}";
        }

        return null;
    }

    private function intListProblem(mixed $value): ?string
    {
        if (! is_array($value) || $value === [] || ! array_is_list($value)) {
            return 'must be a non-empty list';
        }

        foreach ($value as $item) {
            if ($this->options !== [] && ! in_array($item, $this->options, true)) {
                return 'contains a value that is not allowed';
            }

            $problem = $this->intProblem($item);
            if ($problem !== null) {
                return "has an item that {$problem}";
            }
        }

        return count($value) === count(array_unique($value)) ? null : 'must not repeat values';
    }
}
