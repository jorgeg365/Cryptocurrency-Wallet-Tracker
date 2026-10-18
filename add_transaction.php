
<?php
session_start();

// ======================================
// REQUIRE USER LOGIN
// ======================================

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . "/includes/db.php";

$user_id = (int) $_SESSION["user_id"];
$error = "";

// Generate security token
if (empty($_SESSION["transaction_token"])) {
    $_SESSION["transaction_token"] = bin2hex(random_bytes(32));
}


// ======================================
// RETRIEVE USER'S WALLETS
// ======================================

$stmt = $conn->prepare("
    SELECT wallet_id, wallet_name
    FROM wallets
    WHERE user_id = ?
    ORDER BY wallet_name
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$wallets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


// ======================================
// RETRIEVE CRYPTOCURRENCIES
// ======================================

$cryptos = $conn->query("
    SELECT crypto_id, crypto_name, symbol
    FROM cryptocurrencies
    ORDER BY crypto_name
")->fetch_all(MYSQLI_ASSOC);


// ======================================
// PROCESS TRANSACTION FORM
// ======================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $wallet_id = filter_var(
        $_POST["wallet_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    $crypto_id = filter_var(
        $_POST["crypto_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    $type = $_POST["transaction_type"] ?? "";
    $quantity = trim($_POST["quantity"] ?? "");
    $price = trim($_POST["price_usd"] ?? "");
    $token = $_POST["transaction_token"] ?? "";

    $valid_types = [
        "BUY", "SELL", "DEPOSIT", "WITHDRAWAL"
    ];

    $wallet_ids = array_map(
        "intval",
        array_column($wallets, "wallet_id")
    );

    $crypto_ids = array_map(
        "intval",
        array_column($cryptos, "crypto_id")
    );

    // Validate submitted information
    if (
        !is_string($token) ||
        !hash_equals($_SESSION["transaction_token"], $token)
    ) {
        $error = "Invalid form submission.";

    } elseif (
        !$wallet_id ||
        !in_array($wallet_id, $wallet_ids, true)
    ) {
        $error = "Please select one of your wallets.";

    } elseif (
        !$crypto_id ||
        !in_array($crypto_id, $crypto_ids, true)
    ) {
        $error = "Please select a valid cryptocurrency.";

    } elseif (!in_array($type, $valid_types, true)) {
        $error = "Invalid transaction type.";

    } elseif (
        !preg_match(
            '/^(0|[1-9][0-9]{0,11})(\.[0-9]{1,18})?$/',
            $quantity
        ) ||
        !preg_match('/[1-9]/', $quantity)
    ) {
        $error = "Enter a valid positive quantity (up to 18 decimal places).";

    } elseif (
        ($type === "BUY" || $type === "SELL") &&
        $price === ""
    ) {
        $error = "Price is required for buys and sells.";

    } elseif (
        $price !== "" &&
        (
            !preg_match(
                '/^(0|[1-9][0-9]{0,11})(\.[0-9]{1,8})?$/',
                $price
            ) ||
            !preg_match('/[1-9]/', $price)
        )
    ) {
        $error = "Enter a valid positive price (up to 8 decimal places).";

    } else {

        try {

            // Check available holdings before
            // selling or withdrawing cryptocurrency.
            if (
                $type === "SELL" ||
                $type === "WITHDRAWAL"
            ) {

                $balance_sql = "
                    SELECT
                        CAST(? AS DECIMAL(30,18)) <=
                        COALESCE(SUM(
                            CASE
                                WHEN transaction_type IN
                                    ('BUY', 'DEPOSIT')
                                    THEN quantity
                                WHEN transaction_type IN
                                    ('SELL', 'WITHDRAWAL')
                                    THEN -quantity
                                ELSE 0
                            END
                        ), 0) AS enough_balance
                    FROM wallet_transactions
                    WHERE wallet_id = ?
                      AND crypto_id = ?
                ";

                $balance_stmt = $conn->prepare($balance_sql);

                $balance_stmt->bind_param(
                    "sii",
                    $quantity,
                    $wallet_id,
                    $crypto_id
                );

                $balance_stmt->execute();

                $balance_result =
                    $balance_stmt->get_result()->fetch_assoc();

                $balance_stmt->close();

                if (!$balance_result["enough_balance"]) {
                    $error = "Insufficient cryptocurrency balance.";
                }
            }

            if ($error === "") {

                $price_value = $price === "" ? null : $price;

                $sql = "
                    INSERT INTO wallet_transactions
                    (wallet_id, crypto_id,
                     transaction_type, quantity, price_usd)
                    VALUES (?, ?, ?, ?, ?)
                ";

                $stmt = $conn->prepare($sql);

                $stmt->bind_param(
                    "iisss",
                    $wallet_id,
                    $crypto_id,
                    $type,
                    $quantity,
                    $price_value
                );

                $stmt->execute();
                $stmt->close();

                $_SESSION["transaction_created"] = true;

                // Renew token and redirect
                $_SESSION["transaction_token"] =
                    bin2hex(random_bytes(32));

                header("Location: transactions.php");
                exit;
            }

        } catch (mysqli_sql_exception $e) {

            error_log($e->getMessage());
            $error = "Unable to save transaction.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>CryptoTracker | Add Transaction</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<header class="navbar">
    <div class="logo">CryptoTracker</div>

    <nav>
        <a href="index.php">Home</a>
        <a href="wallets.php">My Wallets</a>
        <a href="transactions.php">Transactions</a>
        <a href="portfolio.php">Portfolio Summary</a>
    </nav>
</header>

<main class="container">

    <section class="form-card">

        <h1>Add Transaction</h1>

        <p>Record your cryptocurrency activity.</p>

        <?php if ($error !== ""): ?>
            <div class="error-message">
                <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
            </div>
        <?php endif; ?>

        <?php if (empty($wallets)): ?>

            <p>
                You need to create a wallet first.
            </p>

            <a href="create_wallet.php">
                Create Wallet
            </a>

        <?php else: ?>

        <form action="add_transaction.php" method="POST">

            <input
                type="hidden"
                name="transaction_token"
                value="<?= htmlspecialchars(
                    $_SESSION["transaction_token"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>"
            >

            <!-- WALLET -->
            <div class="form-group">
                <label for="wallet_id">Choose Wallet</label>

                <select id="wallet_id" name="wallet_id" required>
                    <option value="">Select Wallet</option>

                    <?php foreach ($wallets as $wallet): ?>
                        <option value="<?= (int) $wallet["wallet_id"] ?>">
                            <?= htmlspecialchars(
                                $wallet["wallet_name"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- CRYPTOCURRENCY -->
            <div class="form-group">
                <label for="crypto_id">Cryptocurrency</label>

                <select id="crypto_id" name="crypto_id" required>
                    <option value="">Select Cryptocurrency</option>

                    <?php foreach ($cryptos as $crypto): ?>
                        <option value="<?= (int) $crypto["crypto_id"] ?>">
                            <?= htmlspecialchars(
                                $crypto["crypto_name"] .
                                " (" . $crypto["symbol"] . ")",
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- TRANSACTION TYPE -->
            <div class="form-group">
                <label for="transaction_type">
                    Transaction Type
                </label>

                <select
                    id="transaction_type"
                    name="transaction_type"
                    required
                >
                    <option value="BUY">BUY</option>
                    <option value="SELL">SELL</option>
                    <option value="DEPOSIT">DEPOSIT</option>
                    <option value="WITHDRAWAL">WITHDRAWAL</option>
                </select>
            </div>

            <!-- QUANTITY -->
            <div class="form-group">
                <label for="quantity">Quantity</label>

                <input
                    type="number"
                    id="quantity"
                    name="quantity"
                    min="0"
                    step="any"
                    placeholder="0.05"
                    required
                >
            </div>

            <!-- PRICE -->
            <div class="form-group">
                <label for="price_usd">
                    Price per Coin (USD)
                </label>

                <input
                    type="number"
                    id="price_usd"
                    name="price_usd"
                    min="0"
                    step="any"
                    placeholder="65000.00"
                >

                <small>
                    Required for BUY and SELL.
                    Optional for deposits and withdrawals.
                </small>
            </div>

            <button type="submit" class="form-button">
                Save Transaction
            </button>

        </form>

        <?php endif; ?>

        <p style="margin-top: 20px;">
            <a href="transactions.php">
                Back to Transaction History
            </a>
        </p>

    </section>

</main>

<footer>
    <p>
        CIS 344 Group Project |
        Cryptocurrency Wallet Tracker
    </p>
</footer>

</body>
</html>

<?php $conn->close(); ?>
