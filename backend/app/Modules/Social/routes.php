<?php

use App\Modules\Social\Http\Controllers\CommentController;
use App\Modules\Social\Http\Controllers\FeedController;
use App\Modules\Social\Http\Controllers\PostController;
use App\Modules\Social\Http\Controllers\ProfileController;
use App\Modules\Social\Http\Controllers\ReactionController;
use App\Modules\Social\Http\Controllers\RelationshipController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('feed', FeedController::class);
    Route::get('posts/{post}', [PostController::class, 'show']);
    Route::get('posts/{post}/comments', [CommentController::class, 'index']);

    Route::get('profiles/search', [ProfileController::class, 'search']);
    Route::get('profiles/{username}', [ProfileController::class, 'show']);
    Route::get('profiles/{username}/posts', [ProfileController::class, 'posts']);
    Route::get('profiles/{username}/followers', [ProfileController::class, 'followers']);
    Route::get('profiles/{username}/following', [ProfileController::class, 'following']);

    // Safety tools never require a verified email.
    Route::post('profiles/{username}/block', [RelationshipController::class, 'block'])->middleware('throttle:interactions');
    Route::delete('profiles/{username}/block', [RelationshipController::class, 'unblock'])->middleware('throttle:interactions');
    Route::get('me/blocks', [RelationshipController::class, 'blocked']);
    Route::delete('comments/{comment}', [CommentController::class, 'destroy']);
    Route::delete('posts/{post}', [PostController::class, 'destroy']);

    // Creating content and connections requires a verified email (anti-spam).
    Route::middleware('verified')->group(function () {
        Route::post('posts', [PostController::class, 'store'])->middleware('throttle:content');
        Route::patch('posts/{post}', [PostController::class, 'update'])->middleware('throttle:content');
        Route::post('posts/{post}/comments', [CommentController::class, 'store'])->middleware('throttle:content');
        Route::put('posts/{post}/reaction', [ReactionController::class, 'update'])->middleware('throttle:interactions');
        Route::delete('posts/{post}/reaction', [ReactionController::class, 'destroy'])->middleware('throttle:interactions');
        Route::post('profiles/{username}/follow', [RelationshipController::class, 'follow'])->middleware('throttle:interactions');
        Route::delete('profiles/{username}/follow', [RelationshipController::class, 'unfollow'])->middleware('throttle:interactions');
    });
});
