<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Routing\Route;
use Throwable;

class ErrorNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $error;
    public $route;

    public function __construct(Throwable $error, Route $route = null)
    {
        $this->error = $error;
        $this->route = $route;
    }

    public function build()
    {
        return $this->view('emails.errorNotification')
            ->subject('Erro no ajuste do limite do cartão');
    }
}
