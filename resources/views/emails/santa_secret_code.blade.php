<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Santa’s Secret Code</title>
</head>
<body style="margin:0;padding:0;background:#0f172a;font-family:system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0f172a;padding:24px 0;">
    <tr>
        <td align="center">
            <table width="600" cellpadding="0" cellspacing="0" style="background:#020617;border-radius:16px;border:1px solid #22c55e;color:#e5e7eb;">
                <!-- Header -->
                <tr>
                    <td style="padding:24px 24px 12px 24px;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="width:32px;height:32px;border-radius:16px;background:#22c55e;display:flex;align-items:center;justify-content:center;font-size:18px;">
                                🎅
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

                <!-- Body -->
                <tr>
                    <td style="padding:0 24px 16px 24px;">
                        <p style="font-size:14px;margin:0 0 12px 0;">
                            Hi <strong>{{ $project->organizer_name ?? 'Organizer' }}</strong>,
                        </p>

                        <p style="font-size:14px;margin:0 0 12px 0;">
                            Thank you for creating a new Christmas party group using our Christmas Party Planner.
                            Here are your codes:
                        </p>

                        <div style="margin:16px 0;padding:16px;border-radius:12px;background:#022c22;border:1px solid #34d399;">
                            <div style="font-size:12px;letter-spacing:0.16em;text-transform:uppercase;color:#6ee7b7;margin-bottom:8px;">
                                Join Code (share with participants)
                            </div>
                            <div style="font-size:20px;font-weight:700;color:#a7f3d0;font-family:ui-monospace,Menlo,Monaco,Consolas,'Liberation Mono','Courier New',monospace;">
                                {{ $project->unique_code }}
                            </div>
                        </div>

                        <div style="margin:16px 0;padding:16px;border-radius:12px;background:#111827;border:1px dashed #fbbf24;">
                            <div style="font-size:12px;letter-spacing:0.16em;text-transform:uppercase;color:#fde68a;margin-bottom:8px;">
                                Santa’s Secret Code (for your eyes only)
                            </div>
                            <div style="font-size:20px;font-weight:700;color:#facc15;font-family:ui-monospace,Menlo,Monaco,Consolas,'Liberation Mono','Courier New',monospace;">
                                {{ $project->santa_secret_code }}
                            </div>
                            <p style="font-size:12px;color:#e5e7eb;margin-top:10px;">
                                Keep this code private. You can use it later for admin-only actions
                                (like removing a participant, generating and sending themes and assignments,
                                viewing cards, or unlocking organizer tools).
                            </p>
                        </div>

                        <p style="font-size:13px;margin:0 0 8px 0;color:#d1d5db;">
                            You can now share the join code with everyone and start adding participants.
                        </p>

                        <p style="font-size:11px;margin:0;color:#6b7280;">
                            Wishing you a fun, and stress-free Christmas party. 🎄
                        </p>

                        <p style="font-size:12px;margin:16px 0 0 0;color:#9ca3af;">
                            If you didn’t create this group, you can ignore this email.
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
