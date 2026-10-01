<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Http\Requests\AvatarRequest;
use App\Modules\Identity\Http\Requests\UpdateProfileRequest;
use App\Modules\Identity\Http\Resources\MeResource;
use App\Support\Media\ImageProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileSettingsController
{
    public function update(UpdateProfileRequest $request, AuditLogger $audit): MeResource
    {
        $user = $request->user();
        $profile = $user->profile;
        $oldUsername = $profile->username;

        $profile->update($request->validated());

        if ($profile->username !== $oldUsername) {
            $audit->record('profile.username_changed', subject: $user, metadata: ['from' => $oldUsername, 'to' => $profile->username]);
        }

        return new MeResource($user->load('profile', 'privacy'));
    }

    public function updateAvatar(AvatarRequest $request, ImageProcessor $images): MeResource
    {
        $user = $request->user();
        $stored = $images->store($request->file('avatar'), 'avatars', squareSize: 512);

        $previous = $user->profile->avatar_path;
        $user->profile->update(['avatar_path' => $stored['path']]);

        if ($previous) {
            Storage::disk(config('ecosystem.media.disk'))->delete($previous);
        }

        return new MeResource($user->load('profile', 'privacy'));
    }

    public function deleteAvatar(Request $request): MeResource
    {
        $user = $request->user();

        if ($path = $user->profile->avatar_path) {
            $user->profile->update(['avatar_path' => null]);
            Storage::disk(config('ecosystem.media.disk'))->delete($path);
        }

        return new MeResource($user->load('profile', 'privacy'));
    }
}
