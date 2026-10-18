
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

// Generate token for secure deletion
if (empty($_SESSION["delete_wallet_token"])) {
    $_SESSION["delete_wallet_token"] = bin2hex(random_bytes(32));
}


// ======================================
// RETRIEVE USER'S WALLETS
// ======================================

$sql = "
    SELECT
        w.wallet_id,
        u.first_name,
        u.last_name,
        w.wallet_name,
        w.date_created
    FROM users AS u
    INNER JOIN wallets AS w
        ON u.user_id = w.user_id
    WHERE u.user_id = ?
    ORDER BY w.wallet_id
";

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

    <title>CryptoTracker | My Wallets</title>

    <link rel="stylesheet" href="css/style.css?v=4">
</head>

<body>

    <!-- NAVIGATION -->
    <header class="navbar">

        <div class="logo">CryptoTracker</div>

        <nav>
            <a href="index.php">Home</a>
            <a href="wallets.php" class="active">My Wallets</a>
            <a href="transactions.php">Transactions</a>
            <a href="portfolio.php">Portfolio Summary</a>
        </nav>

    </header>


    <!-- MAIN CONTENT -->
    <main class="container">

        <section class="crypto-section">

            <h1>Cryptocurrency Wallets</h1>

            <p>View and manage your cryptocurrency wallets.</p>


            <!-- CREATE SUCCESS MESSAGE -->
            <?php if (!empty($_SESSION["wallet_created"])): ?>

                <div class="success-message">
                    Wallet created successfully!
                </div>

                <?php unset($_SESSION["wallet_created"]); ?>

            <?php endif; ?>


<!-- WALLET UPDATE SUCCESS MESSAGE -->
<?php if (!empty($_SESSION["wallet_updated"])): ?>

    <div class="success-message">
        Wallet updated successfully!
    </div>

    <?php unset($_SESSION["wallet_updated"]); ?>

<?php endif; ?>



            <!-- DELETE SUCCESS MESSAGE -->
            <?php if (!empty($_SESSION["wallet_deleted"])): ?>

                <div class="success-message">
                    Wallet deleted successfully!
                </div>

                <?php unset($_SESSION["wallet_deleted"]); ?>

            <?php endif; ?>


            <!-- DELETE ERROR MESSAGE -->
            <?php if (!empty($_SESSION["wallet_delete_error"])): ?>

                <div class="error-message">
                    <?= htmlspecialchars(
                        $_SESSION["wallet_delete_error"],
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </div>

                <?php unset($_SESSION["wallet_delete_error"]); ?>

            <?php endif; ?>


            <!-- CREATE WALLET BUTTON -->
            <div class="wallet-actions">

                <a href="create_wallet.php"
                   class="wallet-create-link">
                    + Create Wallet
                </a>

            </div>


            <!-- WALLETS TABLE -->
            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Owner</th>
                            <th>Wallet Name</th>
                            <th>Date Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($result->num_rows > 0): ?>

                        <?php while ($row = $result->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars(
                                        $row["first_name"] . " " .
                                        $row["last_name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row["wallet_name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row["date_created"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </td>

                                
<td>
    <div class="wallet-row-actions">

        <!-- EDIT BUTTON -->
        <a
            href="edit_wallet.php?id=<?= (int) $row["wallet_id"] ?>"
            class="wallet-edit-link"
        >
            Edit
        </a>

        <!-- DELETE BUTTON -->
        <form
            action="delete_wallet.php"
            method="POST"
            class="wallet-delete-form"
            onsubmit="return confirm('Are you sure you want to delete this wallet?');"
        >

            <input
                type="hidden"
                name="wallet_id"
                value="<?= (int) $row["wallet_id"] ?>"
            >

            <input
                type="hidden"
                name="delete_wallet_token"
                value="<?= htmlspecialchars(
                    $_SESSION["delete_wallet_token"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>"
            >

            <button
                type="submit"
                class="wallet-delete-button"
            >
                Delete
            </button>

        </form>

    </div>
</td>


                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="4" style="text-align:center;">
                                You don't have any wallets yet.
                                Click Create Wallet to get started.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>


    <!-- FOOTER -->
    <footer>
        <p>
            CIS 344 Group Project |
            Cryptocurrency Wallet Tracker
        </p>
    </footer>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
