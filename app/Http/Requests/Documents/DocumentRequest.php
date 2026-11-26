<?php

namespace App\Http\Requests\Documents;

use App\Models\Document;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class DocumentRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:'.implode(',', Document::ACCEPTED_TYPES), 'max:'.Document::MAX_KILOBYTES],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.mimes' => __('Upload a PDF or an image.'),
            'file.max' => __('Files can be up to 20 MB.'),
        ];
    }

    public function document(): UploadedFile
    {
        $file = $this->file('file');

        if (! $file instanceof UploadedFile) {
            abort(422);
        }

        return $file;
    }
}
