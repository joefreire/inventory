<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Text read from an NFC tag (a link or a bare code).
 */
class NfcScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['tag' => ['required', 'string', 'max:500']];
    }

    public function scanned(): string
    {
        return (string) $this->validated('tag');
    }
}
