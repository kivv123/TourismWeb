<?php
require 'includes/auth.php';

if (!isset($_SESSION['temp_registration'])) {
    header('Location: register.php');
    exit;
}

$temp = $_SESSION['temp_registration'];
$errors = [];

// Handle Verification Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputCode = $_POST['code'] ?? '';

    if (time() > $temp['expires']) {
        unset($_SESSION['temp_registration']);
        flash('error', 'Verification code expired. Please register again.');
        header('Location: register.php');
        exit;
    }

    if ($inputCode === $temp['code']) {
        try {
            $pdo = db();
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, 'customer', 'active')");
            $stmt->execute([$temp['name'], $temp['email'], $temp['password_hash']]);
            $userId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare('INSERT INTO customers (user_id, phone, address) VALUES (?, ?, ?)');
            $stmt->execute([$userId, $temp['phone'], $temp['address']]);

            $pdo->commit();
            unset($_SESSION['temp_registration']);

            // Manually set success message since we aren't using the default footer flash display
            session_start();
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Email verified! Please log in.'];
            header('Location: login.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Registration failed. Please try again.';
        }
    } else {
        $errors[] = 'Invalid verification code.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for the icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --ocean: #075985;
            --teal: #0f766e;
        }

        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden; /* Prevents scrolling */
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .verify-container {
            position: relative;
            height: 100vh;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #000;
        }

        .bg-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('https://images.unsplash.com/photo-1548013146-72479768bada?auto=format&fit=crop&w=1800&q=80');
            background-size: cover;
            background-position: center;
            filter: blur(10px) brightness(0.6); /* Blur and darken */
            transform: scale(1.1); /* Prevents white edges from blur */
            z-index: 1;
        }

        .verify-card {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 450px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            padding: 40px;
            margin: 20px;
        }

        .icon-box {
            width: 70px;
            height: 70px;
            background: #e0f2fe;
            color: var(--ocean);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .code-input {
            font-size: 2.5rem;
            font-weight: 800;
            letter-spacing: 0.5rem;
            text-align: center;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 20px;
            color: var(--ocean);
        }

        .code-input:focus {
            border-color: var(--teal);
            box-shadow: 0 0 0 4px rgba(15, 118, 110, 0.1);
        }

        .btn-verify {
            background: linear-gradient(135deg, var(--ocean), var(--teal));
            border: none;
            color: white;
            padding: 14px;
            font-weight: 700;
            border-radius: 12px;
            transition: transform 0.2s;
        }

        .btn-verify:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }

        .resend-link {
            color: var(--ocean);
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="verify-container">
    <div class="bg-image"></div>
    
    <div class="verify-card text-center">
        <div class="icon-box">
            <i class="fa-solid fa-shield-halved fa-2x"></i>
        </div>
        
        <h2 class="fw-bold mb-2">Verify Email</h2>
        <p class="text-muted mb-4">
            Enter the 6-digit code sent to <br>
            <span class="text-dark fw-bold"><?= htmlspecialchars($temp['email']) ?></span>
        </p>

        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger py-2 small"><?= $error ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="code" class="form-control code-input" maxlength="6" placeholder="000000" required autofocus autocomplete="off">
            
            <button type="submit" class="btn btn-verify w-100 mb-3">
                Verify & Create Account
            </button>
        </form>

        <p class="small text-muted mb-0">
            Didn't get the code? <br>
            <a href="register.php" class="resend-link">Restart Registration</a>
        </p>
    </div>
</div>

</body>
</html>