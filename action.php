<?php
session_start();

if(!isset($_SESSION['user_id'])){
    header('Location: login.php');
    exit;
}

include 'db.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$action = $_POST['action'];


// withdraw
if($action == 'withdraw'){

    $amount = $_POST['amount'];
    
    $result = mysqli_query($conn, "SELECT balance FROM users WHERE id = $user_id");
    $row = mysqli_fetch_assoc($result);
    $balance = $row['balance'];

    if($amount <= 0){
        header('Location: dashboard.php?page=withdraw&err=invalid_amount');
        exit;
    }

    if($amount > $balance){
        header('Location: dashboard.php?page=withdraw&err=insufficient');
        exit;
    }

    $new_balance = $balance - $amount;
    
    $ref = 'TXN' . rand(1000,9999);

    mysqli_query($conn, "UPDATE users SET balance = $new_balance WHERE id = $user_id");
    mysqli_query($conn, "INSERT INTO transactions (user_id, txn_ref, description, amount, type) VALUES ($user_id, '$ref', 'Cash Withdrawal', $amount, 'debit')");

    header('Location: dashboard.php?page=withdraw&ok=1&ref='.$ref);
    exit;
}


// transfer money
if($action == 'transfer'){

    $recipient = $_POST['recipient'];
    $amount = $_POST['amount'];

    $result = mysqli_query($conn, "SELECT balance FROM users WHERE id = $user_id");
    $row = mysqli_fetch_assoc($result);
    $balance = $row['balance'];

    if($recipient == ''){
        header('Location: dashboard.php?page=transactions&err=no_recipient');
        exit;
    }

    if($amount <= 0){
        header('Location: dashboard.php?page=transactions&err=invalid_amount');
        exit;
    }

    if($amount > $balance){
        header('Location: dashboard.php?page=transactions&err=insufficient');
        exit;
    }

    $new_balance = $balance - $amount;
    $ref = 'TXN' . rand(1000,9999);

    // check if recipient exists
    $check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$recipient'");
    $rec = mysqli_fetch_assoc($check);
    $recipient_id = $rec['id'];

    mysqli_query($conn, "UPDATE users SET balance = $new_balance WHERE id = $user_id");
    mysqli_query($conn, "INSERT INTO transactions (user_id, txn_ref, description, amount, type) VALUES ($user_id, '$ref', 'Transfer to $recipient', $amount, 'debit')");

    // if recipient found add money to them also
    if($recipient_id){
        $rec_result = mysqli_query($conn, "SELECT balance FROM users WHERE id = $recipient_id");
        $rec_row = mysqli_fetch_assoc($rec_result);
        $rec_balance = $rec_row['balance'];
        $new_rec_balance = $rec_balance + $amount;
        $ref2 = 'TXN' . rand(1000,9999);

        mysqli_query($conn, "UPDATE users SET balance = $new_rec_balance WHERE id = $recipient_id");
        mysqli_query($conn, "INSERT INTO transactions (user_id, txn_ref, description, amount, type) VALUES ($recipient_id, '$ref2', 'Received from $username', $amount, 'credit')");
    }

    header('Location: dashboard.php?page=transactions&ok=1&ref='.$ref.'&to='.$recipient);
    exit;
}


// invest
if($action == 'invest'){

    $plan_name = $_POST['plan_name'];
    $plan_icon = $_POST['plan_icon'];
    $roi_rate = $_POST['plan_roi'];
    $dur_label = $_POST['plan_dur'];
    $min_amt = $_POST['plan_min'];
    $amount = $_POST['amount'];

    $result = mysqli_query($conn, "SELECT balance FROM users WHERE id = $user_id");
    $row = mysqli_fetch_assoc($result);
    $balance = $row['balance'];

    if($amount <= 0){
        header('Location: dashboard.php?page=invest&err=invalid_amount');
        exit;
    }

    if($amount < $min_amt){
        header('Location: dashboard.php?page=invest&err=below_min');
        exit;
    }

    if($amount > $balance){
        header('Location: dashboard.php?page=invest&err=insufficient');
        exit;
    }

    $roi_amount = ($amount * $roi_rate) / 100;
    $maturity_amount = $amount + $roi_amount;
    $new_balance = $balance - $amount;
    $ref = 'INV' . rand(1000,9999);
    $txn_ref = 'TXN' . rand(1000,9999);
    $today = date('Y-m-d');

    mysqli_query($conn, "UPDATE users SET balance = $new_balance WHERE id = $user_id");
    mysqli_query($conn, "INSERT INTO transactions (user_id, txn_ref, description, amount, type) VALUES ($user_id, '$txn_ref', 'Invested in $plan_name', $amount, 'debit')");
    mysqli_query($conn, "INSERT INTO investments (id, user_id, plan_name, plan_icon, capital, roi_rate, roi_amount, maturity_amount, duration_label, start_date, status) VALUES ('$ref', $user_id, '$plan_name', '$plan_icon', $amount, $roi_rate, $roi_amount, $maturity_amount, '$dur_label', '$today', 'active')");

    header('Location: dashboard.php?page=invest&ok=1&ref='.$ref);
    exit;
}


// claim money after investment matures
if($action == 'claim_maturity'){

    $inv_id = $_POST['investment_id'];

    $result = mysqli_query($conn, "SELECT * FROM investments WHERE id = '$inv_id' AND user_id = $user_id AND status = 'active'");
    $inv = mysqli_fetch_assoc($result);

    if(!$inv){
        header('Location: dashboard.php?page=invest&err=invalid_amount');
        exit;
    }

    $maturity_amount = $inv['maturity_amount'];
    $plan_name = $inv['plan_name'];

    $result2 = mysqli_query($conn, "SELECT balance FROM users WHERE id = $user_id");
    $row2 = mysqli_fetch_assoc($result2);
    $balance = $row2['balance'];

    $new_balance = $balance + $maturity_amount;
    $ref = 'TXN' . rand(1000,9999);

    mysqli_query($conn, "UPDATE users SET balance = $new_balance WHERE id = $user_id");
    mysqli_query($conn, "INSERT INTO transactions (user_id, txn_ref, description, amount, type) VALUES ($user_id, '$ref', 'Maturity: $plan_name', $maturity_amount, 'credit')");
    mysqli_query($conn, "UPDATE investments SET status = 'matured' WHERE id = '$inv_id'");

    header('Location: dashboard.php?page=invest&ok=1&matured=1&ref='.$ref);
    exit;
}


header('Location: dashboard.php');
exit;
?>