<?php
// wallet.php
// include this in dashboard.php like other pages

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// add new card
if(isset($_POST['add_card'])){
    $card_name   = $_POST['card_name'];
    $card_number = $_POST['card_number'];
    $card_holder = $_POST['card_holder'];
    $balance     = $_POST['balance'];
    $card_color  = $_POST['card_color'];

    mysqli_query($conn, "INSERT INTO cards (user_id, card_name, card_number, card_holder, balance, card_color) VALUES ('$user_id', '$card_name', '$card_number', '$card_holder', '$balance', '$card_color')");

    header('Location: dashboard.php?page=wallet&ok=1');
    exit;
}

// delete card
if(isset($_GET['delete_card'])){
    $card_id = $_GET['delete_card'];
    mysqli_query($conn, "DELETE FROM cards WHERE id = $card_id AND user_id = $user_id");
    header('Location: dashboard.php?page=wallet');
    exit;
}

// get all cards
$cards_result = mysqli_query($conn, "SELECT * FROM cards WHERE user_id = $user_id ORDER BY created_at DESC");
$cards = array();
while($c = mysqli_fetch_assoc($cards_result)){
    $cards[] = $c;
}

// total balance of all cards
$total = 0;
foreach($cards as $c){
    $total += $c['balance'];
}

// card colors
$colors = array(
    'blue'   => 'linear-gradient(135deg, #1e3a8a, #2563eb)',
    'purple' => 'linear-gradient(135deg, #4c1d95, #7c3aed)',
    'green'  => 'linear-gradient(135deg, #064e3b, #059669)',
    'red'    => 'linear-gradient(135deg, #7f1d1d, #dc2626)',
    'dark'   => 'linear-gradient(135deg, #111827, #374151)',
);
?>

<h1>💳 My Wallet</h1>

<?php if(isset($_GET['ok'])){ ?>
<div class="alert alert-success" style="margin-bottom:20px;">Card added successfully!</div>
<?php } ?>

<!-- Total Balance -->
<div class="card" style="max-width:300px; margin-bottom:24px;">
    <div class="card-label">Total Wallet Balance</div>
    <div style="font-size:28px; font-weight:700; color:#3fb950; margin-top:6px;">Rs. <?= number_format($total, 2) ?></div>
    <div class="muted" style="font-size:12px; margin-top:4px;">Across <?= count($cards) ?> card(s)</div>
</div>

<!-- Cards Grid -->
<?php if(count($cards) == 0){ ?>
<p class="muted" style="margin-bottom:24px;">You have no cards yet. Add one below!</p>
<?php } else { ?>
<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px,1fr)); gap:16px; margin-bottom:32px;">
    <?php foreach($cards as $c){ 
        $bg = isset($colors[$c['card_color']]) ? $colors[$c['card_color']] : $colors['blue'];
        $masked = '•••• •••• •••• ' . substr(str_replace(' ','',$c['card_number']), -4);
    ?>
    <div style="background:<?= $bg ?>; border-radius:16px; padding:24px; color:#fff; position:relative;">
        <!-- card name -->
        <div style="font-size:13px; opacity:0.8; margin-bottom:20px;"><?= htmlspecialchars($c['card_name']) ?></div>

        <!-- card number -->
        <div style="font-family:monospace; font-size:17px; letter-spacing:2px; margin-bottom:20px;"><?= $masked ?></div>

        <!-- card bottom -->
        <div style="display:flex; justify-content:space-between; align-items:flex-end;">
            <div>
                <div style="font-size:10px; opacity:0.7;">CARD HOLDER</div>
                <div style="font-size:14px; font-weight:600;"><?= strtoupper(htmlspecialchars($c['card_holder'])) ?></div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:10px; opacity:0.7;">BALANCE</div>
                <div style="font-size:16px; font-weight:700;">Rs. <?= number_format($c['balance'], 2) ?></div>
            </div>
        </div>

        <!-- delete button -->
        <a href="dashboard.php?page=wallet&delete_card=<?= $c['id'] ?>"
           onclick="return confirm('Delete this card?')"
           style="position:absolute; top:12px; right:12px; background:rgba(0,0,0,0.3); color:#fff; border-radius:50%; width:24px; height:24px; display:flex; align-items:center; justify-content:center; font-size:12px; text-decoration:none;">✕</a>
    </div>
    <?php } ?>
</div>
<?php } ?>

<!-- Add New Card Form -->
<div class="card" style="max-width:480px;">
    <h2 style="margin-bottom:16px;">➕ Add New Card</h2>
    <form method="POST" action="dashboard.php?page=wallet">

        <label class="form-label">Card Name (e.g. Nabil Bank, eSewa)</label>
        <input type="text" name="card_name" class="form-input" placeholder="e.g. Nabil Bank" required>

        <label class="form-label">Card Number</label>
        <input type="text" name="card_number" class="form-input" placeholder="e.g. 1234 5678 9012 3456" required>

        <label class="form-label">Card Holder Name</label>
        <input type="text" name="card_holder" class="form-input" placeholder="e.g. Ram Bahadur" required>

        <label class="form-label">Balance (Rs.)</label>
        <input type="number" name="balance" class="form-input" placeholder="e.g. 5000" min="0" required>

        <label class="form-label">Card Color</label>
        <select name="card_color" class="form-input">
            <option value="blue">🔵 Blue</option>
            <option value="purple">🟣 Purple</option>
            <option value="green">🟢 Green</option>
            <option value="red">🔴 Red</option>
            <option value="dark">⚫ Dark</option>
        </select>

        <button type="submit" name="add_card" class="btn btn-green" style="margin-top:12px; width:100%;">Add Card</button>
    </form>
</div>
