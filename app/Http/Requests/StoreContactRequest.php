<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreContactRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        $comment = $this->input('comment');

        $this->merge([
            'name' => $this->normalizeSingleLine($this->input('name')),
            'phone' => $this->normalizeSingleLine($this->input('phone')),
            'email' => is_string($email) ? Str::lower(trim($email)) : $email,
            'comment' => is_string($comment) ? trim($comment) : $comment,
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:100',
            ],
            'phone' => [
                'required',
                'string',
                'max:30',
                'regex:/^\+?(?=(?:\D*\d){10,15}\D*$)[0-9\s()\-]+$/',
            ],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'comment' => ['required', 'string', 'min:10', 'max:3000'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите имя.',
            'name.min' => 'Имя должно содержать минимум 2 символа.',
            'name.max' => 'Имя не должно превышать 100 символов.',
            'phone.required' => 'Укажите номер телефона.',
            'phone.max' => 'Номер телефона не должен превышать 30 символов.',
            'phone.regex' => 'Введите номер телефона из 10–15 цифр.',
        ];
    }

    private function normalizeSingleLine(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_replace('/[\t\r\n]+/', ' ', trim($value)) ?? trim($value);
    }
}
