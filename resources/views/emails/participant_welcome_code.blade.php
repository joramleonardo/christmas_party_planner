<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Welcome to the Exchange Gift Group</title>
</head>
<body style="
    margin:0;
    padding:0;
    font-family:system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
    background-color:#020617;
    background-image:
        radial-gradient(circle at 10px 10px, rgba(248,250,252,0.18) 0, transparent 3px),
        radial-gradient(circle at 90px 40px, rgba(248,250,252,0.14) 0, transparent 3px),
        radial-gradient(circle at 160px 80px, rgba(248,250,252,0.16) 0, transparent 3px),
        radial-gradient(circle at 40px 120px, rgba(248,250,252,0.18) 0, transparent 3px);
    background-size:200px 200px;
">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:24px 0;">
    <tr>
        <td align="center">
            <table width="600" cellpadding="0" cellspacing="0" style="
                background:#020617;
                border-radius:16px;
                border:1px solid #f97316;
                color:#e5e7eb;
                background-image:
                    radial-gradient(circle at 20px 20px, rgba(248,250,252,0.12) 0, transparent 3px),
                    radial-gradient(circle at 200px 80px, rgba(248,250,252,0.10) 0, transparent 3px),
                    radial-gradient(circle at 380px 40px, rgba(248,250,252,0.14) 0, transparent 3px);
                background-size:260px 220px;
            ">
                <!-- Header -->
                <tr>
                    <td style="padding:24px 24px 12px 24px;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="
                                width:32px;
                                height:32px;
                                border-radius:16px;
                                background:#f97316;
                                display:flex;
                                align-items:center;
                                justify-content:center;
                                font-size:18px;
                            ">
                                🎄
                            </div>
                            <div>
                                <div style="font-size:13px;letter-spacing:0.16em;text-transform:uppercase;color:#bae6fd;">
                                    Christmas Party Planner
                                </div>
                                <h1 style="margin:4px 0 0 0;font-size:18px;color:#e0f2fe;">
                                    {{ $project->name}}
                                </h1>
                            </div>
                        </div>
                    </td>
                </tr>

                <!-- Main content -->
                <tr>
                    <td style="padding:0 24px 16px 24px;">
                        <p style="font-size:14px;margin:0 0 12px 0;">
                            Hi
                            <strong>
                                {{ $participant->name }}
                            </strong>,
                        </p>

                        <p style="font-size:14px;margin:0 0 12px 0;">
                            Thank you for joining {{ $project->name}}!
                        </p>

                        <div style="
                            margin:16px 0;
                            padding:16px;
                            border-radius:12px;
                            background:#111827;
                            border:1px solid #fbbf24;
                        ">
                            <div style="
                                font-size:12px;
                                letter-spacing:0.16em;
                                text-transform:uppercase;
                                color:#fde68a;
                                margin-bottom:4px;
                            ">
                                Participant Details
                            </div>

                            <p style="font-size:13px;margin:0 0 6px 0;color:#e5e7eb;">
                                <strong>Name:</strong>
                                {{ $participant->name }}
                            </p>

                            <p style="font-size:13px;margin:0 0 6px 0;color:#e5e7eb;">
                                <strong>Codename:</strong>
                                {{ $participant->codename }}
                            </p>

                            {{-- ✅ Wishlist section with descriptions --}}
                            @php
                                $hasWishlist = $participant->wishlist_1 || $participant->wishlist_2 || $participant->wishlist_3
                                    || $participant->wishlist_1_description || $participant->wishlist_2_description || $participant->wishlist_3_description;
                            @endphp

                            @if($hasWishlist)
                                <div style="margin:8px 0 6px 0;">
                                    <p style="font-size:13px;margin:0 0 4px 0;color:#e5e7eb;">
                                        <strong>Wishlist:</strong>
                                    </p>
                                    <ul style="font-size:13px;margin:0 0 0 18px;padding:0;color:#e5e7eb;">
                                        @if($participant->wishlist_1 || $participant->wishlist_1_description)
                                            <li style="margin-bottom:6px;">
                                                @if($participant->wishlist_1)
                                                    <div>
                                                        <strong>{{ $participant->wishlist_1 }}</strong>
                                                    </div>
                                                @endif
                                                @if($participant->wishlist_1_description)
                                                    <div style="font-size:12px;color:#9ca3af;margin-top:2px;">
                                                        {{ $participant->wishlist_1_description }}
                                                    </div>
                                                @endif
                                            </li>
                                        @endif

                                        @if($participant->wishlist_2 || $participant->wishlist_2_description)
                                            <li style="margin-bottom:6px;">
                                                @if($participant->wishlist_2)
                                                    <div>
                                                        <strong>{{ $participant->wishlist_2 }}</strong>
                                                    </div>
                                                @endif
                                                @if($participant->wishlist_2_description)
                                                    <div style="font-size:12px;color:#9ca3af;margin-top:2px;">
                                                        {{ $participant->wishlist_2_description }}
                                                    </div>
                                                @endif
                                            </li>
                                        @endif

                                        @if($participant->wishlist_3 || $participant->wishlist_3_description)
                                            <li style="margin-bottom:6px;">
                                                @if($participant->wishlist_3)
                                                    <div>
                                                        <strong>{{ $participant->wishlist_3 }}</strong>
                                                    </div>
                                                @endif
                                                @if($participant->wishlist_3_description)
                                                    <div style="font-size:12px;color:#9ca3af;margin-top:2px;">
                                                        {{ $participant->wishlist_3_description }}
                                                    </div>
                                                @endif
                                            </li>
                                        @endif
                                    </ul>
                                </div>
                            @endif

                            <p style="font-size:13px;margin:8px 0 0 0;color:#e5e7eb;">
                                <strong>Unique Code (for your eyes only):</strong>
                                <span style="
                                    display:inline-block;
                                    padding:4px 8px;
                                    border-radius:999px;
                                    background:#0f172a;
                                    border:1px solid #facc15;
                                    font-family:monospace;
                                    font-size:13px;
                                ">
                                    {{ $participant->unique_code }}
                                </span>
                            </p>
                        </div>

                        <p style="font-size:13px;margin:0 0 8px 0;color:#d1d5db;">
                            Keep your <strong>Codename</strong> and <strong>Unique code</strong> safe. You’ll use it when you:
                        </p>
                        <ul style="font-size:13px;margin:0 0 8px 18px;color:#d1d5db;padding:0;">
                            <li>Vote for awards</li>
                            <li>Join games or other activities</li>
                            <li>Identify yourself anonymously during the activities</li>
                        </ul>

                        <p style="font-size:11px;margin:0;color:#6b7280;">
                            Please keep your codename and unique code secret until the party. 🤫
                        </p>

                        <p style="font-size:12px;margin:16px 0 0 0;color:#9ca3af;">
                            If you didn’t request to join this group, you can ignore this email. Someone might
                            have typed the wrong email address by mistake.
                        </p>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td style="padding:16px 24px 20px 24px;border-top:1px solid #1f2937;">
                        <p style="font-size:11px;margin:0;color:#6b7280;">
                            Sent by <strong>JKLeonardo Tech</strong><br>
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
