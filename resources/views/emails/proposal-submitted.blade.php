<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Proposal Submitted</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <h1 style="color: #2563eb; margin-top: 0;">New Proposal Submitted</h1>
        <p>A new proposal has been submitted and requires your review.</p>
    </div>

    <div style="background-color: #ffffff; padding: 20px; border: 1px solid #e5e7eb; border-radius: 8px;">
        <h2 style="color: #1f2937; margin-top: 0;">Proposal Details</h2>
        
        <p><strong>Title:</strong> {{ $proposal->title }}</p>
        <p><strong>Description:</strong> {{ Str::limit($proposal->description, 200) }}</p>
        <p><strong>Submitted by:</strong> {{ $proposal->user->name }} ({{ $proposal->user->email }})</p>
        <p><strong>Status:</strong> {{ ucfirst($proposal->status->value) }}</p>
        <p><strong>Submitted at:</strong> {{ $proposal->created_at->format('F j, Y \a\t g:i A') }}</p>

        @if($proposal->tags->count() > 0)
            <p><strong>Tags:</strong> {{ $proposal->tags->pluck('name')->join(', ') }}</p>
        @endif
    </div>

    <div style="margin-top: 20px; padding: 15px; background-color: #eff6ff; border-left: 4px solid #2563eb; border-radius: 4px;">
        <p style="margin: 0;">
            <strong>Action Required:</strong> Please review this proposal in the admin dashboard.
        </p>
    </div>

    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; text-align: center; color: #6b7280; font-size: 12px;">
        <p>This is an automated notification from the Talk Proposals system.</p>
    </div>
</body>
</html>

