@component('mail::message')
# Ticket Submitted

Hi {{ $clientName }},

Your support ticket has been **created**.

**Reference No:** `{{ $refNo }}`

@isset($problem)
**Problem:** {{ $problem }}
@endisset

@isset($employeeCode)
@component('mail::button', ['url' => 'http://ticketing.stii.local/client/rate/' . rawurlencode($refNo)])
Track this Ticket
@endcomponent
@endisset

If you didn’t submit this request, please reply to this email.

Thanks, IT Unit
@endcomponent
