<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Proposal;
use App\Models\Review;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable for proposal reviewed notification.
 */
class ProposalReviewedNotification extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Proposal $proposal,
        public Review $review,
        public User $speaker
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Proposal Has Been Reviewed: '.$this->proposal->title,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.proposal-reviewed',
            with: [
                'proposal' => $this->proposal,
                'review' => $this->review,
                'speaker' => $this->speaker,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}

