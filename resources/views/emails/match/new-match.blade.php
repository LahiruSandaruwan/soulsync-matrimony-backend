@extends('emails.layouts.base')

@section('title', 'You Have a New Match! - SoulSync Matrimony')

@section('content')
    <h1>Someone is Interested in You!</h1>

    <p class="greeting">
        Hi {{ $user->first_name }},
    </p>

    <p>
        Great news! <strong>{{ $matchedUser->first_name }}</strong> has shown interest in your profile on SoulSync Matrimony.
    </p>

    <div class="profile-card">
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

    @if(isset($compatibilityScore) && $compatibilityScore > 0)
    <div class="card">
        <p class="card-title">Compatibility Score</p>
        <p class="card-content" style="font-size: 24px; font-weight: 600; color: #ec4899;">
            {{ $compatibilityScore }}% Match
        </p>
    </div>
    @endif

    <div class="button-wrapper">
        <a href="{{ $matchUrl ?? config('app.frontend_url') . '/app/matches' }}" class="button">
            View Profile & Respond
        </a>
    </div>

    <p class="text-muted text-center">
        Express your interest back to start a conversation!
    </p>

    <hr class="divider">

    <p class="text-muted" style="font-size: 13px;">
        <strong>Pro tip:</strong> Upgrade to Premium to send unlimited messages and see who viewed your profile.
    </p>
@endsection
