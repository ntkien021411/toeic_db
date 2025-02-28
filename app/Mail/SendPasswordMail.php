<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Account;

class SendPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public $account;
    public $newPassword;

    public function __construct(Account $account, $newPassword)
    {
        $this->account = $account;
        $this->newPassword = $newPassword;
    }

    public function build()
    {
        return $this->subject('Mật khẩu mới của bạn')
                    ->view('emails.send-password') // Phải trùng với tên file blade
                    ->with([
                        'username' => $this->account->username,
                        'email' => $this->account->email, // Sửa lỗi $this->user->email
                        'password' => $this->newPassword, 
                    ]);
    }
}
