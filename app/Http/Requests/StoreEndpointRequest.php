<?php

namespace App\Http\Requests;

use App\Support\EndpointLocationNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class StoreEndpointRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $location = $this->input('location');

        if (is_string($location)) {
            $this->merge([
                'location' => EndpointLocationNormalizer::normalize($location),
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'location' => ['required', 'string', 'max:2048'],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
