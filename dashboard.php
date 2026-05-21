<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require 'db.php';

$user_id = $_SESSION['user_id'];
$page = $_GET['page'] ?? 'dashboard';

$stmt = $conn->prepare("SELECT full_name, balance, card_number, username FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($full_name, $balance, $card_number, $username);
$stmt->fetch();
$stmt->close();

$txns = [];
$result = $conn->query("SELECT txn_ref, description, amount, type, created_at FROM transactions WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 50");
while ($row = $result->fetch_assoc()) {
    $txns[] = $row;
}

// ── Fetch active investments for this user ──────────────────────────────────
$investments = [];
$inv_result = $conn->query("SELECT id, plan_name, plan_icon, capital, roi_amount, maturity_amount, roi_rate, duration_label, start_date, status FROM investments WHERE user_id = $user_id ORDER BY start_date DESC");
while ($row = $inv_result->fetch_assoc()) {
    $investments[] = $row;
}

$alert = '';
$atype = '';

if (isset($_GET['ok'])) {
    $ref = $_GET['ref'];
    if ($page === 'withdraw') {
        $alert = "Withdrawal successful! Reference: #$ref";
        $atype = 'success';
    } elseif ($page === 'transactions') {
        $to = $_GET['to'];
        $alert = "Transfer to $to successful! Reference: #$ref";
        $atype = 'success';
    } elseif ($page === 'invest') {
        $alert = "Investment placed successfully! Reference: #$ref";
        $atype = 'success';
    } elseif ($page === 'invest' && isset($_GET['matured'])) {
        $alert = "Maturity amount credited to your account! Reference: #$ref";
        $atype = 'success';
    }
}

if (isset($_GET['err'])) {
    if ($_GET['err'] == 'insufficient')       $alert = 'Insufficient funds.';
    elseif ($_GET['err'] == 'invalid_amount') $alert = 'Please enter a valid amount.';
    elseif ($_GET['err'] == 'below_min')      $alert = 'Amount is below the minimum required for this plan.';
    elseif ($_GET['err'] == 'no_recipient')   $alert = 'Please enter a recipient name.';
    else                                       $alert = 'An error occurred.';
    $atype = 'error';
}

$masked_card = '****' . substr($card_number, 4);

// ── Investment plan definitions (keep in sync with action.php) ──────────────
$plans = [
    ['id'=>'fd',  'name'=>'Fixed Deposit',     'icon'=>'🏦', 'min'=>10000,  'roi'=>7.5,  'duration'=>12, 'dur_label'=>'12 Months', 'risk'=>'Low',    'risk_color'=>'#22c55e', 'tag'=>'Most Popular', 'tag_color'=>'#3b82f6'],
    ['id'=>'mf',  'name'=>'Mutual Fund',        'icon'=>'📈', 'min'=>5000,   'roi'=>14.2, 'duration'=>24, 'dur_label'=>'24 Months', 'risk'=>'Medium', 'risk_color'=>'#f59e0b', 'tag'=>'High Returns', 'tag_color'=>'#8b5cf6'],
    ['id'=>'gb',  'name'=>'Gold Bond',          'icon'=>'🥇', 'min'=>25000,  'roi'=>9.8,  'duration'=>18, 'dur_label'=>'18 Months', 'risk'=>'Low',    'risk_color'=>'#22c55e', 'tag'=>'Stable',       'tag_color'=>'#f59e0b'],
    ['id'=>'eq',  'name'=>'Equity Fund',        'icon'=>'🚀', 'min'=>15000,  'roi'=>22.0, 'duration'=>36, 'dur_label'=>'36 Months', 'risk'=>'High',   'risk_color'=>'#ef4444', 'tag'=>'Max Growth',   'tag_color'=>'#ef4444'],
    ['id'=>'rd',  'name'=>'Recurring Deposit',  'icon'=>'🔄', 'min'=>1000,   'roi'=>6.5,  'duration'=>6,  'dur_label'=>'6 Months',  'risk'=>'Low',    'risk_color'=>'#22c55e', 'tag'=>'Flexible',     'tag_color'=>'#06b6d4'],
    ['id'=>'ref', 'name'=>'Real Estate Fund',   'icon'=>'🏢', 'min'=>100000, 'roi'=>17.5, 'duration'=>48, 'dur_label'=>'48 Months', 'risk'=>'Medium', 'risk_color'=>'#f59e0b', 'tag'=>'Premium',      'tag_color'=>'#ec4899'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>IsraelStateBank Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #0d1117;
            --surface: #161b22;
            --border: #30363d;
            --blue: #58a6ff;
            --green: #238636;
            --red: #f85149;
            --muted: #8b949e;
            --white: #e6edf3;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--white);
            min-height: 100vh;
            display: flex;
        }

        .sidebar {
            width: 240px;
            background: var(--surface);
            border-right: 1px solid var(--border);
            padding: 28px 16px;
            display: flex;
            flex-direction: column;
        }

        .sidebar-logo {
            font-size: 20px;
            font-weight: 700;
            color: var(--blue);
            text-align: center;
            margin-bottom: 36px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 14px;
            border-radius: 8px;
            font-size: 14px;
            color: var(--muted);
            text-decoration: none;
            margin-bottom: 4px;
        }

        .nav-item:hover { background: #21262d; color: var(--white); }
        .nav-item.active { background: rgba(88,166,255,0.12); color: var(--blue); }
        .nav-item.logout { color: var(--red); margin-top: auto; }

        .sidebar-footer {
            margin-top: auto;
            border-top: 1px solid var(--border);
            padding-top: 16px;
        }

        .avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1d4ed8, #7c3aed);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
        }

        .user-chip {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            margin-bottom: 8px;
        }

        .content { flex: 1; padding: 36px 40px; }

        h1 { font-size: 22px; font-weight: 700; margin-bottom: 24px; }
        h2 { font-size: 16px; font-weight: 600; margin-bottom: 16px; }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
        }

        .card-label {
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .amount-lg { font-size: 30px; font-weight: 700; }

        .grid3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .visa-card {
            background: linear-gradient(135deg, #1e3a8a, #2563eb, #7c3aed);
            border-radius: 18px;
            padding: 28px;
            color: #fff;
            margin-bottom: 20px;
            max-width: 420px;
        }

        .visa-card-top {
            display: flex;
            justify-content: space-between;
            margin-bottom: 32px;
        }

        .visa-card-num {
            font-family: monospace;
            font-size: 19px;
            letter-spacing: 3px;
            margin-bottom: 24px;
        }

        .visa-card-bottom { display: flex; gap: 40px; font-size: 12px; }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 22px;
        }

        .alert-success { background: rgba(35,134,54,0.15); color: #3fb950; border: 1px solid rgba(35,134,54,0.3); }
        .alert-error { background: rgba(248,81,73,0.12); color: var(--red); border: 1px solid rgba(248,81,73,0.25); }

        .form-group { margin-bottom: 16px; }

        .form-label {
            display: block;
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 6px;
        }

        .form-input {
            width: 100%;
            padding: 11px 14px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--white);
            font-size: 14px;
            font-family: 'Inter', sans-serif;
        }

        .form-input:focus { outline: none; border-color: var(--blue); }

        .btn {
            padding: 11px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-green  { background: var(--green); color: #fff; }
        .btn-red    { background: rgba(248,81,73,0.15); color: var(--red); border: 1px solid rgba(248,81,73,0.3); }
        .btn-blue   { background: rgba(88,166,255,0.15); color: var(--blue); border: 1px solid rgba(88,166,255,0.3); }
        .btn-purple { background: #6366f1; color: #fff; width: 100%; margin-top: 4px; }

        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: left;
            padding: 10px 14px;
            font-size: 12px;
            color: var(--muted);
            border-bottom: 1px solid var(--border);
        }
        td {
            padding: 13px 14px;
            border-bottom: 1px solid rgba(48,54,61,0.5);
            font-size: 14px;
        }

        .badge {
            padding: 3px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            background: rgba(35,134,54,0.2);
            color: #3fb950;
        }

        .badge-active {
            background: rgba(88,166,255,0.15);
            color: var(--blue);
        }

        .pos  { color: #3fb950; font-weight: 600; }
        .neg  { color: var(--red); font-weight: 600; }
        .muted { color: var(--muted); font-size: 13px; }

        /* ── Investment-specific styles ─────────────────────────── */
        .inv-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .inv-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 18px 20px;
            cursor: pointer;
            transition: border-color .2s, box-shadow .2s;
            position: relative;
        }

        .inv-card:hover { border-color: #58a6ff55; }
        .inv-card.selected { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.2); }

        .inv-tag {
            position: absolute;
            top: 14px; right: 14px;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 20px;
            text-transform: uppercase;
        }

        .inv-card-top {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 14px;
        }

        .inv-icon   { font-size: 28px; margin-top: 2px; }
        .inv-name   { font-weight: 700; font-size: 15px; margin-bottom: 3px; }
        .inv-desc   { font-size: 12px; color: var(--muted); line-height: 1.4; }

        .inv-stats {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 8px;
            border-top: 1px solid var(--border);
            padding-top: 12px;
        }

        .inv-stat-label { font-size: 10px; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 2px; }
        .inv-stat-val   { font-size: 13px; font-weight: 600; }

        .inv-form-box {
            background: var(--surface);
            border: 1px solid #6366f1;
            border-radius: 12px;
            margin-bottom: 28px;
            overflow: hidden;
            max-width: 560px;
        }

        .inv-form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 20px;
            background: #161827;
            border-bottom: 1px solid var(--border);
        }

        .inv-form-body { padding: 20px; }

        .inv-preview {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 14px;
            font-size: 13px;
        }

        .inv-preview-row {
            display: flex;
            justify-content: space-between;
            color: #cbd5e1;
            margin-bottom: 6px;
        }

        .inv-preview-row:last-child {
            margin-bottom: 0;
            padding-top: 8px;
            border-top: 1px solid var(--border);
            font-weight: 700;
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">🏦 IsraelStateBank</div>

    <a href="dashboard.php?page=dashboard"     class="nav-item <?= $page=='dashboard'    ? 'active':'' ?>">🏠 Dashboard</a>
    <a href="dashboard.php?page=transactions"  class="nav-item <?= $page=='transactions' ? 'active':'' ?>">💸 Transfer</a>
    <a href="dashboard.php?page=withdraw"      class="nav-item <?= $page=='withdraw'     ? 'active':'' ?>">🏧 Withdraw</a>
    <a href="dashboard.php?page=invest"        class="nav-item <?= $page=='invest'       ? 'active':'' ?>">💹 Invest</a>
    <a href="dashboard.php?page=history"       class="nav-item <?= $page=='history'      ? 'active':'' ?>">📋 History</a>
    <a href="dashboard.php?page=accounts"      class="nav-item <?= $page=='accounts'     ? 'active':'' ?>">💳 My Card</a>

    <div class="sidebar-footer">
        <div class="user-chip">
            <div class="avatar"><?= strtoupper(substr($full_name, 0, 1)) ?></div>
            <div>
                <strong><?= $full_name ?></strong><br>
                <small style="color:var(--muted);">@<?= $username ?></small>
            </div>
        </div>
        <a href="logout.php" class="nav-item logout">🚪 Logout</a>
    </div>
</aside>

<main class="content">

    <?php if ($alert != '') { ?>
        <div class="alert alert-<?= $atype ?>"><?= htmlspecialchars($alert) ?></div>
    <?php } ?>

    <?php /* ══════════════════ DASHBOARD ══════════════════ */ ?>
    <?php if ($page == 'dashboard') { ?>

        <h1>Welcome back, <?= explode(' ', $full_name)[0] ?> 👋</h1>

        <div class="grid3">
            <div class="card">
                <div class="card-label">Total Balance</div>
                <div class="amount-lg">Rs. <?= number_format($balance, 2) ?></div>
                <div class="muted" style="margin-top:6px;">Primary Savings Account</div>
            </div>
            <div class="card">
                <div class="card-label">Total Transactions</div>
                <div class="amount-lg"><?= count($txns) ?></div>
                <div class="muted" style="margin-top:6px;">All time records</div>
            </div>
            <div class="card">
                <div class="card-label">Account Status</div>
                <div style="margin-top:8px;"><span class="badge">✔ Active</span></div>
                <div class="muted" style="margin-top:6px;">Verified member</div>
            </div>
        </div>

        <div class="visa-card">
            <div class="visa-card-top">
                <span>PLATINUM DEBIT</span>
                <span>IsraelStateBank</span>
            </div>
            <div class="visa-card-num"><?= $masked_card ?></div>
            <div class="visa-card-bottom">
                <div>CARD HOLDER<br><?= strtoupper($full_name) ?></div>
                <div>EXPIRES<br>12/29</div>
            </div>
        </div>

        <div class="card">
            <h2>Recent Activity</h2>
            <?php if (count($txns) == 0) { ?>
                <p class="muted">No transactions yet.</p>
            <?php } else { ?>
                <table>
                    <thead>
                        <tr><th>REF</th><th>DESCRIPTION</th><th>DATE</th><th>STATUS</th><th>AMOUNT</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($txns, 0, 5) as $t) { ?>
                        <tr>
                            <td class="muted">#<?= $t['txn_ref'] ?></td>
                            <td><?= htmlspecialchars($t['description']) ?></td>
                            <td class="muted"><?= date('Y-m-d', strtotime($t['created_at'])) ?></td>
                            <td><span class="badge">Completed</span></td>
                            <td class="<?= $t['type']=='credit' ? 'pos':'neg' ?>">
                                <?= $t['type']=='credit' ? '+':'-' ?>Rs. <?= number_format($t['amount'], 2) ?>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>
        </div>

    <?php /* ══════════════════ TRANSFER ══════════════════ */ ?>
    <?php } elseif ($page == 'transactions') { ?>

        <h1>💸 Fund Transfer</h1>

        <div class="card" style="max-width:520px;">
            <h2>Send Money</h2>
            <p class="muted" style="margin-bottom:20px;">Available balance: Rs. <?= number_format($balance, 2) ?></p>
            <form method="POST" action="action.php">
                <input type="hidden" name="action" value="transfer">
                <div class="form-group">
                    <label class="form-label">Recipient Name</label>
                    <input class="form-input" type="text" name="recipient" placeholder="e.g. Sita Thapa" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Amount (Rs.)</label>
                    <input class="form-input" type="number" name="amount" placeholder="e.g. 5000" min="1" required>
                </div>
                <button type="submit" class="btn btn-green">Send Money</button>
            </form>
        </div>

    <?php /* ══════════════════ WITHDRAW ══════════════════ */ ?>
    <?php } elseif ($page == 'withdraw') { ?>

        <h1>🏧 Withdraw Cash</h1>

        <div class="card" style="max-width:460px;">
            <h2>Withdrawal Request</h2>
            <p class="muted" style="margin-bottom:20px;">Available balance: Rs. <?= number_format($balance, 2) ?></p>
            <form method="POST" action="action.php">
                <input type="hidden" name="action" value="withdraw">
                <div class="form-group">
                    <label class="form-label">Amount (Rs.)</label>
                    <input class="form-input" type="number" name="amount" placeholder="e.g. 10000" min="1" required>
                </div>
                <button type="submit" class="btn btn-red">Withdraw</button>
            </form>
        </div>

    <?php /* ══════════════════ INVEST ══════════════════ */ ?>
    <?php } elseif ($page == 'invest') { ?>

        <h1>💹 Investment Plans</h1>
        <p class="muted" style="margin-bottom:24px;">
            Capital is deducted from the total balance and the ROI gained is added Back to total balance.
        </p>

        <!-- Plan cards (clicking selects the plan via JS) -->
        <div class="inv-grid">
            <?php foreach ($plans as $p) { ?>
            <div class="inv-card" id="plan-<?= $p['id'] ?>" onclick="selectPlan('<?= $p['id'] ?>')">
                <span class="inv-tag" style="background:<?= $p['tag_color'] ?>22; color:<?= $p['tag_color'] ?>;">
                    <?= $p['tag'] ?>
                </span>
                <div class="inv-card-top">
                    <span class="inv-icon"><?= $p['icon'] ?></span>
                    <div>
                        <div class="inv-name"><?= $p['name'] ?></div>
                        <div class="inv-desc">
                            <?php
                            $descs = [
                                'fd'  => 'Safe & guaranteed returns with fixed interest rate.',
                                'mf'  => 'Diversified portfolio managed by expert fund managers.',
                                'gb'  => 'Invest in sovereign gold bonds backed by the government.',
                                'eq'  => 'High-growth equity investments for long-term wealth.',
                                'rd'  => 'Monthly savings plan with compound interest.',
                                'ref' => 'Invest in curated real estate projects.',
                            ];
                            echo $descs[$p['id']];
                            ?>
                        </div>
                    </div>
                </div>
                <div class="inv-stats">
                    <div><span class="inv-stat-label">ROI</span><span class="inv-stat-val" style="color:#22c55e;"><?= $p['roi'] ?>%</span></div>
                    <div><span class="inv-stat-label">Duration</span><span class="inv-stat-val"><?= $p['dur_label'] ?></span></div>
                    <div><span class="inv-stat-label">Min Amount</span><span class="inv-stat-val">Rs. <?= number_format($p['min']) ?></span></div>
                    <div><span class="inv-stat-label">Risk</span><span class="inv-stat-val" style="color:<?= $p['risk_color'] ?>;"><?= $p['risk'] ?></span></div>
                </div>
            </div>
            <?php } ?>
        </div>

        <!-- Investment form (shown after selecting a plan) -->
        <div class="inv-form-box" id="inv-form-box" style="display:none;">
            <div class="inv-form-header">
                <span id="inv-form-title" style="font-weight:700; font-size:15px;"></span>
                <button onclick="closeForm()" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:16px;">✕</button>
            </div>
            <div class="inv-form-body">
                <p class="muted" style="margin-bottom:14px;">
                    Available Balance: <strong style="color:#a5f3fc;">Rs. <?= number_format($balance, 2) ?></strong>
                </p>
                <form method="POST" action="action.php">
                    <input type="hidden" name="action" value="invest">
                    <input type="hidden" name="plan_id" id="input-plan-id">
                    <input type="hidden" name="plan_name" id="input-plan-name">
                    <input type="hidden" name="plan_icon" id="input-plan-icon">
                    <input type="hidden" name="plan_roi" id="input-plan-roi">
                    <input type="hidden" name="plan_dur" id="input-plan-dur">
                    <input type="hidden" name="plan_min" id="input-plan-min">

                    <div class="form-group">
                        <label class="form-label">Investment Amount (Rs.)</label>
                        <input class="form-input" type="number" name="amount" id="inv-amount-input"
                               placeholder="Enter amount" min="1" oninput="updatePreview()" required>
                    </div>

                    <!-- Live ROI preview -->
                    <div class="inv-preview" id="inv-preview" style="display:none;">
                        <div class="inv-preview-row"><span>Capital</span><span id="prev-capital"></span></div>
                        <div class="inv-preview-row"><span id="prev-roi-label"></span><span id="prev-roi" style="color:#22c55e;"></span></div>
                        <div class="inv-preview-row"><span>Maturity Amount</span><span id="prev-maturity" style="color:#a5f3fc;"></span></div>
                    </div>

                    <button type="submit" class="btn btn-purple">Confirm Investment</button>
                </form>
            </div>
        </div>

        <!-- Active investments table -->
        <?php if (count($investments) > 0) { ?>
        <div class="card" style="margin-top:10px;">
            <h2>Your Active Investments</h2>
            <table>
                <thead>
                    <tr>
                        <th>REF</th><th>PLAN</th><th>CAPITAL</th><th>ROI RATE</th>
                        <th>MATURITY AMT</th><th>DURATION</th><th>START DATE</th><th>STATUS</th><th>ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($investments as $inv) { ?>
                    <tr>
                        <td class="muted"><?= htmlspecialchars($inv['id']) ?></td>
                        <td><?= $inv['plan_icon'] ?> <?= htmlspecialchars($inv['plan_name']) ?></td>
                        <td class="neg">-Rs. <?= number_format($inv['capital'], 2) ?></td>
                        <td class="pos">+<?= $inv['roi_rate'] ?>%</td>
                        <td style="color:#a5f3fc;">Rs. <?= number_format($inv['maturity_amount'], 2) ?></td>
                        <td class="muted"><?= htmlspecialchars($inv['duration_label']) ?></td>
                        <td class="muted"><?= $inv['start_date'] ?></td>
                        <td><span class="badge badge-active">Active</span></td>
                        <td>
                            <form method="POST" action="action.php" style="display:inline;">
                                <input type="hidden" name="action" value="claim_maturity">
                                <input type="hidden" name="investment_id" value="<?= $inv['id'] ?>">
                                <button type="submit" class="btn btn-blue" style="padding:6px 14px; font-size:12px;"
                                        onclick="return confirm('Claim maturity of Rs. <?= number_format($inv['maturity_amount'],2) ?>?')">
                                    Claim Maturity
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } ?>

    <?php /* ══════════════════ HISTORY ══════════════════ */ ?>
    <?php } elseif ($page == 'history') { ?>

        <h1>📋 Transaction History</h1>

        <div class="card">
            <?php if (count($txns) == 0) { ?>
                <p class="muted">No transactions yet.</p>
            <?php } else { ?>
                <table>
                    <thead>
                        <tr><th>REF ID</th><th>DESCRIPTION</th><th>DATE & TIME</th><th>STATUS</th><th>AMOUNT</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($txns as $t) { ?>
                        <tr>
                            <td class="muted">#<?= $t['txn_ref'] ?></td>
                            <td><?= htmlspecialchars($t['description']) ?></td>
                            <td class="muted"><?= $t['created_at'] ?></td>
                            <td><span class="badge">Completed</span></td>
                            <td class="<?= $t['type']=='credit' ? 'pos':'neg' ?>">
                                <?= $t['type']=='credit' ? '+':'-' ?>Rs. <?= number_format($t['amount'], 2) ?>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>
        </div>

    <?php /* ══════════════════ MY CARD ══════════════════ */ ?>
    <?php } elseif ($page == 'accounts') { ?>

        <h1>💳 My Card & Account</h1>

        <div class="visa-card">
            <div class="visa-card-top">
                <span>PLATINUM DEBIT</span>
                <span>IsraelStateBank</span>
            </div>
            <div class="visa-card-num"><?= $masked_card ?></div>
            <div class="visa-card-bottom">
                <div>CARD HOLDER<br><?= strtoupper($full_name) ?></div>
                <div>EXPIRES<br>12/29</div>
            </div>
        </div>

        <div class="card" style="max-width:420px;">
            <div class="card-label">Account Details</div>
            <table style="margin-top:12px;">
                <tr>
                    <td class="muted" style="border:none;width:140px;padding:8px 0;">Full Name</td>
                    <td style="border:none;"><?= htmlspecialchars($full_name) ?></td>
                </tr>
                <tr>
                    <td class="muted" style="border:none;padding:8px 0;">Username</td>
                    <td style="border:none;">@<?= htmlspecialchars($username) ?></td>
                </tr>
                <tr>
                    <td class="muted" style="border:none;padding:8px 0;">Balance</td>
                    <td style="border:none;color:#3fb950;">Rs. <?= number_format($balance, 2) ?></td>
                </tr>
                <tr>
                    <td class="muted" style="border:none;padding:8px 0;">Card Number</td>
                    <td style="border:none;font-family:monospace;"><?= $masked_card ?></td>
                </tr>
            </table>
        </div>

    <?php } ?>

</main>

<!-- ── JS: plan selection & live ROI preview ──────────────────────────────── -->
<script>
const plans = <?= json_encode(array_combine(array_column($plans,'id'), $plans)) ?>;

let activePlanId = null;

function selectPlan(id) {
    // Deselect previous
    if (activePlanId) document.getElementById('plan-' + activePlanId)?.classList.remove('selected');

    // If clicking the same card, toggle off
    if (activePlanId === id) {
        activePlanId = null;
        document.getElementById('inv-form-box').style.display = 'none';
        return;
    }

    activePlanId = id;
    const p = plans[id];

    document.getElementById('plan-' + id).classList.add('selected');

    // Populate hidden fields
    document.getElementById('input-plan-id').value   = p.id;
    document.getElementById('input-plan-name').value = p.name;
    document.getElementById('input-plan-icon').value = p.icon;
    document.getElementById('input-plan-roi').value  = p.roi;
    document.getElementById('input-plan-dur').value  = p.dur_label;
    document.getElementById('input-plan-min').value  = p.min;

    // Update form header
    document.getElementById('inv-form-title').textContent = p.icon + '  Invest in ' + p.name;

    // Update placeholder
    document.getElementById('inv-amount-input').placeholder = 'Min: Rs. ' + p.min.toLocaleString('en-IN');
    document.getElementById('inv-amount-input').value = '';
    document.getElementById('inv-preview').style.display = 'none';

    document.getElementById('inv-form-box').style.display = 'block';
    document.getElementById('inv-form-box').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function closeForm() {
    if (activePlanId) document.getElementById('plan-' + activePlanId)?.classList.remove('selected');
    activePlanId = null;
    document.getElementById('inv-form-box').style.display = 'none';
}

function updatePreview() {
    if (!activePlanId) return;
    const p = plans[activePlanId];
    const amt = parseFloat(document.getElementById('inv-amount-input').value);
    const preview = document.getElementById('inv-preview');

    if (!amt || amt <= 0) { preview.style.display = 'none'; return; }

    const roi      = parseFloat(((amt * p.roi) / 100).toFixed(2));
    const maturity = amt + roi;

    document.getElementById('prev-capital').textContent    = 'Rs. ' + amt.toLocaleString('en-IN', {minimumFractionDigits:2});
    document.getElementById('prev-roi-label').textContent  = 'Expected ROI (' + p.roi + '% / ' + p.dur_label + ')';
    document.getElementById('prev-roi').textContent        = '+ Rs. ' + roi.toLocaleString('en-IN', {minimumFractionDigits:2});
    document.getElementById('prev-maturity').textContent   = 'Rs. ' + maturity.toLocaleString('en-IN', {minimumFractionDigits:2});

    preview.style.display = 'block';
}
</script>

</body>
</html>