<!-- 
    Replace your existing transfer section in dashboard.php with this
    The card selector shows all user's cards
-->

<?php
// get user cards for dropdown
$cards_result = mysqli_query($conn, "SELECT * FROM cards WHERE user_id = $user_id");
$cards = array();
while($c = mysqli_fetch_assoc($cards_result)){
    $cards[] = $c;
}
?>

<h1>💸 Fund Transfer</h1>

<div class="card" style="max-width:520px;">
    <h2>Send Money</h2>
    <p class="muted" style="margin-bottom:20px;">Main account balance: Rs. <?= number_format($balance, 2) ?></p>

    <form method="POST" action="action.php">
        <input type="hidden" name="action" value="transfer">

        <!-- card selector -->
        <label class="form-label">Pay From</label>
        <?php if(count($cards) == 0){ ?>
            <div style="background:#21262d; border:1px solid var(--border); border-radius:8px; padding:12px; margin-bottom:16px;">
                <span class="muted">🏦 Main Account — Rs. <?= number_format($balance, 2) ?></span>
                <input type="hidden" name="card_id" value="main">
            </div>
        <?php } else { ?>
            <select name="card_id" class="form-input" onchange="updateCardBalance(this)">
                <option value="main">🏦 Main Account — Rs. <?= number_format($balance, 2) ?></option>
                <?php foreach($cards as $c){ ?>
                <option value="<?= $c['id'] ?>" data-balance="<?= $c['balance'] ?>">
                    💳 <?= htmlspecialchars($c['card_name']) ?> — Rs. <?= number_format($c['balance'], 2) ?>
                </option>
                <?php } ?>
            </select>
        <?php } ?>

        <!-- selected card balance preview -->
        <div id="selected-balance" style="background:#21262d; border-radius:8px; padding:10px 14px; margin-bottom:16px; font-size:13px; color:var(--muted);">
            Selected balance: <strong style="color:#3fb950;">Rs. <?= number_format($balance, 2) ?></strong>
        </div>

        <label class="form-label">Recipient Username</label>
        <input type="text" name="recipient" class="form-input" placeholder="e.g. sita123" required>

        <label class="form-label">Amount (Rs.)</label>
        <input type="number" name="amount" class="form-input" placeholder="e.g. 500" min="1" required>

        <button type="submit" class="btn btn-green">Send Money</button>
    </form>
</div>

<script>
var mainBalance = <?= $balance ?>;
var cardBalances = {
    <?php foreach($cards as $c){ ?>
    '<?= $c['id'] ?>': <?= $c['balance'] ?>,
    <?php } ?>
};

function updateCardBalance(select){
    var val = select.value;
    var bal = val == 'main' ? mainBalance : (cardBalances[val] || 0);
    document.getElementById('selected-balance').innerHTML =
        'Selected balance: <strong style="color:#3fb950;">Rs. ' + bal.toLocaleString('en-IN', {minimumFractionDigits:2}) + '</strong>';
}
</script>
