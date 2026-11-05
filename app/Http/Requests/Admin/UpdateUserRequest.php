<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    public function rules(): array
    {
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target->id)],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+\-\s().]{5,40}$/'],
            'role' => ['required', 'string', Rule::in($this->user()->assignableRoles())],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Nobody can demote or deactivate themselves (prevents lock-out).
            if ($this->route('user')->is($this->user())) {
                if (! $this->boolean('is_active', true)) {
                    $validator->errors()->add('is_active', 'You cannot deactivate your own account.');
                }
                if ($this->input('role') !== $this->user()->getRoleNames()->first()) {
                    $validator->errors()->add('role', 'You cannot change your own role.');
                }
            }
        });
    }
}
