<?php

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Models\User;
use App\Utilities\Mailer;

class MailController extends Controller
{
    private Mailer $mailer;

    public function __construct()
    {
        parent::__construct();

        // Mailer初期化
        $this->mailer = Mailer::getInstance();
    }

    /**
     * 会員登録メール送信処理
     *
     * @param array $request
     */
    public function sendRegisterMail(array $request): void
    {
        if (!isset($request['name']) && !isset($request['email'])) {
            $this->apiResponse(400, '会員登録メールの送信に失敗しました。');
            return;
        }
        $name = $request['name'];
        $email = $request['email'];

        // メールアドレスが登録済みならメール送信しない
        $userModel = new User();
        $searchParam = [
            'and_where' => [
                'email' => ['keyword' => $email],
            ],
        ];
        $user = $userModel->search($searchParam, 1, 1);
        if (is_array($user) && count($user) > 0) {
            $this->apiResponse(400, '既に登録済みのメールアドレスです。別のメールアドレスをお試しください。');
            return;
        }

        $key = $_ENV['APP_TOKEN_ENCRYPT_KEY'] ?? '';
        $iv = $_ENV['APP_TOKEN_ENCRYPT_IV'] ?? '';
        $tokenParam = ['name' => $name, 'email' => $email];
        $token = bin2hex(openssl_encrypt(json_encode($tokenParam), 'AES-256-CBC', $key, 0, $iv));

        $subject = '【会員管理システム】会員登録のご案内';
        $siteSslEnabled = ($_ENV['APP_SSL_ENABLED'] === 'true') ? true : false;
        $sitePrefix = $siteSslEnabled ? 'https' : 'http';
        $siteHost = $_ENV['APP_HOST'] ?? 'localhost:8080';
        $body =
            '※このメールはシステムから自動送信されています。返信は受け付けておりません。<br><br>'
            . $name . ' 様<br><br>'
            . 'この度は会員管理システムの会員登録にお申込みいただき、誠にありがとうございます。<br><br>'
            . '下記のリンクより本登録の完了をお願い申し上げます。<br><br>'
            . '<a href="' . $sitePrefix . '://' . $siteHost . '/register/?token=' . $token . '">会員登録</a>';
        $from = $_ENV['MAIL_FROM_ADDRESS'] ?? null;

        $statusCode = 200;
        $message = '会員登録メールを送信しました。メールに記載したリンクから本登録を行ってください。';
        if (!$this->mailer->sendMail($email, $subject, $body, $from)) {
            $statusCode = 401;
            $message = '会員登録メールの送信に失敗しました。';
        }

        $this->apiResponse($statusCode, $message);
    }
}
