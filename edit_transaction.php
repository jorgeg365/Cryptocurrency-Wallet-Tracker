
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
if (empty($_SESSION["edit_transaction_token"])) {
    $_SESSION["edit_transaction_token"] = bin2hex(random_bytes(32));
}

// ======================================
// GET TRANSACTION ID
// ======================================

$id_input = $_SERVER["REQUEST_METHOD"] === "POST"
    ? ($_POST["transaction_id"] ?? null)
    : ($_GET["id"] ?? null);

$transaction_id = filter_var(
    $id_input,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if (!$transaction_id) {
    header("Location: transactions.php");
    exit;
}

// ======================================
// FIND TRANSACTION BELONGING TO USER
// ======================================

$sql = "
    SELECT
        t.transaction_id,
        t.wallet_id,
        t.crypto_id,
        t.transaction_type,
        t.quantity,
        t.price_usd,
        w.wallet_name,
        c.crypto_name,
        c.symbol
    FROM wallet_transactions AS t
    INNER JOIN wallets AS w
        ON t.wallet_id = w.wallet_id
    INNER JOIN cryptocurrencies AS c
        ON t.crypto_id = c.crypto_id
    WHERE t.transaction_id = ?
      AND w.user_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $transaction_id, $user_id);
$stmt->execute();

$transaction = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$transaction) {
    http_response_code(404);
    exit("Transaction not found.");
}

// Remove unnecessary trailing zeros
$quantity = rtrim(
    rtrim($transaction["quantity"], "0"),
    "."
);

$price = $transaction["price_usd"] === null
    ? ""
    : rtrim(rtrim($transaction["price_usd"], "0"), ".");

$type = $transaction["transaction_type"];

