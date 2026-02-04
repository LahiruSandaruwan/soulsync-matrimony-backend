@extends('emails.layouts.base')

@section('title', 'Welcome to SoulSync Matrimony')

@section('content')
    <h1>Welcome to SoulSync Matrimony, {{ $user->first_name }}!</h1>

    <p class="greeting">
        We're thrilled to have you join our community of singles seeking meaningful connections and lifelong partnerships.
    </p>

    <p>
        Your journey to finding your perfect match starts here. SoulSync Matrimony uses advanced compatibility matching to help you discover profiles that align with your values, interests, and preferences.
    </p>

    <div class="card">
        <p class="card-title">Get Started</p>
        <p class="card-content">
            Complete your profile to start receiving personalized match recommendations. A complete profile gets 3x more views!
        </p>
    </div>

    <div class="button-wrapper">
        <a href="{{ $completeProfileUrl ?? config('app.frontend_url') . '/app/profile/edit' }}" class="button">
            Complete Your Profile
        </a>
    </div>

    <hr class="divider">

    <p><strong>Here's what you can do next:</strong></p>

    <ul class="feature-list">
        <li>Add your photos to make a great first impression</li>
        <li>Set your partner preferences for better matches</li>
        <li>Browse profiles and express interest</li>
        <li>Upgrade to Premium for unlimited messaging</li>
    </ul>

    <p class="text-muted text-center">
        Need help getting started? Our support team is here for you at<br>
        <a href="mailto:support@soulsync.com">support@soulsync.com</a>
    </p>

    <p>
        Wishing you the best on your journey,<br>
        <strong>The SoulSync Team</strong>
    </p>
@endsection
