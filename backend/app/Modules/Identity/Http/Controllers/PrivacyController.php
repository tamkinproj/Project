<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Http\Requests\UpdatePrivacyRequest;
use App\Modules\Identity\Http\Resources\PrivacyResource;
use Illuminate\Http\Request;

class PrivacyController
{
    public function show(Request $request): PrivacyResource
    {
        return new PrivacyResource($request->user()->privacy);
    }

    public function update(UpdatePrivacyRequest $request): PrivacyResource
    {
        $privacy = $request->user()->privacy;
        $privacy->update($request->validated());

        return new PrivacyResource($privacy);
    }
}
