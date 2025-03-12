<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public $resetLink;

    public function __construct($resetLink)
    {
        $this->resetLink = $resetLink;
    }

    public function build()
    {
        return $this->subject('Khôi phục mật khẩu')
            ->view('emails.reset_password')
            ->with([
                'resetLink' => $this->resetLink, // ✅ Đảm bảo truyền đúng tên biến
            ]);
    }
}
