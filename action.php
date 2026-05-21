<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require 'db.php';

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];
$action   = $_POST['action'];

function gen_ref($conn) {
    do {
        $ref = 'TXN' . rand(1000, 9999);
        $r = $conn->query("SELECT id FROM transactions WHERE txn_ref = '$ref' LIMIT 1");
    } while ($r && $r->num_rows > 0);
    return $ref;
}

function gen_inv_ref($conn) {
    do {
        $ref = 'INV' . rand(1000, 9999);
        $r = $conn->query("SELECT id FROM investments WHERE id = '$ref' LIMIT 1");
    } while ($r && $r->num_rows > 0);
    return $ref;
}

function get_balance($conn, $uid) {
    $r   = $conn->query("SELECT balance FROM users WHERE id = $uid");
    $row = $r->fetch_assoc();
    return $row['balance'];
}

// ── WITHDRAW ──────────────────────────────────────────────────────────────────
if ($action == 'withdraw') {
    $amount = $_POST['amount'];

    if ($amount <= 0) {
        header('Location: dashboard.php?page=withdraw&err=invalid_amount');
        exit;
    }

    $balance = get_balance($conn, $user_id);

    if ($amount > $balance) {
        header('Location: dashboard.php?page=withdraw&err=insufficient');
        exit;
    }

    $new_balance = $balance - $amount;
    $ref  = gen_ref($conn);
    $desc = 'Cash Withdrawal';

    $stmt = $conn->prepare("UPDATE users SET balance = ? WHERE id = ?");
    $stmt->bind_param('di', $new_balance, $user_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO transactions (user_id, txn_ref, description, amount, type) VALUES (?, ?, ?, ?, 'debit')");
    $stmt->bind_param('issd', $user_id, $ref, $desc, $amount);
    $stmt->execute();
    $stmt->close();

    header('Location: dashboard.php?page=withdraw&ok=1&ref=' . $ref);
    exit;
}

// ── TRANSFER ──────────────────────────────────────────────────────────────────
if ($action == 'transfer') {
    $recipient = $_POST['recipient'];
    $amount    = $_POST['amount'];

    if ($recipient == '') {
        header('Location: dashboard.php?page=transactions&err=no_recipient');
        exit;
    }

    if ($amount <= 0) {
        header('Location: dashboard.php?page=transactions&err=invalid_amount');
        exit;
    }

    $balance = get_balance($conn, $user_id);

    if ($amount > $balance) {
        header('Location: dashboard.php?page=transactions&err=insufficient');
        exit;
    }

    $new_balance = $balance - $amount;
    $ref  = gen_ref($conn);
    $desc = 'Transfer to ' . $recipient;

    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param('s', $recipient);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($recipient_id);
    $stmt->fetch();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE users SET balance = ? WHERE id = ?");
    $stmt->bind_param('di', $new_balance, $user_id);
    $stmt->execute();
    $stmt->close();

    if ($recipient_id) {
        $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->bind_param('di', $amount, $recipient_id);
        $stmt->execute();
        $stmt->close();

        $recv_desc = 'Received from ' . $username;
        $recv_ref  = gen_ref($conn);
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, txn_ref, description, amount, type) VALUES (?, ?, ?, ?, 'credit')");
        $stmt->bind_param('issd', $recipient_id, $recv_ref, $recv_desc, $amount);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $conn->prepare("INSERT INTO transactions (user_id, txn_ref, description, amount, type) VALUES (?, ?, ?, ?, 'debit')");
    $stmt->bind_param('issd', $user_id, $ref, $desc, $amount);
    $stmt->execute();
    $stmt->close();

    header('Location: dashboard.php?page=transactions&ok=1&ref=' . $ref . '&to=' . $recipient);
    exit;
}

// ── INVEST ────────────────────────────────────────────────────────────────────
if ($action == 'invest') {
    $plan_name = $_POST['plan_name'];
    $plan_icon = $_POST['plan_icon'];
    $roi_rate  = $_POST['plan_roi'];
    $dur_label = $_POST['plan_dur'];
    $min_amt   = $_POST['plan_min'];
    $amount    = $_POST['amount'];

    if ($amount <= 0) {
        header('Location: dashboard.php?page=invest&err=invalid_amount');
        exit;
    }

    if ($amount < $min_amt) {
        header('Location: dashboard.php?page=invest&err=below_min');
        exit;
    }

    $balance = get_balance($conn, $user_id);

    if ($amount > $balance) {
        header('Location: dashboard.php?page=invest&err=insufficient');
        exit;
    }

    $roi_amount      = round(($amount * $roi_rate) / 100, 2);
    $maturity_amount = $amount + $roi_amount;
    $new_balance     = $balance - $amount;
    $ref             = gen_inv_ref($conn);
    $today           = date('Y-m-d');
    $desc            = 'Invested in ' . $plan_name;

    // Deduct capital from balance
    $stmt = $conn->prepare("UPDATE users SET balance = ? WHERE id = ?");
    $stmt->bind_param('di', $new_balance, $user_id);
    $stmt->execute();
    $stmt->close();

    // Record debit transaction
    $txn_ref = gen_ref($conn);
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, txn_ref, description, amount, type) VALUES (?, ?, ?, ?, 'debit')");
    $stmt->bind_param('issd', $user_id, $txn_ref, $desc, $amount);
    $stmt->execute();
    $stmt->close();

    // Save investment record
    $stmt = $conn->prepare("INSERT INTO investments (id, user_id, plan_name, plan_icon, capital, roi_rate, roi_amount, maturity_amount, duration_label, start_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
    $stmt->bind_param('sisssdddss', $ref, $user_id, $plan_name, $plan_icon, $amount, $roi_rate, $roi_amount, $maturity_amount, $dur_label, $today);
    $stmt->execute();
    $stmt->close();

    header('Location: dashboard.php?page=invest&ok=1&ref=' . $ref);
    exit;
}

// ── CLAIM MATURITY ────────────────────────────────────────────────────────────
if ($action == 'claim_maturity') {
    $inv_id = $_POST['investment_id'];

    // Fetch investment — must belong to this user and still be active
    $stmt = $conn->prepare("SELECT maturity_amount, plan_name FROM investments WHERE id = ? AND user_id = ? AND status = 'active'");
    $stmt->bind_param('si', $inv_id, $user_id);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($maturity_amount, $plan_name);
    $found = $stmt->fetch();
    $stmt->close();

    if (!$found) {
        header('Location: dashboard.php?page=invest&err=invalid_amount');
        exit;
    }

    $balance     = get_balance($conn, $user_id);
    $new_balance = $balance + $maturity_amount;
    $ref         = gen_ref($conn);
    $desc        = 'Maturity: ' . $plan_name;

    // Credit maturity amount to balance
    $stmt = $conn->prepare("UPDATE users SET balance = ? WHERE id = ?");
    $stmt->bind_param('di', $new_balance, $user_id);
    $stmt->execute();
    $stmt->close();

    // Record credit transaction
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, txn_ref, description, amount, type) VALUES (?, ?, ?, ?, 'credit')");
    $stmt->bind_param('issd', $user_id, $ref, $desc, $maturity_amount);
    $stmt->execute();
    $stmt->close();

    // Mark investment as matured
    $stmt = $conn->prepare("UPDATE investments SET status = 'matured' WHERE id = ?");
    $stmt->bind_param('s', $inv_id);
    $stmt->execute();
    $stmt->close();

    header('Location: dashboard.php?page=invest&ok=1&matured=1&ref=' . $ref);
    exit;
}

header('Location: dashboard.php');
exit;