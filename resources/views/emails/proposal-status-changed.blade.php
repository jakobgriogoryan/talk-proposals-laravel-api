<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proposal Status Updated</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <h1 style="color: #2563eb; margin-top: 0;">Proposal Status Updated</h1>
        <p>Your proposal status has been changed.</p>
    </div>

    <div style="background-color: #ffffff; padding: 20px; border: 1px solid #e5e7eb; border-radius: 8px;">
        <h2 style="color: #1f2937; margin-top: 0;">Proposal Details</h2>
        
        <p><strong>Title:</strong> {{ $proposal->title }}</p>
        <p><strong>Status Changed:</strong> 
            <span style="color: #6b7280;">{{ ucfirst($oldStatus) }}</span> 
            → 
            <span style="color: #2563eb; font-weight: bold;">{{ ucfirst($newStatus) }}</span>
        </p>
        <p><strong>Updated at:</strong> {{ $proposal->updated_at->format('F j, Y \a\t g:i A') }}</p>
    </div>

    @if($newStatus === 'approved')
        <div style="margin-top: 20px; padding: 15px; background-color: #d1fae5; border-left: 4px solid #10b981; border-radius: 4px;">
            <p style="margin: 0; color: #065f46;">
                <strong>🎉 Congratulations!</strong> Your proposal has been approved.
            </p>
        </div>
    @elseif($newStatus === 'rejected')
        <div style="margin-top: 20px; padding: 15px; background-color: #fee2e2; border-left: 4px solid #ef4444; border-radius: 4px;">
            <p style="margin: 0; color: #991b1b;">
                <strong>Notice:</strong> Your proposal has been rejected. Please review the feedback and consider submitting a new proposal.
            </p>
        </div>
    @else
        <div style="margin-top: 20px; padding: 15px; background-color: #eff6ff; border-left: 4px solid #2563eb; border-radius: 4px;">
            <p style="margin: 0;">
                <strong>Status Update:</strong> Your proposal status has been updated to {{ ucfirst($newStatus) }}.
            </p>
        </div>
    @endif

    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; text-align: center; color: #6b7280; font-size: 12px;">
        <p>This is an automated notification from the Talk Proposals system.</p>
    </div>
</body>
</html>

