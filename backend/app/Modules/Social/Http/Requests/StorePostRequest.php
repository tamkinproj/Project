<?php

namespace App\Modules\Social\Http\Requests;

use App\Modules\Social\Enums\PostVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
{
    public function rules(): array
    {
        $maxImages = config('ecosystem.media.max_images_per_post');

        return [
            'body' => ['nullable', 'string', 'max:5000', 'required_without:images'],
            'visibility' => ['nullable', Rule::enum(PostVisibility::class)],
            'images' => ['nullable', 'array', "max:{$maxImages}"],
            'images.*' => ['file', 'image', 'mimes:jpeg,png,webp', 'max:'.config('ecosystem.media.max_upload_kb')],
        ];
    }

    public function messages(): array
    {
        return ['body.required_without' => 'Write something or add a photo.'];
    }
}
