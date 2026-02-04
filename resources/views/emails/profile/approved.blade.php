@extends('emails.layouts.base')

@section('title', 'Profile Approved - SoulSync Matrimony')

@section('content')
    <div class="text-center">
        <h1 style="color: #10b981;">Your Profile is Approved!</h1>
    </div>

    <p class="greeting">
        Hi {{ $user->first_name }},
    </p>

    <p>
        Great news! Your profile has been reviewed and approved by our team. You're now visible to other members on SoulSync Matrimony and can start receiving match suggestions.
    </p>

    <div class="card" style="background-color: #ecfdf5; border-color: #6ee7b7;">
        <p class="card-title" style="color: #059669;">What This Means</p>
        <p class="card-content">
            Your profile is now live and other members can discover you. Start browsing profiles and express your interest to find your perfect match!
        </p>
    </div>

    <div class="button-wrapper">
        <a href="{{ $dashboardUrl ?? config('app.frontend_url') . '/app/dashboard' }}" class="button" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
            Go to Dashboard
        </a>
    </div>

    <hr class="divider">

    <p><strong>Tips to get more matches:</strong></p>

    <ul class="feature-list">
        <li>Add more photos to showcase your personality</li>
        <li>Complete all sections of your profile</li>
        <li>Write a compelling "About Me" section</li>
        <li>Set accurate partner preferences</li>
        <li>Log in regularly to stay active</li>
    </ul>

    <p class="text-muted text-center">
        We're excited to help you find your perfect match!
    </p>
@endsection
