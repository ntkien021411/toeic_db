<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khôi phục mật khẩu - Toeic App</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            background-color: #f4f4f4;
            padding: 20px;
            text-align: center;
        }
        .container {
            max-width: 600px;
            background: #ffffff;
            padding: 20px;
            margin: 0 auto;
            border-radius: 8px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        h2 {
            color: #007bff;
        }
        p {
            font-size: 16px;
            color: #555;
        }
        .password {
            font-size: 20px;
            font-weight: bold;
            color: #d9534f;
        }
        .footer {
            margin-top: 20px;
            font-size: 14px;
            color: #777;
        }
        .banner {
            width: 100%;
            max-height: 200px;
            object-fit: cover;
            border-radius: 8px;
        }
        .button {
            display: inline-block;
            background: #007bff;
            color: #ffffff;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-size: 16px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Ảnh banner của website Toeic -->
        <img src="https://your-website.com/images/toeic-banner.jpg" alt="Toeic App" class="banner">

        <h2>Xin chào, {{ $username }}</h2>
        <p>Bạn vừa yêu cầu khôi phục mật khẩu trên <strong>Toeic App</strong>.</p>
        <p>Email của bạn: <strong>{{ $email }}</strong></p>
        <p>Mật khẩu mới của bạn:</p>
        <p class="password">{{ $password }}</p>

        <p>Vui lòng đăng nhập và đổi mật khẩu ngay để đảm bảo an toàn!</p>

        <div class="footer">
            <p>Đây là email tự động, vui lòng không trả lời.</p>
            <p>Nếu bạn không yêu cầu thay đổi mật khẩu, hãy bỏ qua email này.</p>
        </div>
    </div>
</body>
</html>