// ======================================
// PROCESS EDIT FORM
// ======================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $quantity = trim($_POST["quantity"] ?? "");
    $price = trim($_POST["price_usd"] ?? "");
    $token = $_POST["edit_transaction_token"] ?? "";

    // Validate CSRF token
    if (
        !is_string($token) ||
        !hash_equals(
            $_SESSION["edit_transaction_token"],
            $token
        )
    ) {
        $error = "Invalid form submission.";

    } elseif (
        !preg_match(
            '/^(0|[1-9][0-9]{0,11})(\.[0-9]{1,18})?$/',
            $quantity
        ) ||
        !preg_match('/[1-9]/', $quantity)
    ) {
        $error = "Enter a valid positive quantity.";

    } elseif (
        in_array($type, ["BUY", "SELL"], true) &&
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
        $error = "Enter a valid positive price.";

    } else {

        try {

            // ======================================
            // CHECK RESULTING CRYPTO BALANCE
            // ======================================

            // Calculate holdings as if this
            // transaction had the new quantity.

            $balance_sql = "
                SELECT
                    COALESCE(SUM(
                        CASE
                            WHEN transaction_id = ?
                             AND transaction_type IN
                                ('BUY', 'DEPOSIT')
                                THEN CAST(? AS DECIMAL(30,18))

                            WHEN transaction_id = ?
                             AND transaction_type IN
                                ('SELL', 'WITHDRAWAL')
                                THEN -CAST(? AS DECIMAL(30,18))

                            WHEN transaction_type IN
                                ('BUY', 'DEPOSIT')
                                THEN quantity

                            WHEN transaction_type IN
                                ('SELL', 'WITHDRAWAL')
                                THEN -quantity

                            ELSE 0
                        END
                    ), 0) >= 0 AS valid_balance
                FROM wallet_transactions
                WHERE wallet_id = ?
                  AND crypto_id = ?
            ";

            $check = $conn->prepare($balance_sql);

            $wallet_id = (int) $transaction["wallet_id"];
            $crypto_id = (int) $transaction["crypto_id"];

            $check->bind_param(
                "isisii",
                $transaction_id,
                $quantity,
                $transaction_id,
                $quantity,
                $wallet_id,
                $crypto_id
            );

            $check->execute();

            $balance = $check->get_result()->fetch_assoc();

            $check->close();

            if ((int) $balance["valid_balance"] !== 1) {

                $error = "This change would create a negative cryptocurrency balance.";

            } else {

                // ======================================
                // UPDATE TRANSACTION
                // ======================================

                $price_value = $price === "" ? null : $price;

                $update_sql = "
                    UPDATE wallet_transactions AS t
                    INNER JOIN wallets AS w
                        ON t.wallet_id = w.wallet_id
                    SET
                        t.quantity = ?,
                        t.price_usd = ?
                    WHERE t.transaction_id = ?
                      AND w.user_id = ?
                ";

                $update = $conn->prepare($update_sql);

                $update->bind_param(
                    "ssii",
                    $quantity,
                    $price_value,
                    $transaction_id,
                    $user_id
                );

                $update->execute();
                $update->close();

                // One-time success message
                $_SESSION["transaction_updated"] = true;

                // Renew security token
                $_SESSION["edit_transaction_token"] =
                    bin2hex(random_bytes(32));

                header("Location: transactions.php");
                exit;
            }

        } catch (mysqli_sql_exception $e) {

            error_log($e->getMessage());
            $error = "Unable to update transaction.";
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

    <title>CryptoTracker | Edit Transaction</title>

    <link rel="stylesheet" href="css/style.css?v=7">
</head>

<body>

    <!-- NAVIGATION -->
    <header class="navbar">

        <div class="logo">CryptoTracker</div>

        <nav>
            <a href="index.php">Home</a>
            <a href="wallets.php">My Wallets</a>
            <a href="transactions.php">Transactions</a>
            <a href="portfolio.php">Portfolio Summary</a>
        </nav>

    </header>

    <!-- EDIT TRANSACTION FORM -->
    <main class="container">

        <section class="form-card">

            <h1>Edit Transaction</h1>

            <p>Update your cryptocurrency transaction information.</p>

            <?php if ($error !== ""): ?>

                <div class="error-message">
                    <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
                </div>

            <?php endif; ?>

            <form method="POST" action="edit_transaction.php">

                <!-- Transaction ID -->
                <input
                    type="hidden"
                    name="transaction_id"
                    value="<?= (int) $transaction_id ?>"
                >

                <!-- Security Token -->
                <input
                    type="hidden"
                    name="edit_transaction_token"
                    value="<?= htmlspecialchars(
                        $_SESSION["edit_transaction_token"],
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                >

                <!-- Wallet -->
                <div class="form-group">

                    <label>Wallet</label>

                    <p>
                        <?= htmlspecialchars(
                            $transaction["wallet_name"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>
                    </p>

                </div>

                <!-- Cryptocurrency -->
                <div class="form-group">

                    <label>Cryptocurrency</label>

                    <p>
                        <?= htmlspecialchars(
                            $transaction["crypto_name"] .
                            " (" . $transaction["symbol"] . ")",
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>
                    </p>

                </div>

                <!-- Transaction Type -->
                <div class="form-group">

                    <label>Transaction Type</label>

                    <p>
                        <?= htmlspecialchars($type, ENT_QUOTES, "UTF-8") ?>
                    </p>

                </div>

                <!-- Quantity -->
                <div class="form-group">

                    <label for="quantity">Quantity</label>

                    <input
                        type="text"
                        inputmode="decimal"
                        id="quantity"
                        name="quantity"
                        value="<?= htmlspecialchars(
                            $quantity,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        required
                    >

                </div>

                <!-- Price -->
                <div class="form-group">

                    <label for="price_usd">
                        Price per Coin (USD)
                    </label>

                    <input
                        type="text"
                        inputmode="decimal"
                        id="price_usd"
                        name="price_usd"
                        value="<?= htmlspecialchars(
                            $price,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                    >

                    <small>
                        Required for BUY and SELL.
                        Optional for deposits and withdrawals.
                    </small>

                </div>

                <button type="submit" class="form-button">
                    Save Changes
                </button>

            </form>

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
