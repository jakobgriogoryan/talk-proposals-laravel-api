<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Proposal Has Been Reviewed</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <h1 style="color: #2563eb; margin-top: 0;">Your Proposal Has Been Reviewed</h1>
        <p>A reviewer has submitted feedback on your proposal.</p>
    </div>

    <div style="background-color: #ffffff; padding: 20px; border: 1px solid #e5e7eb; border-radius: 8px;">
        <h2 style="color: #1f2937; margin-top: 0;">Proposal Details</h2>
        
        <p><strong>Title:</strong> {{ $proposal->title }}</p>
        <p><strong>Reviewed by:</strong> {{ $review->reviewer->name }}</p>
        <p><strong>Rating:</strong> 
            @for($i = 1; $i <= 5; $i++)
                @if($i <= $review->rating)
                    <span style="color: #fbbf24;">★</span>
                @else
                    <span style="color: #d1d5db;">★</span>
                @endif
            @endfor
            ({{ $review->rating }}/5)
        </p>
        <p><strong>Reviewed at:</strong> {{ $review->created_at->format('F j, Y \a\t g:i A') }}</p>

        @if($review->comment)
            <div style="margin-top: 15px; padding: 15px; background-color: #f9fafb; border-left: 4px solid #2563eb; border-radius: 4px;">
                <p style="margin: 0;"><strong>Reviewer Comment:</strong></p>
                <p style="margin-top: 10px; margin-bottom: 0;">{{ $review->comment }}</p>
            </div>
        @endif
    </div>

    <div style="margin-top: 20px; padding: 15px; background-color: #eff6ff; border-left: 4px solid #2563eb; border-radius: 4px;">
        <p style="margin: 0;">
            <strong>Next Steps:</strong> You can view the full review details in your proposal dashboard.
        </p>
    </div>

    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; text-align: center; color: #6b7280; font-size: 12px;">
        <p>This is an automated notification from the Talk Proposals system.</p>
    </div>
</body>
</html>

