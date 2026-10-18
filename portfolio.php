
<?php
session_start();

// Require the user to log in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . "/includes/db.php";

// Get the currently logged-in user's ID
$user_id = (int) $_SESSION["user_id"];

// Calculate balances for the logged-in user's wallets
$sql = "
    SELECT
        u.first_name,
        u.last_name,
        w.wallet_name,
        c.crypto_name,
        c.symbol,
        SUM(
            CASE
                WHEN t.transaction_type IN ('BUY', 'DEPOSIT')
                    THEN t.quantity
                WHEN t.transaction_type IN ('SELL', 'WITHDRAWAL')
                    THEN -t.quantity
                ELSE 0
            END
        ) AS total_quantity
    FROM users AS u
    INNER JOIN wallets AS w
        ON u.user_id = w.user_id
    INNER JOIN wallet_transactions AS t
        ON w.wallet_id = t.wallet_id
    INNER JOIN cryptocurrencies AS c
        ON t.crypto_id = c.crypto_id
    WHERE u.user_id = ?
    GROUP BY
        u.user_id,
        u.first_name,
        u.last_name,
        w.wallet_id,
        w.wallet_name,
        c.crypto_id,
        c.crypto_name,
        c.symbol
    ORDER BY
        w.wallet_id,
        c.symbol
";

// Use a prepared statement for security
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>CryptoTracker | Portfolio Summary</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <!-- Navigation -->
    <header class="navbar">

        <div class="logo">CryptoTracker</div>

        <nav>
            <a href="index.php">Home</a>
            <a href="wallets.php">My Wallets</a>
            <a href="transactions.php">Transactions</a>
            <a href="portfolio.php" class="active">
                Portfolio Summary
            </a>
            <!-- <a href="login.php">Login</a> -->
        </nav>

    </header>

    <!-- Portfolio Summary -->
    <main class="container">

        <section class="crypto-section">

            <h1>Portfolio Summary</h1>

            <p>
                View cryptocurrency balances calculated
                from wallet transactions.
            </p>

            <br>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Owner</th>
                            <th>Wallet</th>
                            <th>Cryptocurrency</th>
                            <th>Symbol</th>
                            <th>Total Quantity</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <?php
                            $owner = $row["first_name"] . " " .
                                     $row["last_name"];

                            // Remove unnecessary trailing zeros
                            $quantity = rtrim(
                                rtrim($row["total_quantity"], "0"),
                                "."
                            );

                            if ($quantity === "" || $quantity === "-") {
                                $quantity = "0";
                            }
                        ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars($owner) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row["wallet_name"]) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row["crypto_name"]) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row["symbol"]) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($quantity) ?>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

    <!-- Footer -->
    <footer>
        <p>
            CIS 344 Group Project |
            Cryptocurrency Wallet Tracker
        </p>
    </footer>

</body>
</html>

<?php $conn->close(); ?>
