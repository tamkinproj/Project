<?php

use Illuminate\Support\Facades\Route;

// Malformed identifiers never reach a controller or the database.
$ulid = '[0-7][0-9a-hjkmnp-tv-zA-HJKMNP-TV-Z]{25}';
foreach (['post', 'comment', 'card', 'session', 'user', 'report'] as $parameter) {
    Route::pattern($parameter, $ulid);
}
Route::pattern('username', '@?[A-Za-z0-9._]{1,30}');

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    require base_path('app/Modules/Identity/routes.php');
    require base_path('app/Modules/Social/routes.php');
    require base_path('app/Modules/Moderation/routes.php');
    require base_path('app/Modules/Notifications/routes.php');
    require base_path('app/Modules/Card/routes.php');
    require base_path('app/Modules/Admin/routes.php');
});
