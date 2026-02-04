@extends('emails.layouts.base')

@section('title', 'Profile Update Required - SoulSync Matrimony')

@section('content')
    <h1>Profile Update Required</h1>

    <p class="greeting">
        Hi {{ $user->first_name }},
    </p>

    <p>
        Thank you for creating a profile on SoulSync Matrimony. After reviewing your profile, our team has identified some areas that need attention before it can be approved.
    </p>

    @if(isset($reason) && $reason)
    <div class="card" style="background-color: #fef2f2; border-color: #fecaca;">
        <p class="card-title" style="color: #dc2626;">Reason for Review</p>
        <p class="card-content">
            {{ $reason }}
        </p>
    </div>
    @endif

    <p>
        Please update your profile to address the above concerns. Common reasons for profile review include:
    </p>

    <ul style="color: #4b5563; padding-left: 20px; margin: 16px 0;">
        <li style="margin-bottom: 8px;">Incomplete or unclear profile information</li>
        <li style="margin-bottom: 8px;">Photos that don't meet our guidelines</li>
        <li style="margin-bottom: 8px;">Content that violates our community standards</li>
        <li style="margin-bottom: 8px;">Suspicious or misleading information</li>
    </ul>

    <div class="button-wrapper">
        <a href="{{ $editProfileUrl ?? config('app.frontend_url') . '/app/profile/edit' }}" class="button">
            Update Your Profile
        </a>
    </div>

    <div class="card">
        <p class="card-title">Need Help?</p>
        <p class="card-content">
            If you believe this decision was made in error or need assistance updating your profile, please contact our support team at <a href="mailto:support@soulsync.com" style="color: #ec4899;">support@soulsync.com</a>
        </p>
    </div>

    <hr class="divider">

    <p class="text-muted">
        We review all profiles to ensure a safe and genuine experience for our members. Once you've made the necessary updates, your profile will be reviewed again.
    </p>
@endsection
