<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Management Login</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for logos/icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background-image: url('../assets/login_bg.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: Arial, sans-serif;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.9);
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            max-width: 450px;
            width: 100%;
            margin: 1rem;
        }
        .login-card h2 {
            color: #26A69A;
            text-align: center;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
        }
        .form-label {
            color: #26A69A;
            font-weight: 500;
        }
        .form-control {
            border-color: #26A69A;
            border-radius: 5px;
            padding-left: 2.5rem;
        }
        .form-control:focus {
            border-color: #FFD700;
            box-shadow: 0 0 5px rgba(38, 166, 154, 0.5);
        }
        .btn-login {
            background-color: #FFD700;
            color: #fff;
            border: none;
            width: 100%;
            padding: 0.75rem;
            font-weight: bold;
            border-radius: 5px;
        }
        .btn-login:hover {
            background-color: #FFC107;
            color: #fff;
        }
        .register-link {
            color: #26A69A;
            text-align: center;
            display: block;
            margin-top: 1rem;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .register-link:hover {
            color: #FFD700;
            text-decoration: underline;
        }
        .alert {
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        .password-container, .input-container {
            position: relative;
        }
        .password-toggle, .input-icon {
            position: absolute;
            top: 75%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #26A69A;
        }
        .password-toggle {
            right: 10px;
        }
        .input-icon {
            left: 10px;
            font-size: 1rem;
        }
        .form-control.has-icon {
            padding-left: 2.5rem;
        }
        @media (max-width: 576px) {
            .login-card {
                padding: 1.5rem;
                margin: 0.5rem;
            }
            .login-card h2 {
                font-size: 1.5rem;
            }
            .form-control, .btn-login {
                font-size: 0.9rem;
            }
        }
        @media (max-width: 400px) {
            .login-card {
                padding: 1rem;
            }
            .login-card h2 {
                font-size: 1.3rem;
            }
            .form-control, .btn-login {
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-card">
        <h2><i class="fa-solid fa-sign-in-alt me-2"></i>Management Login</h2>
        <?php
        session_start();
        if (isset($_SESSION['error'])) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
            echo htmlspecialchars($_SESSION['error']);
            echo '</div>';
            unset($_SESSION['error']);
        }
        if (isset($_SESSION['success'])) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
            echo htmlspecialchars($_SESSION['success']);
            echo '</div>';
            unset($_SESSION['success']);
        }
        ?>
        <form action="../actions/login_action.php" method="POST">
            <div class="mb-3 input-container">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control has-icon" id="email" name="email" required>
                <i class="fa-solid fa-envelope input-icon"></i>
            </div>
            <div class="mb-3 password-container">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control has-icon" id="password" name="password" required>
                <i class="fa-solid fa-lock input-icon"></i>
                <i class="fa-solid fa-eye-slash password-toggle" id="togglePassword"></i>
            </div>
            <button type="submit" class="btn btn-login">Login</button>
            <a href="?page=management_register" class="register-link">Register Here</a>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Automatically hide alerts after 1 second
        document.addEventListener('DOMContentLoaded', function () {
            const alerts = document.querySelectorAll('.alert');
            if (alerts.length > 0) {
                alerts.forEach(alert => {
                    setTimeout(function () {
                        alert.classList.remove('show');
                        alert.classList.add('fade');
                        setTimeout(function () {
                            alert.remove();
                        }, 150);
                    }, 1000);
                });
            }

            // Password toggle functionality
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            togglePassword.addEventListener('click', function () {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.classList.toggle('fa-eye-slash');
                this.classList.toggle('fa-eye');
            });
        });
    </script>
</body>
</html>