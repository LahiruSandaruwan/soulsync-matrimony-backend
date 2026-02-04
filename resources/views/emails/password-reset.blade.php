@extends('emails.layouts.base')

@section('title', 'Reset Your Password - SoulSync Matrimony')

@section('content')
    <h1>Reset Your Password</h1>

    <p class="greeting">
        Hi {{ $user->first_name ?? 'there' }},
    </p>

    <p>
        We received a request to reset your password for your SoulSync Matrimony account. Click the button below to create a new password.
    </p>

    <div class="button-wrapper">
        <a href="{{ $resetUrl }}" class="button">
            Reset Password
        </a>
    </div>

    <div class="card">
        <p class="card-title">Important</p>
        <p class="card-content">
            This password reset link will expire in {{ $expiresIn ?? '60 minutes' }}. If you didn't request a password reset, please ignore this email or contact support if you're concerned about your account security.
        </p>
    </div>

    <hr class="divider">

    <p class="text-muted">
        <strong>Security tip:</strong> SoulSync will never ask for your password via email. Always access your account through our official website.
    </p>

    <p class="text-muted text-center" style="font-size: 13px;">
        <strong>Can't click the button?</strong> Copy and paste this URL into your browser:<br>
        <a href="{{ $resetUrl }}" style="word-break: break-all; color: #ec4899;">{{ $resetUrl }}</a>
    </p>
@endsection
