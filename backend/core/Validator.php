<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\ValidationException;

/**
 * Validador de dados baseado em regras textuais.
 *
 * Regras suportadas: required, string, email, numeric, integer, boolean,
 * min:N, max:N, between:A,B, in:a,b,c, regex:/.../, same:campo,
 * confirmed, cpf, cep, telefone, url.
 */
final class Validator
{
    /** @var array<string,string[]> */
    private array $errors = [];

    /**
     * @param array<string,mixed> $data
     * @param array<string,string> $rules  ['campo' => 'required|email|max:120']
     * @param array<string,string> $labels rotulos amigaveis por campo
     */
    public function __construct(
        private array $data,
        private array $rules,
        private array $labels = []
    ) {
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,string> $rules
     * @param array<string,string> $labels
     */
    public static function make(array $data, array $rules, array $labels = []): self
    {
        $validator = new self($data, $rules, $labels);
        $validator->run();
        return $validator;
    }

    public function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules    = explode('|', $ruleString);
            $value    = $this->data[$field] ?? null;
            $required = in_array('required', $rules, true);
            $present  = $value !== null && $value !== '';

            if (!$required && !$present) {
                continue;
            }

            // O campo so e tratado como numerico (min/max/between comparando
            // VALOR) quando declara explicitamente numeric ou integer. Caso
            // contrario, min/max/between medem o COMPRIMENTO do texto. Isso
            // evita que strings numericas (ex.: numero de endereco "123" ou
            // senhas) sejam interpretadas como grandeza.
            $numerico = in_array('numeric', $rules, true) || in_array('integer', $rules, true);

            foreach ($rules as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $this->applyRule($field, $name, $param, $value, $numerico);
            }
        }
    }

    private function applyRule(string $field, string $rule, ?string $param, mixed $value, bool $numerico = false): void
    {
        $label = $this->labels[$field] ?? $field;

        switch ($rule) {
            case 'required':
                if ($value === null || $value === '' || (is_array($value) && $value === [])) {
                    $this->addError($field, "O campo {$label} e obrigatorio.");
                }
                break;
            case 'string':
                if (!is_string($value)) {
                    $this->addError($field, "O campo {$label} deve ser texto.");
                }
                break;
            case 'email':
                if (!filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "O campo {$label} deve ser um e-mail valido.");
                }
                break;
            case 'numeric':
                if (!is_numeric($value)) {
                    $this->addError($field, "O campo {$label} deve ser numerico.");
                }
                break;
            case 'integer':
                if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->addError($field, "O campo {$label} deve ser um numero inteiro.");
                }
                break;
            case 'boolean':
                if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
                    $this->addError($field, "O campo {$label} deve ser booleano.");
                }
                break;
            case 'min':
                if ($numerico) {
                    if ((float) $value < (float) $param) {
                        $this->addError($field, "O campo {$label} deve ser no minimo {$param}.");
                    }
                } elseif (mb_strlen((string) $value) < (int) $param) {
                    $this->addError($field, "O campo {$label} deve ter no minimo {$param} caracteres.");
                }
                break;
            case 'max':
                if ($numerico) {
                    if ((float) $value > (float) $param) {
                        $this->addError($field, "O campo {$label} deve ser no maximo {$param}.");
                    }
                } elseif (mb_strlen((string) $value) > (int) $param) {
                    $this->addError($field, "O campo {$label} deve ter no maximo {$param} caracteres.");
                }
                break;
            case 'between':
                [$a, $b] = array_pad(explode(',', (string) $param), 2, null);
                $medida = $numerico ? (float) $value : mb_strlen((string) $value);
                if ($medida < (float) $a || $medida > (float) $b) {
                    $this->addError($field, "O campo {$label} deve estar entre {$a} e {$b}.");
                }
                break;
            case 'in':
                $options = explode(',', (string) $param);
                if (!in_array((string) $value, $options, true)) {
                    $this->addError($field, "O campo {$label} possui um valor invalido.");
                }
                break;
            case 'regex':
                if (!is_string($param) || @preg_match($param, (string) $value) !== 1) {
                    $this->addError($field, "O campo {$label} possui formato invalido.");
                }
                break;
            case 'same':
                if (($this->data[$param] ?? null) !== $value) {
                    $this->addError($field, "O campo {$label} nao confere.");
                }
                break;
            case 'confirmed':
                if (($this->data["{$field}_confirmation"] ?? null) !== $value) {
                    $this->addError($field, "A confirmacao do campo {$label} nao confere.");
                }
                break;
            case 'cpf':
                if (!self::validarCpf((string) $value)) {
                    $this->addError($field, "O campo {$label} deve ser um CPF valido.");
                }
                break;
            case 'cep':
                if (preg_match('/^\d{5}-?\d{3}$/', (string) $value) !== 1) {
                    $this->addError($field, "O campo {$label} deve ser um CEP valido.");
                }
                break;
            case 'telefone':
                $digits = preg_replace('/\D/', '', (string) $value) ?? '';
                if (strlen($digits) < 10 || strlen($digits) > 11) {
                    $this->addError($field, "O campo {$label} deve ser um telefone valido.");
                }
                break;
            case 'url':
                if (!filter_var((string) $value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, "O campo {$label} deve ser uma URL valida.");
                }
                break;
        }
    }

    public static function validarCpf(string $cpf): bool
    {
        $cpf = preg_replace('/\D/', '', $cpf) ?? '';
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }
        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$t] !== $digit) {
                return false;
            }
        }
        return true;
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string,string[]> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** Lanca ValidationException se houver erros. */
    public function validate(): void
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors);
        }
    }
}
