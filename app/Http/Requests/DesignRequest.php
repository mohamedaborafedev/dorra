<?php

namespace App\Http\Requests;

use App\Enums\DesignStatus;
use App\Models\Design;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DesignRequest extends FormRequest
{
    public function authorize(): bool
    {
        $design = $this->route('design');

        return $design instanceof Design
            ? ($this->user()?->can('update', $design) ?? false)
            : ($this->user()?->can('create', Design::class) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'status' => ['required', Rule::enum(DesignStatus::class)],
            'thumbnail' => [$this->route('design') instanceof Design ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['thumbnail.required' => 'Please upload an image for the design.'];
    }
}
