<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Very small rule-based validator. Rules are pipe separated:
 *   'name' => 'required|max:150'
 *   'role' => 'required|in:poweradmin,adminsto,audit,observer'
 *   'group_id' => 'required|integer|exists:groups,id'
 */
class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $rules
     * @param array<string, string> $labels
     */
    public function __construct(
        private array $data,
        private array $rules,
        private array $labels = []
    ) {
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        $validator = new self($data, $rules, $labels);
        $validator->run();

        return $validator;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            $value = $this->data[$field] ?? null;
            $label = $this->labels[$field] ?? str_replace('_', ' ', $field);

            $nullable = in_array('nullable', $rules, true);
            $isEmpty = $value === null || $value === '' || (is_array($value) && $value === []);

            if ($nullable && $isEmpty) {
                continue;
            }

            foreach ($rules as $rule) {
                if ($rule === '' || $rule === 'nullable') {
                    continue;
                }

                [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);

                if ($this->check($name, $value, $parameter, $label)) {
                    // First failure wins for a field.
                    break;
                }
            }
        }
    }

    /**
     * Returns true when the rule FAILED (and records the error).
     */
    private function check(string $rule, mixed $value, ?string $parameter, string $label): bool
    {
        $isEmpty = $value === null || $value === '' || (is_array($value) && $value === []);

        $failed = match ($rule) {
            'required'  => $isEmpty,
            'email'     => ! $isEmpty && filter_var((string) $value, FILTER_VALIDATE_EMAIL) === false,
            'numeric'   => ! $isEmpty && ! is_numeric($value),
            'integer'   => ! $isEmpty && filter_var($value, FILTER_VALIDATE_INT) === false,
            'min'       => ! $isEmpty && mb_strlen((string) $value) < (int) $parameter,
            'max'       => ! $isEmpty && mb_strlen((string) $value) > (int) $parameter,
            'min_value' => ! $isEmpty && (float) $value < (float) $parameter,
            'in'        => ! $isEmpty && ! in_array((string) $value, explode(',', (string) $parameter), true),
            'date'      => ! $isEmpty && strtotime((string) $value) === false,
            'unique'    => $this->failsUnique($value, $parameter),
            'exists'    => $this->failsExists($value, $parameter),
            default     => false,
        };

        if (! $failed) {
            return false;
        }

        $this->errors[$field] = $this->message($rule, $label, $parameter);

        return true;
    }

    private function failsUnique(mixed $value, ?string $parameter): bool
    {
        if ($value === null || $value === '' || $parameter === null) {
            return false;
        }

        // parameter format: table,column[,ignoreId]
        [$table, $column, $ignore] = array_pad(explode(',', $parameter), 3, null);

        // Identifiers are always quoted (e.g. `groups` is a MySQL 8 reserved word).
        $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?";
        $bindings = [$value];

        if ($ignore !== null) {
            $sql .= ' AND `id` <> ?';
            $bindings[] = $ignore;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($bindings);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function failsExists(mixed $value, ?string $parameter): bool
    {
        if ($value === null || $value === '' || $parameter === null) {
            return false;
        }

        [$table, $column] = array_pad(explode(',', $parameter), 2, 'id');

        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?");
        $stmt->execute([$value]);

        return (int) $stmt->fetchColumn() === 0;
    }

    private function message(string $rule, string $label, ?string $parameter): string
    {
        return match ($rule) {
            'required'  => "{$label} wajib diisi.",
            'email'     => "Format {$label} tidak valid.",
            'numeric'   => "{$label} harus berupa angka.",
            'integer'   => "{$label} harus berupa bilangan bulat.",
            'min'       => "{$label} minimal {$parameter} karakter.",
            'max'       => "{$label} maksimal {$parameter} karakter.",
            'min_value' => "{$label} minimal {$parameter}.",
            'in'        => "Pilihan {$label} tidak valid.",
            'date'      => "Format tanggal {$label} tidak valid.",
            'unique'    => "{$label} sudah digunakan.",
            'exists'    => "{$label} yang dipilih tidak ditemukan.",
            default     => "{$label} tidak valid.",
        };
    }
}
