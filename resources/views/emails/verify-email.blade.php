@extends('emails.layouts.base')

@section('title', 'Verify Your Email - SoulSync Matrimony')

@section('content')
    <h1>Verify Your Email Address</h1>

    <p class="greeting">
        Hi {{ $user->first_name }},
    </p>

    <p>
        Thank you for registering with SoulSync Matrimony! To ensure the security of your account and start connecting with potential matches, please verify your email address by clicking the button below.
    </p>

    <div class="button-wrapper">
        <a href="{{ $verificationUrl }}" class="button">
            Verify Email Address
        </a>
    </div>

    <div class="card">
        <p class="card-title">Link Expires In</p>
        <p class="card-content">
            This verification link will expire in {{ $expiresIn ?? '60 minutes' }}. If the link expires, you can request a new one from your account settings.
        </p>
    </div>

    <p class="text-muted">
        If you didn't create an account with SoulSync Matrimony, you can safely ignore this email. Someone may have entered your email address by mistake.
    </p>

    <hr class="divider">

    <p class="text-muted text-center" style="font-size: 13px;">
        <strong>Can't click the button?</strong> Copy and paste this URL into your browser:<br>
        <a href="{{ $verificationUrl }}" style="word-break: break-all; color: #ec4899;">{{ $verificationUrl }}</a>
    </p>
@endsection
