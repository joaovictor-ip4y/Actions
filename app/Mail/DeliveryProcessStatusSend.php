<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DeliveryProcessStatusSend extends Mailable
{
    use Queueable, SerializesModels;

    public $dataCard;
    public $status;

    /**
     * Create a new message instance.
     *
     * @param array $dataCard
     * @param string $status
     * @return void
     */
    public function __construct(array $dataCard, string $status)
    {
        $this->dataCard = $dataCard;
        $this->status = $status;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this
            ->subject('Atualização no Status do Seu Cartão')
            ->view('emails.delivery.started')
            ->with([
                'content' => $this->dataCard,
                'status' => $this->status,
            ]);
    }
}
