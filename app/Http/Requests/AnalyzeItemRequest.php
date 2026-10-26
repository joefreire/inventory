<?php

namespace App\Http\Requests;

use App\Ai\ItemAdvisor;
use App\Http\Requests\Concerns\HandlesPhotoUploads;
use Illuminate\Foundation\Http\FormRequest;

/**
 * AI analysis of a part: photos and/or the name typed so far.
 */
class AnalyzeItemRequest extends FormRequest
{
    use HandlesPhotoUploads;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'required_without_all:photo,photos', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ] + $this->photoRules(ItemAdvisor::MAX_PHOTOS);
    }

    /**
     * @return list<string>
     */
    public function photoPaths(): array
    {
        return array_map(fn ($file) => (string) $file->getRealPath(), $this->photoFiles(ItemAdvisor::MAX_PHOTOS));
    }
}
