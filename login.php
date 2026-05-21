<?php
session_start();


if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

require 'db.php';

$error   = '';
$success = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tab = (($_POST['action'] ?? '') === 'signup') ? 'signup' : 'login';
} else {
    $tab = $_GET['tab'] ?? 'login';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $tab      = 'login';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, password FROM users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($id, $full_name, $hash);
        $stmt->fetch();

        if ($stmt->num_rows === 1 && password_verify($password, $hash)) {
            $_SESSION['user_id']   = $id;
            $_SESSION['username']  = $username;
            $_SESSION['full_name'] = $full_name;
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
        $stmt->close();
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'signup') {
    $tab       = 'signup';
    $username  = trim($_POST['new_username'] ?? '');
    $full_name = trim($_POST['full_name']    ?? '');
    $password  = $_POST['new_password']  ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    if ($username === '' || $full_name === '' || $password === '' || $confirm === '') {
        $error = 'Please fill in all fields.';
    } elseif (strlen($password) < 4) {
        $error = 'Password must be at least 4 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
      
        $chk = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $chk->bind_param('s', $username);
        $chk->execute();
        $chk->store_result();

        if ($chk->num_rows > 0) {
            $error = 'Username already taken. Please choose another.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
           
            $card = sprintf('%04d %04d %04d %04d',
                rand(1000,9999), rand(1000,9999),
                rand(1000,9999), rand(1000,9999));
            $ins = $conn->prepare(
                "INSERT INTO users (username, full_name, password, balance, card_number) VALUES (?, ?, ?, 5000.00, ?)"
            );
            $ins->bind_param('ssss', $username, $full_name, $hash, $card);
            if ($ins->execute()) {
                $success = 'Account created! You can now log in.';
                $tab     = 'login';
            } else {
                $error = 'Registration failed. Please try again.';
            }
            $ins->close();
        }
        $chk->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IsraelStateBank – Login / Sign Up</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:        #0d1117;
            --surface:   #161b22;
            --border:    #30363d;
            --blue:      #58a6ff;
            --green:     #238636;
            --green-h:   #2ea043;
            --red:       #f85149;
            --muted:     #8b949e;
            --white:     #e6edf3;
        }

        #loader {
    position: fixed;
    inset: 0;
    background: #0d1117;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    transition: opacity 0.8s ease;
}

#loader h1 {
    font-size: 32px;
    font-weight: 700;
    color: #58a6ff;
    animation: pulse 5s infinite;
}
#loader p {
    color: #8b949e;
    font-size: 14px;
    margin-top: 8px;
}
.loader-bar {
    width: 200px;
    height: 3px;
    background: #30363d;
    border-radius: 999px;
    margin-top: 24px;
    overflow: hidden;
}
.loader-bar-inner {
    height: 100%;
    width: 0%;
    background: #58a6ff;
    border-radius: 999px;
    animation: load 1s ease forwards;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.5; }
}
@keyframes load {
    to { width: 100%; }
}
#loader.hidden {
    opacity: 0;
    pointer-events: none;
}

 body {
    min-height: 100vh;
    background: url('israelbank.jpg') center/cover no-repeat fixed;
    font-family: 'Inter', sans-serif;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
body::before {
    content: '';
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.55);
    z-index: 0;
}
        body::before, body::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            filter: blur(120px);
            opacity: 0.18;
            z-index: 0;
        }
        body::before {
            width: 500px; height: 500px;
            background: #1d4ed8;
            top: -120px; left: -120px;
        }
        body::after {
            width: 400px; height: 400px;
            background: #7e22ce;
            bottom: -100px; right: -100px;
        }

        .card {
            position: relative;
            z-index: 1;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 40px 36px;
            width: 400px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.6);
        }

        .logo {
            text-align: center;
            margin-bottom: 28px;
        }
        .logo h1 {
            font-size: 26px;
            font-weight: 700;
            color: var(--blue);
            letter-spacing: -0.5px;
        }
        .logo p {
            color: var(--muted);
            font-size: 13px;
            margin-top: 4px;
        }

        
        .tabs {
            display: flex;
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 28px;
        }
        .tabs a {
            flex: 1;
            text-align: center;
            padding: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            color: var(--muted);
            background: transparent;
            transition: background 0.2s, color 0.2s;
        }
        .tabs a.active {
            background: var(--blue);
            color: #fff;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--muted);
            margin-bottom: 6px;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 11px 14px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--white);
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            margin-bottom: 16px;
            transition: border-color 0.2s;
        }
        input:focus {
            outline: none;
            border-color: var(--blue);
        }

        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            background: var(--green);
            color: #fff;
            transition: background 0.2s, transform 0.1s;
            margin-top: 4px;
        }
        .btn:hover   { background: var(--green-h); }
        .btn:active  { transform: scale(0.98); }

        .alert {
            padding: 11px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 18px;
            font-weight: 500;
        }
        .alert-error   { background: rgba(248,81,73,0.15); color: var(--red);   border: 1px solid rgba(248,81,73,0.3); }
        .alert-success { background: rgba(35,134,54,0.15);  color: #3fb950;     border: 1px solid rgba(35,134,54,0.3); }

        .hidden { display: none; }
        
    </style>
</head>
<body>
    <div id="loader">
    <h1>🏦 IsraelStateBank</h1>
    <p>Secure Digital Banking</p>
    <div class="loader-bar">
        <div class="loader-bar-inner"></div>
    </div>
</div>
<div class="card">
    <div class="logo">
        <h1>🏦 IsraelStateBank</h1>
        <p>Secure Digital Banking</p>
    </div>

    <div class="tabs">
        <a href="?tab=login"  class="<?= $tab === 'login'  ? 'active' : '' ?>">Login</a>
        <a href="?tab=signup" class="<?= $tab === 'signup' ? 'active' : '' ?>">Sign Up</a>
    </div>

    <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <!-- LOGIN FORM -->
    <?php if ($tab === 'login'): ?>
    <form method="POST" action="login.php">
        <input type="hidden" name="action" value="login">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" placeholder="e.g. admin" required autocomplete="username">

        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">

        <button type="submit" class="btn">Login</button>
    </form>
    <?php endif; ?>

    <!-- SIGNUP FORM -->
    <?php if ($tab === 'signup'): ?>
    <form method="POST" action="login.php">
        <input type="hidden" name="action" value="signup">
        <label for="full_name">Full Name</label>
        <input type="text" id="full_name" name="full_name" placeholder="e.g. Rojal Maharjan" required>

        <label for="new_username">Username</label>
        <input type="text" id="new_username" name="new_username" placeholder="e.g. rojal123" required autocomplete="off">

        <label for="new_password">Password</label>
        <input type="password" id="new_password" name="new_password" placeholder="Min. 4 characters" required autocomplete="new-password">

        <label for="confirm_password">Confirm Password</label>
        <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat password" required autocomplete="new-password">

        <button type="submit" class="btn">Create Account</button>
    </form>
    <?php endif; ?>
</div>
<script>
    setTimeout(function () {
        document.getElementById('loader').classList.add('hidden');
    }, 2000);
</script>
</body>
</html>
