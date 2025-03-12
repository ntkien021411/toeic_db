<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center vh-100 bg-light">
    <div class="card shadow p-4" style="width: 400px;">
        <h3 class="text-center">Reset Your Password</h3>

        <!-- ✅ Thêm ID vào form -->
        <form id="resetPasswordForm" method="POST" action="{{ route('password.reset') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">

            <div class="mb-3">
                <label class="form-label">Old Password</label>
                <input type="password" name="old_password" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" id="new_password" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="new_password_confirmation" id="new_password_confirmation" class="form-control" required>
                <small id="password_error" class="text-danger" style="display: none;">Mật khẩu không khớp!</small>
            </div>

            <!-- Hiển thị thông báo lỗi -->
            @if(session('error'))
                <div class="alert alert-danger">
                    {{ session('error') }}
                </div>
            @endif

            <button type="submit" class="btn btn-primary w-100">Reset Password</button>
        </form>
    </div>

    <script>
    document.getElementById("resetPasswordForm").addEventListener("submit", function (event) {
        const password = document.getElementById("new_password").value;
        const confirmPassword = document.getElementById("new_password_confirmation").value;
        const passwordError = document.getElementById("password_error");

        if (password !== confirmPassword) {
            event.preventDefault()
            passwordError.style.display = "block"; // Hiện thông báo lỗi
            return;
        } else {
            passwordError.style.display = "none"; // Ẩn lỗi nếu đúng
        }
    });
    </script>
</body>
</html>
