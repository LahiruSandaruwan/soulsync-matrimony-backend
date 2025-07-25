<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\User;
use App\Models\Conversation;
use App\Models\UserMatch;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// User-specific channels
Broadcast::channel('user.{id}', function (User $user, $id) {
    return (int) $user->id === (int) $id;
});

// Chat channels - users can only access conversations they're part of
Broadcast::channel('chat.{conversation_id}', function (User $user, $conversation_id) {
    $conversation = Conversation::find($conversation_id);
    
    if (!$conversation) {
        return false;
    }
    
    return $conversation->participants()->where('user_id', $user->id)->exists();
});

// Match notifications - users can only access their own match notifications
Broadcast::channel('matches.{user_id}', function (User $user, $user_id) {
    return (int) $user->id === (int) $user_id;
});

// Profile view notifications - users can only access their own profile view notifications
Broadcast::channel('profile-views.{user_id}', function (User $user, $user_id) {
    return (int) $user->id === (int) $user_id;
});

// System notifications - users can only access their own notifications
Broadcast::channel('notifications.{user_id}', function (User $user, $user_id) {
    return (int) $user->id === (int) $user_id;
});

// Admin channels - only admin users can access
Broadcast::channel('admin.{channel}', function (User $user, $channel) {
    return $user->hasRole('admin');
});

// Public channels - anyone can access (for announcements, etc.)
Broadcast::channel('public.{channel}', function (User $user, $channel) {
    return true;
});

// Online users presence channel - authenticated users can join
Broadcast::channel('online-users', function (User $user) {
    return [
        'id' => $user->id,
        'name' => $user->first_name . ' ' . $user->last_name,
        'profile_picture' => $user->profilePicture?->file_path,
    ];
});

// User status channel - users can only access their own status
Broadcast::channel('user-status.{user_id}', function (User $user, $user_id) {
    return (int) $user->id === (int) $user_id;
});

// Premium features channel - only premium users can access
Broadcast::channel('premium.{channel}', function (User $user, $channel) {
    return $user->hasActiveSubscription();
}); 