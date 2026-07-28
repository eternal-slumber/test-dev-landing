<?php

use App\Http\Requests\StoreContactRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

$validContactData = static fn (): array => [
    'name' => 'Иван Петров',
    'phone' => '+7 (999) 123-45-67',
    'email' => 'ivan@example.com',
    'comment' => 'Нужна консультация по проекту.',
    'idempotency_key' => (string) Str::uuid(),
];

test('valid contact data passes validation', function () use ($validContactData) {
    $request = new StoreContactRequest;
    $validator = Validator::make($validContactData(), $request->rules());

    expect($request->authorize())->toBeTrue()
        ->and($validator->passes())->toBeTrue();
});

test('contact field is required', function (string $field) use ($validContactData) {
    $data = $validContactData();
    unset($data[$field]);

    $validator = Validator::make($data, (new StoreContactRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has($field))->toBeTrue();
})->with(['name', 'phone', 'email', 'comment', 'idempotency_key']);

test('invalid email is rejected', function () use ($validContactData) {
    $data = $validContactData();
    $data['email'] = 'invalid-email';

    $validator = Validator::make($data, (new StoreContactRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('email'))->toBeTrue();
});

test('invalid phone is rejected', function (string $phone) use ($validContactData) {
    $data = $validContactData();
    $data['phone'] = $phone;

    $validator = Validator::make($data, (new StoreContactRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('phone'))->toBeTrue();
})->with([
    'letters' => 'call me!',
    'formatting without digits' => '()+ -',
    'too few digits' => '+7 999 123-45',
    'too many digits' => '+7 999 123-45-67-890-12',
    'plus is not at the start' => '7+9991234567',
]);

test('invalid name is rejected', function (string $name) use ($validContactData) {
    $data = $validContactData();
    $data['name'] = $name;

    $validator = Validator::make($data, (new StoreContactRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('name'))->toBeTrue();
})->with([
    'digits' => 'Иван 123',
    'no letters' => '   ',
    'unsupported symbol' => 'Иван @',
]);

test('invalid comment length is rejected', function (string $comment) use ($validContactData) {
    $data = $validContactData();
    $data['comment'] = $comment;

    $validator = Validator::make($data, (new StoreContactRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('comment'))->toBeTrue();
})->with([
    'too short' => str_repeat('a', 9),
    'too long' => str_repeat('a', 3001),
]);

test('name and phone errors are returned in russian', function () use ($validContactData) {
    $data = $validContactData();
    $data['name'] = '12';
    $data['phone'] = 'call me!';
    $request = new StoreContactRequest;

    $validator = Validator::make(
        $data,
        $request->rules(),
        $request->messages(),
    );

    expect($validator->errors()->first('name'))
        ->toBe('Имя может содержать только буквы, пробелы, дефисы и апострофы.')
        ->and($validator->errors()->first('phone'))
        ->toBe('Введите номер телефона из 10–15 цифр.');
});
