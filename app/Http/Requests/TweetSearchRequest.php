<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TweetSearchRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tweet-url' => ['required', 'string', 'regex:/status\/(\d{5,30})/'],
        ];
    }

    /**
     * Returns the tweet ID extracted from the provided tweet URL if it matches.
     *
     * @return int|null
     */
    public function tweetId(): ?int
    {
        $url = $this->input('tweet-url');

        if (
            preg_match(
                '~^https?://(www\.)?(twitter\.com|x\.com)/\w+/status/(\d{5,30})(?:[/?#&]|$)~i',
                $url,
                $matches,
            )
        ) {
            return $matches[3];
        }

        return null;
    }
}
