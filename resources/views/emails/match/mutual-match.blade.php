@extends('emails.layouts.base')

@section('title', "It's a Match! - SoulSync Matrimony")

@section('content')
    <div class="text-center">
        <h1 style="color: #ec4899;">It's a Match!</h1>
    </div>

    <p class="greeting">
        Hi {{ $user->first_name }},
    </p>

    <p>
        Congratulations! You and <strong>{{ $matchedUser->first_name }}</strong> have both expressed interest in each other. This could be the start of something beautiful!
    </p>

    <div class="profile-card" style="border-color: #ec4899; border-width: 2px;">
        @if($matchedUser->photos && $matchedUser->photos->first())
            <img src="{{ $matchedUser->photos->first()->file_path }}" alt="{{ $matchedUser->first_name }}" class="profile-avatar">
        @else
            <div class="profile-avatar" style="background: linear-gradient(135deg, #ec4899 0%, #f43f5e 100%); display: flex; align-items: center; justify-content: center; font-size: 32px; color: white;">
                {{ substr($matchedUser->first_name, 0, 1) }}
            </div>
        @endif
        <p class="profile-name">{{ $matchedUser->first_name }}, {{ $matchedUser->age ?? 'N/A' }}</p>
        <p class="profile-details">
            {{ $matchedUser->profile?->current_city ?? '' }}{{ $matchedUser->profile?->current_city && $matchedUser->profile?->current_country ? ', ' : '' }}{{ $matchedUser->profile?->current_country ?? '' }}<br>
            {{ $matchedUser->profile?->occupation ?? '' }}
        </p>
    </div>

    <div class="card" style="background: linear-gradient(135deg, #fdf2f8 0%, #fff1f2 100%);">
        <p class="card-title" style="color: #be185d;">You Can Now Chat!</p>
        <p class="card-content">
            As a mutual match, you can now start a conversation. Don't keep {{ $matchedUser->first_name }} waiting - say hello!
        </p>
    </div>

    <div class="button-wrapper">
        <a href="{{ $chatUrl ?? config('app.frontend_url') . '/app/chat' }}" class="button">
            Start Conversation
        </a>
    </div>

    <hr class="divider">

    <p><strong>Tips for a great first message:</strong></p>

    <ul class="feature-list">
        <li>Reference something from their profile</li>
        <li>Ask an open-ended question</li>
        <li>Be genuine and respectful</li>
        <li>Keep it light and friendly</li>
    </ul>

    <p class="text-muted text-center">
        We're rooting for you!
    </p>
@endsection
