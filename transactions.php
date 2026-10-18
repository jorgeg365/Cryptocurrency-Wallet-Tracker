
<?php
session_start();

// Require login before viewing transactions
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// ======================================
// GENERATE DELETE SECURITY TOKEN
// ======================================

if (empty($_SESSION["delete_transaction_token"])) {
    $_SESSION["delete_transaction_token"] =
        bin2hex(random_bytes(32));
}

require_once __DIR__ . "/includes/db.php";

// Get the logged-in user's ID
$user_id = (int) $_SESSION["user_id"];

// Retrieve only the logged-in user's transactions
$sql = "
    SELECT
        t.transaction_id,
        u.first_name,
        u.last_name,
        w.wallet_name,
        c.crypto_name,
        c.symbol,
        t.transaction_type,
        t.quantity,
        t.price_usd,
        t.transaction_date
    FROM users AS u
    INNER JOIN wallets AS w
        ON u.user_id = w.user_id
    INNER JOIN wallet_transactions AS t
        ON w.wallet_id = t.wallet_id
    INNER JOIN cryptocurrencies AS c
        ON t.crypto_id = c.crypto_id
    WHERE u.user_id = ?
    ORDER BY t.transaction_id
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

    <title>CryptoTracker | Transactions</title>

    <link rel="stylesheet" href="css/style.css?v=8">
</head>

<body>

    <!-- Navigation -->
    <header class="navbar">

        <div class="logo">CryptoTracker</div>

<nav>
    <a href="index.php">Home</a>
    <a href="wallets.php">My Wallets</a>
    <a href="transactions.php">Transactions</a>
    <a href="portfolio.php">Portfolio Summary</a>
    <!-- <a href="login.php">Login</a> -->
</nav>

    </header>

    <!-- Transaction History -->
    <main class="container">

        <section class="crypto-section">

            <h1>Transaction History</h1>

            <p>
                View cryptocurrency transactions
                recorded in the database.
            </p>




<!-- ======================================
     DELETE SUCCESS MESSAGE
====================================== -->

<?php if (!empty($_SESSION["transaction_deleted"])): ?>

    <div class="success-message">
        Transaction deleted successfully!
    </div>

    <?php unset($_SESSION["transaction_deleted"]); ?>

<?php endif; ?>


<!-- ======================================
     DELETE ERROR MESSAGE
====================================== -->

<?php if (!empty($_SESSION["transaction_delete_error"])): ?>

    <div class="error-message">

        <?= htmlspecialchars(
            $_SESSION["transaction_delete_error"],
            ENT_QUOTES,
            "UTF-8"
        ) ?>

    </div>

    <?php unset($_SESSION["transaction_delete_error"]); ?>

<?php endif; ?>







<?php if (!empty($_SESSION["transaction_created"])): ?>

    <div class="success-message">
        Transaction added successfully!
    </div>


<!-- EDIT SUCCESS MESSAGE -->
<?php if (!empty($_SESSION["transaction_updated"])): ?>

    <div class="success-message">
        Transaction updated successfully!
    </div>

    <?php unset($_SESSION["transaction_updated"]); ?>

<?php endif; ?>


    <?php unset($_SESSION["transaction_created"]); ?>

<?php endif; ?>

<div class="transaction-actions">
    <a href="add_transaction.php"
       class="transaction-create-link">
        + Add Transaction
    </a>
</div>


            <br>

            <div class="table-wrapper">
                <table>

                    <thead>
                        <tr>
                            <th>Owner</th>
                            <th>Wallet</th>
                            <th>Cryptocurrency</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Price per Coin (USD)</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php while ($row = $result->fetch_assoc()): ?>

                        <?php
                            $owner = $row["first_name"] . " " .
                                     $row["last_name"];

                            $crypto = $row["crypto_name"] .
                                      " (" . $row["symbol"] . ")";

                            $quantity = rtrim(
                                rtrim($row["quantity"], "0"), "."
                            );

                            if ($quantity === "") {
                                $quantity = "0";
                            }

                            $price = $row["price_usd"] === null
                                ? "N/A"
                                : "$" . number_format(
                                    (float)$row["price_usd"], 2
                                );
                        ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars($owner) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row["wallet_name"]) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($crypto) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row["transaction_type"]) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($quantity) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($price) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row["transaction_date"]) ?>
                            </td>


<!-- ======================================
     TRANSACTION ACTION BUTTONS
====================================== -->

<td>

    <div class="transaction-row-actions">

        <!-- EDIT BUTTON -->
        <a
            href="edit_transaction.php?id=<?= (int) $row["transaction_id"] ?>"
            class="transaction-edit-link"
        >
            Edit
        </a>

        <!-- DELETE BUTTON -->
        <form
            action="delete_transaction.php"
            method="POST"
            class="transaction-delete-form"
            onsubmit="return confirm('Are you sure you want to delete this transaction? This cannot be undone.');"
        >

            <input
                type="hidden"
                name="transaction_id"
                value="<?= (int) $row["transaction_id"] ?>"
            >

            <input
                type="hidden"
                name="delete_transaction_token"
                value="<?= htmlspecialchars(
                    $_SESSION["delete_transaction_token"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>"
            >

            <button
                type="submit"
                class="transaction-delete-button"
            >
                Delete
            </button>

        </form>

    </div>

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
