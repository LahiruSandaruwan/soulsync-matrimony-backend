<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\User;
use App\Models\Conversation;

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

// Chat channels
Broadcast::channel('chat.{conversationId}', function (User $user, $conversationId) {
    $conversation = Conversation::find($conversationId);
    return $conversation && $conversation->participants()->where('user_id', $user->id)->exists();
});

// Match notifications
Broadcast::channel('matches.{userId}', function (User $user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Profile view notifications
Broadcast::channel('profile-views.{userId}', function (User $user, $userId) {
    return (int) $user->id === (int) $userId;
});

// System notifications
Broadcast::channel('notifications.{userId}', function (User $user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Admin channels
Broadcast::channel('admin.{channel}', function (User $user, $channel) {
    return $user->hasRole('admin');
});

// Public channels (for announcements, etc.)
Broadcast::channel('public.{channel}', function (User $user, $channel) {
    return true; // Anyone can listen to public channels
});

// Online users presence channel
Broadcast::channel('online-users', function (User $user) {
    return [
        'id' => $user->id,
        'name' => $user->first_name . ' ' . $user->last_name,
        'profile_picture' => $user->profilePicture?->file_path,
    ];
});

// User status channel
Broadcast::channel('user-status.{userId}', function (User $user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Premium features channel
Broadcast::channel('premium.{userId}', function (User $user, $userId) {
    return (int) $user->id === (int) $userId && $user->is_premium;
});

// Voice chat channels
Broadcast::channel('voice.{conversationId}', function (User $user, $conversationId) {
    $conversation = Conversation::find($conversationId);
    return $conversation && $conversation->participants()->where('user_id', $user->id)->exists();
});

// Horoscope compatibility channel
Broadcast::channel('horoscope.{userId}', function (User $user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Subscription updates channel
Broadcast::channel('subscription.{userId}', function (User $user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Payment status channel
Broadcast::channel('payment.{userId}', function (User $user, $userId) {
    return (int) $user->id === (int) $userId;
});

// System maintenance channel
Broadcast::channel('system.maintenance', function (User $user) {
    return true; // All authenticated users can receive system notifications
});

// System announcements channel
Broadcast::channel('system.announcements', function (User $user) {
    return true; // All authenticated users can receive announcements
}); 