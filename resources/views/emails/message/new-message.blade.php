@extends('emails.layouts.base')

@section('title', 'New Message - SoulSync Matrimony')

@section('content')
    <h1>You Have a New Message</h1>

    <p class="greeting">
        Hi {{ $user->first_name }},
    </p>

    <p>
        <strong>{{ $sender->first_name }}</strong> has sent you a message on SoulSync Matrimony.
    </p>

    <div class="profile-card">
        @if($sender->photos && $sender->photos->first())
            <img src="{{ $sender->photos->first()->file_path }}" alt="{{ $sender->first_name }}" class="profile-avatar">
        @else
            <div class="profile-avatar" style="background: linear-gradient(135deg, #ec4899 0%, #f43f5e 100%); display: flex; align-items: center; justify-content: center; font-size: 32px; color: white;">
                {{ substr($sender->first_name, 0, 1) }}
            </div>
        @endif
        <p class="profile-name">{{ $sender->first_name }}</p>
    </div>

    @if(isset($preview) && $preview)
    <div class="card">
        <p class="card-title">Message Preview</p>
        <p class="card-content" style="font-style: italic;">
            "{{ Str::limit($preview, 150) }}"
        </p>
    </div>
    @endif

    <div class="button-wrapper">
        <a href="{{ $chatUrl ?? config('app.frontend_url') . '/app/chat' }}" class="button">
            Reply to Message
        </a>
    </div>

    <hr class="divider">

    <p class="text-muted text-center">
        Quick responses lead to better connections! Don't leave {{ $sender->first_name }} waiting.
    </p>
@endsection
