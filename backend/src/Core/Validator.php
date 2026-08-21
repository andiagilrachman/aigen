<?php

declare(strict_types=1);

namespace Aigen\Core;

/**
 * Validasi input sederhana yang mengumpulkan error per-field.
 *
 * Aturan didukung: required, email, min:n, max:n, numeric, int, in:a,b,c,
 * confirmed:field, boolean.
 */
final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    /** @var array<string,mixed> */
    private array $validated = [];

    public function __construct(private readonly array $data)
    {
    }

    public static function make(array $data): self
    {
        return new self($data);
    }

    /**
     * @param array<string,string> $rules  contoh: ['email' => 'required|email']
     * @param array<string,string> $labels nama field untuk pesan error
     */
    public function validate(array $rules, array $labels = []): self
    {
        foreach ($rules as $field => $ruleString) {
            $label = $labels[$field] ?? $field;
            $value = $this->data[$field] ?? null;

            if (is_string($value)) {
                $value = trim($value);
            }

            $ruleList = explode('|', $ruleString);
            $isRequired = in_array('required', $ruleList, true);
            $isEmpty = $value === null || $value === '';

            if ($isRequired && $isEmpty) {
                $this->errors[$field] = "$label wajib diisi";
                continue;
            }
            if ($isEmpty) {
                // Field opsional yang kosong: lewati validasi lain.
                $this->validated[$field] = null;
                continue;
            }

            foreach ($ruleList as $rule) {
                if ($rule === 'required' || $rule === '') {
                    continue;
                }

                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $error = $this->applyRule($name, $param, $value, $label);

                if ($error !== null) {
                    $this->errors[$field] = $error;
                    continue 2;
                }
            }

            $this->validated[$field] = $value;
        }

        return $this;
    }

    private function applyRule(string $name, ?string $param, mixed $value, string $label): ?string
    {
        return match ($name) {
            'email' => filter_var((string) $value, FILTER_VALIDATE_EMAIL)
                ? null
                : "$label harus berupa alamat email yang valid",

            'min' => mb_strlen((string) $value) >= (int) $param
                ? null
                : "$label minimal " . (int) $param . ' karakter',

            'max' => mb_strlen((string) $value) <= (int) $param
                ? null
                : "$label maksimal " . (int) $param . ' karakter',

            'numeric' => is_numeric($value)
                ? null
                : "$label harus berupa angka",

            'int' => filter_var($value, FILTER_VALIDATE_INT) !== false
                ? null
                : "$label harus berupa bilangan bulat",

            'in' => in_array((string) $value, explode(',', (string) $param), true)
                ? null
                : "$label tidak valid",

            'boolean' => in_array((string) $value, ['0', '1', 'true', 'false'], true)
                ? null
                : "$label harus bernilai benar atau salah",

            'confirmed' => ($this->data[$param] ?? null) === $value
                ? null
                : "Konfirmasi $label tidak cocok",

            default => null,
        };
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function validated(): array
    {
        return $this->validated;
    }

    /** Hentikan request dengan 422 bila ada error. */
    public function stopIfFails(): array
    {
        if ($this->fails()) {
            Response::error(
                'Periksa kembali data yang Anda masukkan',
                422,
                'validation_failed',
                $this->errors
            );
        }
        return $this->validated;
    }
}
