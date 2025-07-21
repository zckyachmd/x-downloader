<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Crypt;

class ExtensionVideoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the request for validation.
     *
     * If the request has a "ref" field, attempt to decrypt it and merge the
     * decrypted value as "user_id" field. If the decryption fails, the
     * validation rules will still reject the request.
     */
    public function prepareForValidation(): void
    {
        if ($this->has('ref')) {
            try {
                $this->merge([
                    'user_id' => Crypt::decryptString($this->input('ref')),
                ]);
            } catch (\Throwable) {
                // biarkan validation rules tetap menolak ref invalid
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tweet_id'     => ['required', 'numeric', 'min:1'],
            'video_number' => ['required', 'integer', 'min:0'],
            'ref'          => ['required', 'string'],
            'user_id'      => ['required', 'numeric'],
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Validation Failed',
                'errors'  => $validator->errors(),
            ], 422),
        );
    }
}
