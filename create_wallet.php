
<?php
session_start();

// Require user login
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . "/includes/db.php";

$user_id = (int) $_SESSION["user_id"];

$error = "";
$wallet_name = "";

// Generate a security token
if (empty($_SESSION["wallet_token"])) {
    $_SESSION["wallet_token"] = bin2hex(random_bytes(32));
}

// Process Create Wallet form
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $wallet_name = trim($_POST["wallet_name"] ?? "");
    $token = $_POST["wallet_token"] ?? "";

    // Validate form submission
    if (!hash_equals($_SESSION["wallet_token"], $token)) {

        $error = "Invalid form submission.";

    } elseif ($wallet_name === "") {

        $error = "Please enter a wallet name.";

    } elseif (strlen($wallet_name) > 100) {

        $error = "Wallet name must be 100 characters or fewer.";

    } else {

        try {

            // Insert wallet for the logged-in user
            $sql = "INSERT INTO wallets
                    (user_id, wallet_name)
                    VALUES (?, ?)";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                "is",
                $user_id,
                $wallet_name
            );

            $stmt->execute();
            $stmt->close();

            // Renew security token
$_SESSION["wallet_token"] = bin2hex(random_bytes(32));

// Store success message in the session
$_SESSION["wallet_created"] = true;

// Redirect to My Wallets
header("Location: wallets.php");
exit;

        } catch (mysqli_sql_exception $e) {

            error_log($e->getMessage());
            $error = "Unable to create wallet. Please try again.";

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

    <title>CryptoTracker | Create Wallet</title>

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
            <a href="portfolio.php">Portfolio Summary</a>
        </nav>

    </header>

    <!-- Create Wallet Form -->
    <main class="container">

        <section class="form-card">

            <h1>Create Wallet</h1>

            <p>
                Give your cryptocurrency wallet a name
                to start tracking your assets.
            </p>

            <?php if (!empty($error)): ?>

                <div class="error-message">
                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </div>

            <?php endif; ?>

            <form action="create_wallet.php" method="POST">

                <input
                    type="hidden"
                    name="wallet_token"
                    value="<?= htmlspecialchars(
                        $_SESSION["wallet_token"],
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                >

                <div class="form-group">

                    <label for="wallet_name">
                        Wallet Name
                    </label>

                    <input
                        type="text"
                        id="wallet_name"
                        name="wallet_name"
                        maxlength="100"
                        placeholder="e.g. My Bitcoin Wallet"
                        value="<?= htmlspecialchars(
                            $wallet_name,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        required
                    >

                </div>

                <button type="submit" class="form-button">
                    Create Wallet
                </button>

            </form>

            <p style="margin-top: 20px;">
                <a href="wallets.php">Back to My Wallets</a>
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
