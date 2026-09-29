<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Your Secret Santa Gift List</title>
</head>
<body style="
    margin:0;
    padding:0;
    font-family:system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
    background-color:#020617;
    /* Snowy night effect (fallbacks to solid color where unsupported) */
    background-image:
        radial-gradient(circle at 10px 10px, rgba(248,250,252,0.18) 0, transparent 3px),
        radial-gradient(circle at 90px 40px, rgba(248,250,252,0.14) 0, transparent 3px),
        radial-gradient(circle at 160px 80px, rgba(248,250,252,0.16) 0, transparent 3px),
        radial-gradient(circle at 40px 120px, rgba(248,250,252,0.18) 0, transparent 3px);
    background-size:200px 200px;
">
<table width="100%" cellpadding="0" cellspacing="0" style="
    padding:24px 0;
    background-color:transparent;
">
    <tr>
        <td align="center">
            <table width="600" cellpadding="0" cellspacing="0" style="
                background:#020617;
                border-radius:16px;
                border:1px solid #f97316;
                color:#e5e7eb;
                /* Subtle inner snow so it still looks nice if body bg is stripped */
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
                                🎁
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
                            Hi <strong>{{ $participant->name }}</strong>,
                        </p>
                        <p style="font-size:14px;margin:0 0 12px 0;">
                            Here is your Secret Santa gift list for
                            <strong>{{ $project->name ?? 'our exchange gifts' }}</strong>.
                        </p>

                        <div style="
                            margin:16px 0;
                            padding:16px 16px 12px 16px;
                            border-radius:12px;
                            background:#111827;
                            border:1px solid #fbbf24;
                            box-shadow:0 0 20px rgba(251,191,36,0.15);
                        ">
                            <div style="
                                font-size:12px;
                                letter-spacing:0.16em;
                                text-transform:uppercase;
                                color:#fde68a;
                                margin-bottom:4px;
                            ">
                                Your assigned recipients
                            </div>
                            <p style="font-size:14px;margin:0 0 8px 0;color:#e5e7eb;">
                                You will prepare
                                <strong>{{ $giftsEach }}</strong>
                                gift{{ $giftsEach === 1 ? '' : 's' }} for:
                            </p>

                            <ul style="margin:4px 0 0 18px;padding:0;font-size:14px;color:#facc15;">
                                @foreach ($recipients as $name)
                                    <li style="margin-bottom:4px;">{{ $name }}</li>
                                @endforeach
                            </ul>
                        </div>

                        {{-- 🔹 Budget winner section --}}
                        @if(!empty($budgetWinner))
                            <div style="
                                margin:8px 0 16px 0;
                                padding:12px 16px;
                                border-radius:12px;
                                background:#022c22;
                                border:1px solid #34d399;
                            ">
                                <div style="
                                    font-size:12px;
                                    letter-spacing:0.16em;
                                    text-transform:uppercase;
                                    color:#6ee7b7;
                                    margin-bottom:4px;
                                ">
                                    Suggested Budget (Group Poll)
                                </div>

                                @if($budgetWinner['is_tie'])
                                    <p style="font-size:13px;margin:0;color:#d1fae5;">
                                        Current leading budgets (tie with
                                        {{ $budgetWinner['max_votes'] }}
                                        vote{{ $budgetWinner['max_votes'] === 1 ? '' : 's' }} each):
                                        <strong>{{ implode(', ', $budgetWinner['labels']) }}</strong>.
                                    </p>
                                @else
                                    <p style="font-size:13px;margin:0;color:#d1fae5;">
                                        Based on everyone’s votes ({{ $budgetWinner['total_votes'] }} total),
                                        the current suggested budget is
                                        <strong>{{ $budgetWinner['labels'][0] }}</strong>
                                        with {{ $budgetWinner['max_votes'] }}
                                        vote{{ $budgetWinner['max_votes'] === 1 ? '' : 's' }}.
                                    </p>
                                @endif
                            </div>
                        @endif

                        <p style="font-size:13px;margin:0 0 8px 0;color:#d1d5db;">
                            Please keep this list secret 🤫 and bring all your gifts on the day
                            of the exchange.
                        </p>

                        <p style="font-size:12px;margin:16px 0 0 0;color:#9ca3af;">
                            If you didn’t request this email, you can ignore it. Someone might have
                            typed the wrong address by mistake.
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
