<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>@yield('title', 'SoulSync Matrimony')</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        /* Reset styles */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }

        /* Base styles */
        body {
            margin: 0 !important;
            padding: 0 !important;
            background-color: #f4f4f7;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        /* Container */
        .email-wrapper {
            width: 100%;
            background-color: #f4f4f7;
            padding: 30px 0;
        }

        .email-content {
            max-width: 600px;
            margin: 0 auto;
        }

        /* Header */
        .email-header {
            background: linear-gradient(135deg, #ec4899 0%, #f43f5e 100%);
            padding: 30px 40px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }

        .logo {
            font-size: 28px;
            font-weight: 700;
            color: #ffffff;
            text-decoration: none;
            letter-spacing: -0.5px;
        }

        .logo-heart {
            color: #fecdd3;
        }

        /* Body */
        .email-body {
            background-color: #ffffff;
            padding: 40px;
        }

        .email-body h1 {
            color: #1f2937;
            font-size: 24px;
            font-weight: 600;
            margin: 0 0 20px 0;
            line-height: 1.3;
        }

        .email-body p {
            color: #4b5563;
            font-size: 16px;
            line-height: 1.6;
            margin: 0 0 16px 0;
        }

        .email-body .greeting {
            color: #1f2937;
            font-size: 18px;
            margin-bottom: 24px;
        }

        /* Button */
        .button-wrapper {
            text-align: center;
            margin: 32px 0;
        }

        .button {
            display: inline-block;
            background: linear-gradient(135deg, #ec4899 0%, #f43f5e 100%);
            color: #ffffff !important;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(236, 72, 153, 0.25);
        }

        .button:hover {
            background: linear-gradient(135deg, #db2777 0%, #e11d48 100%);
        }

        .button-secondary {
            background: #f3f4f6;
            color: #374151 !important;
            box-shadow: none;
            border: 1px solid #d1d5db;
        }

        /* Card */
        .card {
            background-color: #fdf2f8;
            border: 1px solid #fbcfe8;
            border-radius: 8px;
            padding: 20px;
            margin: 24px 0;
        }

        .card-title {
            color: #be185d;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 12px 0;
        }

        .card-content {
            color: #1f2937;
            font-size: 16px;
            margin: 0;
        }

        /* Profile Card */
        .profile-card {
            background-color: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 24px;
            margin: 24px 0;
            text-align: center;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 16px;
            border: 3px solid #ec4899;
        }

        .profile-name {
            color: #1f2937;
            font-size: 20px;
            font-weight: 600;
            margin: 0 0 8px 0;
        }

        .profile-details {
            color: #6b7280;
            font-size: 14px;
            margin: 0;
        }

        /* Divider */
        .divider {
            border: 0;
            border-top: 1px solid #e5e7eb;
            margin: 32px 0;
        }

        /* List */
        .feature-list {
            margin: 24px 0;
            padding: 0;
            list-style: none;
        }

        .feature-list li {
            color: #4b5563;
            font-size: 15px;
            padding: 8px 0 8px 28px;
            position: relative;
        }

        .feature-list li:before {
            content: '✓';
            color: #10b981;
            font-weight: 600;
            position: absolute;
            left: 0;
        }

        /* Footer */
        .email-footer {
            background-color: #f9fafb;
            padding: 30px 40px;
            text-align: center;
            border-radius: 0 0 8px 8px;
            border-top: 1px solid #e5e7eb;
        }

        .footer-links {
            margin-bottom: 20px;
        }

        .footer-links a {
            color: #6b7280;
            font-size: 13px;
            text-decoration: none;
            margin: 0 12px;
        }

        .footer-links a:hover {
            color: #ec4899;
        }

        .social-links {
            margin-bottom: 20px;
        }

        .social-links a {
            display: inline-block;
            margin: 0 8px;
        }

        .social-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: #e5e7eb;
        }

        .footer-text {
            color: #9ca3af;
            font-size: 12px;
            line-height: 1.5;
            margin: 0;
        }

        .footer-text a {
            color: #ec4899;
            text-decoration: none;
        }

        /* Utility */
        .text-center {
            text-align: center;
        }

        .text-muted {
            color: #9ca3af;
            font-size: 14px;
        }

        .mt-0 { margin-top: 0; }
        .mb-0 { margin-bottom: 0; }
        .mb-16 { margin-bottom: 16px; }
        .mb-24 { margin-bottom: 24px; }

        /* Responsive */
        @media only screen and (max-width: 600px) {
            .email-body, .email-header, .email-footer {
                padding: 24px 20px !important;
            }

            .email-body h1 {
                font-size: 20px;
            }

            .button {
                display: block;
                width: 100%;
                box-sizing: border-box;
            }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-content">
            <!-- Header -->
            <div class="email-header">
                <a href="{{ config('app.frontend_url', 'https://soulsync.com') }}" class="logo">
                    Soul<span class="logo-heart">Sync</span>
                </a>
            </div>

            <!-- Body -->
            <div class="email-body">
                @yield('content')
            </div>

            <!-- Footer -->
            <div class="email-footer">
                <div class="footer-links">
                    <a href="{{ config('app.frontend_url') }}/browse">Browse Profiles</a>
                    <a href="{{ config('app.frontend_url') }}/app/settings">Settings</a>
                    <a href="{{ config('app.frontend_url') }}/support">Help Center</a>
                </div>

                <p class="footer-text">
                    You're receiving this email because you have an account with SoulSync Matrimony.<br>
                    @if(isset($unsubscribeUrl))
                        <a href="{{ $unsubscribeUrl }}">Unsubscribe</a> from these notifications.
                    @endif
                </p>

                <hr class="divider" style="margin: 20px 0;">

                <p class="footer-text">
                    &copy; {{ date('Y') }} SoulSync Matrimony. All rights reserved.<br>
                    <a href="{{ config('app.frontend_url') }}/privacy">Privacy Policy</a> &middot;
                    <a href="{{ config('app.frontend_url') }}/terms">Terms of Service</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
