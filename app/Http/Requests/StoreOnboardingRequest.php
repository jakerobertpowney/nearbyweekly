<?php

namespace App\Http\Requests;

use App\Contracts\PostcodeGeocoder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOnboardingRequest extends FormRequest
{
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
            'postcode' => ['required', 'string', 'max:12', 'regex:/^[A-Z]{1,2}\d[A-Z\d]?\s?\d[A-Z]{2}$/i'],
            'radius_miles' => ['required', Rule::in([5, 10, 25, 50, 100])],
            'interests' => ['required', 'array', 'min:1'],
            'interests.*' => ['required', 'integer', 'exists:interests,id'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'postcode.regex' => 'Enter a valid UK postcode.',
            'interests.min' => 'Choose at least one interest so we can tailor your weekly picks.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('postcode')) {
                    return;
                }

                $geo = app(PostcodeGeocoder::class)->geocode((string) $this->input('postcode'));

                if ($geo === null) {
                    $validator->errors()->add('postcode', 'Enter a real UK postcode.');
                }
            },
        ];
    }
}
