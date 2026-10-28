<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            ? ($this->user()?->can('update', $target) ?? false)
            : ($this->user()?->can('create', User::class) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'lowercase', 'max:255', Rule::unique('users')->ignore($target instanceof User ? $target->id : null)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => [$target instanceof User ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
