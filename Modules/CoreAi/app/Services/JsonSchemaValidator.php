<?php

namespace Modules\CoreAi\Services;

use Modules\CoreAi\Exceptions\AiValidationException;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Kiểm định dữ liệu theo JSON Schema (subset tối thiểu).
 * =====================================================================
 *
 * Triển khai validator nhẹ, không phụ thuộc thư viện ngoài, đủ kiểm định
 * output AI theo PLAN §3.2: type, required, properties, enum, items,
 * additionalProperties, min/max cho string/number/array/object, nested.
 *
 * KHÔNG hỗ trợ: $ref, oneOf/anyOf/allOf, pattern, format, $defs — các
 * trường đó coi như "không ràng buộc" (pass) thay vì báo lỗi.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - validate()  : ném AiValidationException khi lệch.
 * - check()     : trả errors[] (không ném).
 * - validateNode/validateType/validateObject/validateArray/validateScalar
 * - isAssoc/path/pushErr : tiện ích nội bộ.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : mixed $data + array $schema.
 * - OUTPUT: true (khớp) | errors[] (lệch) | AiValidationException.
 * =====================================================================
 */
class JsonSchemaValidator
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm định $data khớp $schema — ném khi lệch.
     * INPUT: mixed data + array schema. OUTPUT: true khi khớp.
     * EXCEPTION: AiValidationException kèm errors[].
     * =====================================================================
     */
    public function validate(mixed $data, array $schema): bool
    {
        $errors = $this->check($data, $schema);
        if ($errors !== []) {
            throw AiValidationException::withErrors($errors);
        }

        return true;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm định và trả mảng lỗi (không ném).
     * INPUT: mixed data + array schema.
     * OUTPUT: array<array{path,message,expected?,actual?}> — rỗng = hợp lệ.
     * =====================================================================
     */
    public function check(mixed $data, array $schema): array
    {
        $errors = [];
        $this->validateNode($data, $schema, '', $errors);

        return $errors;
    }
    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm định một node dữ liệu theo schema con.
     * INPUT: data + schema + path hiện tại + &errors.
     * OUTPUT: void — ghi lỗi vào $errors.
     * =====================================================================
     */
    protected function validateNode(mixed $data, array $schema, string $path, array &$errors): void
    {
        // 1. enum — kiểm trước type (nhanh).
        if (array_key_exists('enum', $schema)) {
            $enum = $schema['enum'];
            if (! is_array($enum) || ! in_array($data, $enum, true)) {
                $this->pushErr($errors, $path, 'gia tri khong thuoc enum', $enum, $data);

                return;
            }
        }

        // 2. type — hỗ trợ string|array<string> (nullable union).
        $type = $schema['type'] ?? null;
        if ($type !== null) {
            $types = is_array($type) ? $type : [$type];
            if (! $this->validateType($data, $types)) {
                $this->pushErr($errors, $path, 'sai kieu du lieu', $types, gettype($data));

                return;
            }
        }

        // 3. const — giá trị cố định.
        if (array_key_exists('const', $schema) && $data !== $schema['const']) {
            $this->pushErr($errors, $path, 'gia tri phai bang const', $schema['const'], $data);
        }

        // 4. object — required/properties/additionalProperties.
        if (is_array($data) && $this->isAssoc($data)) {
            $this->validateObject($data, $schema, $path, $errors);
        }

        // 5. array — items + minItems/maxItems.
        if (is_array($data) && ! $this->isAssoc($data)) {
            $this->validateArray($data, $schema, $path, $errors);
        }

        // 6. scalar — string/number constraints.
        if (is_string($data) || is_int($data) || is_float($data)) {
            $this->validateScalar($data, $schema, $path, $errors);
        }
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm tra data khớp một trong các type cho phép.
     * INPUT: data + string[] types. OUTPUT: bool.
     * =====================================================================
     */
    protected function validateType(mixed $data, array $types): bool
    {
        foreach ($types as $t) {
            $ok = match ($t) {
                'string' => is_string($data),
                'number' => is_int($data) || is_float($data),
                'integer' => is_int($data),
                'boolean' => is_bool($data),
                'object' => is_array($data) && ($data === [] || $this->isAssoc($data)),
                'array' => is_array($data) && ($data === [] || ! $this->isAssoc($data)),
                'null' => $data === null,
                default => true, // type lạ → bỏ qua (không ràng buộc).
            };
            if ($ok) {
                return true;
            }
        }

        return false;
    }
    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm định object — required + properties + additional.
     * INPUT: assoc array + schema + path + &errors. OUTPUT: void.
     * =====================================================================
     */
    protected function validateObject(array $data, array $schema, string $path, array &$errors): void
    {
        // required
        foreach ((array) ($schema['required'] ?? []) as $req) {
            if (! array_key_exists($req, $data)) {
                $this->pushErr($errors, $this->path($path, (string) $req), 'thieu truong bat buoc', 'present', 'missing');
            }
        }

        // properties — validate từng field đã khai báo.
        $props = (array) ($schema['properties'] ?? []);
        foreach ($props as $key => $subSchema) {
            if (! is_array($subSchema)) {
                continue;
            }
            if (array_key_exists($key, $data)) {
                $this->validateNode($data[$key], $subSchema, $this->path($path, (string) $key), $errors);
            }
        }

        // additionalProperties — false = cấm key lạ; array = validate theo schema.
        $additional = $schema['additionalProperties'] ?? null;
        if ($additional !== null) {
            $declared = array_keys($props);
            foreach ($data as $key => $value) {
                if (in_array($key, $declared, true)) {
                    continue;
                }
                if ($additional === false) {
                    $this->pushErr($errors, $this->path($path, (string) $key), 'truong khong duoc khai bao', 'not allowed', 'present');
                } elseif (is_array($additional)) {
                    $this->validateNode($value, $additional, $this->path($path, (string) $key), $errors);
                }
            }
        }

        // min/max properties.
        $count = count($data);
        if (isset($schema['minProperties']) && $count < (int) $schema['minProperties']) {
            $this->pushErr($errors, $path, 'object co qua it thuoc tinh', '>= '.$schema['minProperties'], $count);
        }
        if (isset($schema['maxProperties']) && $count > (int) $schema['maxProperties']) {
            $this->pushErr($errors, $path, 'object co qua nhieu thuoc tinh', '<= '.$schema['maxProperties'], $count);
        }
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm định array — items + minItems/maxItems.
     * INPUT: numeric array + schema + path + &errors. OUTPUT: void.
     * =====================================================================
     */
    protected function validateArray(array $data, array $schema, string $path, array &$errors): void
    {
        $count = count($data);

        if (isset($schema['minItems']) && $count < (int) $schema['minItems']) {
            $this->pushErr($errors, $path, 'mang co qua it phan tu', '>= '.$schema['minItems'], $count);
        }
        if (isset($schema['maxItems']) && $count > (int) $schema['maxItems']) {
            $this->pushErr($errors, $path, 'mang co qua nhieu phan tu', '<= '.$schema['maxItems'], $count);
        }

        // items — mọi phần tử phải khớp schema con.
        $itemSchema = $schema['items'] ?? null;
        if (is_array($itemSchema)) {
            foreach ($data as $i => $item) {
                $this->validateNode($item, $itemSchema, $this->path($path, (string) $i), $errors);
            }
        }
    }
    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm định scalar — min/max length/value.
     * INPUT: string|int|float + schema + path + &errors. OUTPUT: void.
     * =====================================================================
     */
    protected function validateScalar(mixed $data, array $schema, string $path, array &$errors): void
    {
        if (is_string($data)) {
            $len = mb_strlen($data);
            if (isset($schema['minLength']) && $len < (int) $schema['minLength']) {
                $this->pushErr($errors, $path, 'chuoi qua ngan', '>= '.$schema['minLength'], $len);
            }
            if (isset($schema['maxLength']) && $len > (int) $schema['maxLength']) {
                $this->pushErr($errors, $path, 'chuoi qua dai', '<= '.$schema['maxLength'], $len);
            }
        }

        if (is_int($data) || is_float($data)) {
            if (isset($schema['minimum']) && $data < (float) $schema['minimum']) {
                $this->pushErr($errors, $path, 'gia tri nho hon minimum', '>= '.$schema['minimum'], $data);
            }
            if (isset($schema['maximum']) && $data > (float) $schema['maximum']) {
                $this->pushErr($errors, $path, 'gia tri lon hon maximum', '<= '.$schema['maximum'], $data);
            }
        }
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm tra mảng có phải associative (object) không.
     * INPUT: array. OUTPUT: bool.
     * =====================================================================
     */
    protected function isAssoc(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Xây đường dẫn JSON Path đơn giản.
     * INPUT: base path + segment. OUTPUT: 'a.b.c' hoặc 'a[0]'.
     * =====================================================================
     */
    protected function path(string $base, string $segment): string
    {
        if (is_numeric($segment)) {
            return $base . '[' . $segment . ']';
        }

        return $base === '' ? $segment : $base . '.' . $segment;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Ghi một lỗi vào danh sách.
     * INPUT: &errors + path + message + expected + actual. OUTPUT: void.
     * =====================================================================
     */
    protected function pushErr(array &$errors, string $path, string $message, mixed $expected = null, mixed $actual = null): void
    {
        $errors[] = [
            'path' => $path === '' ? '(root)' : $path,
            'message' => $message,
            'expected' => $expected,
            'actual' => $actual,
        ];
    }
}