<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class ExtensionTrackRequest extends FormRequest
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
     * This method is called before validation is ran, and is a good place to
     * manipulate the request data before it's validated. In this case, we're
     * merging the video number field, and if the `data` field is present, we're
     * decoding it and merging the user agent and tokens into the request.
     *
     * If the decoding fails, we throw an exception with a 422 status code,
     * since the request is invalid.
     */
    public function prepareForValidation(): void
    {
        if ($encoded = $this->input('data')) {
            try {
                $json = base64_decode($encoded, strict: true);
                $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

                $this->merge([
                    'user_agent' => $data['u'] ?? null,
                    'user_id'    => $data['uid'] ?? null,
                    'tokens'     => [
                        'bearer_token' => $data['t']['b'] ?? null,
                        'auth_token'   => $data['t']['a'] ?? null,
                        'csrf_token'   => $data['t']['c'] ?? null,
                        'guest_token'  => $data['t']['g'] ?? null,
                    ],
                    'cookies' => [
                        'auth_token'         => $data['k']['auth_token'] ?? null,
                        'ct0'                => $data['k']['ct0'] ?? null,
                        'guest_id_marketing' => $data['k']['guest_id_marketing'] ?? null,
                        'guest_id_ads'       => $data['k']['guest_id_ads'] ?? null,
                        'gt'                 => $data['k']['gt'] ?? null,
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to decode TweetTelemetryRequest data', [
                    'error' => $e->getMessage(),
                ]);
                throw new HttpResponseException(response()->json([
                    'message' => 'Invalid encoded data payload.',
                ], 422));
            }
        }

        $isValidRef = false;

        if ($ref = $this->input('ref')) {
            try {
                $decoded = json_decode(urldecode(base64_decode(strtr($ref, '-_', '+/'))), true);
                if (!is_array($decoded)) {
                    throw new \Exception('Invalid ref JSON');
                }

                $tsDiff = abs(now('UTC')->getTimestampMs() - ($decoded['ts'] ?? 0));

                if ($tsDiff <= 5000) {
                    $extra = $this->input('extra', []);
                    if (
                        ($extra['tweet_id'] ?? null) == ($decoded['t'] ?? null) &&
                        ($extra['video_number'] ?? null) == ($decoded['v'] ?? null)
                    ) {
                        $isValidRef = true;
                    }
                }
            } catch (\Throwable $e) {
                Log::info('Invalid ref format', ['error' => $e->getMessage(), 'ref' => $ref]);
            }
        }

        $this->merge([
            'ref_valid' => $isValidRef,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'event'      => ['required', 'string', 'max:100'],
            'data'       => ['required', 'string'],
            'user_agent' => ['required', 'string'],
            'user_id'    => ['nullable', 'string', 'max:32'],
            'ref'        => ['nullable', 'string'],
            'ref_valid'  => ['boolean'],

            'tokens'              => ['required', 'array'],
            'tokens.bearer_token' => ['required', 'string'],
            'tokens.auth_token'   => ['required', 'string'],
            'tokens.csrf_token'   => ['required', 'string'],
            'tokens.guest_token'  => ['nullable', 'string'],

            'cookies'                    => ['required', 'array'],
            'cookies.auth_token'         => ['required', 'string'],
            'cookies.ct0'                => ['required', 'string'],
            'cookies.guest_id_marketing' => ['nullable', 'string'],
            'cookies.guest_id_ads'       => ['nullable', 'string'],
            'cookies.gt'                 => ['nullable', 'string'],

            'extra' => ['nullable', 'array'],
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
