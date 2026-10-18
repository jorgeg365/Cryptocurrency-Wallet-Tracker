
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
if (empty($_SESSION["edit_wallet_token"])) {
    $_SESSION["edit_wallet_token"] = bin2hex(random_bytes(32));
}

// ======================================
// GET WALLET ID
// ======================================

$id_input = $_SERVER["REQUEST_METHOD"] === "POST"
    ? ($_POST["wallet_id"] ?? null)
    : ($_GET["id"] ?? null);

$wallet_id = filter_var(
    $id_input,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if (!$wallet_id) {
    header("Location: wallets.php");
    exit;
}

// ======================================
// VERIFY WALLET OWNERSHIP
// ======================================

$sql = "
    SELECT wallet_id, wallet_name
    FROM wallets
    WHERE wallet_id = ?
      AND user_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $wallet_id, $user_id);
$stmt->execute();

$wallet = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$wallet) {
    http_response_code(404);
    exit("Wallet not found.");
}

$wallet_name = $wallet["wallet_name"];

// ======================================
// PROCESS EDIT FORM
// ======================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submitted_name = $_POST["wallet_name"] ?? "";
    $token = $_POST["edit_wallet_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals(
            $_SESSION["edit_wallet_token"],
            $token
        )
    ) {
        $error = "Invalid form submission.";

    } elseif (!is_string($submitted_name)) {
        $error = "Invalid wallet name.";

    } else {

        $wallet_name = trim($submitted_name);

        if ($wallet_name === "") {
            $error = "Please enter a wallet name.";

        } elseif (strlen($wallet_name) > 100) {
            $error = "Wallet name must be 100 bytes or fewer.";

        } else {

            try {

                // Update only the logged-in user's wallet
                $update_sql = "
                    UPDATE wallets
                    SET wallet_name = ?
                    WHERE wallet_id = ?
                      AND user_id = ?
                ";

                $update = $conn->prepare($update_sql);

                $update->bind_param(
                    "sii",
                    $wallet_name,
                    $wallet_id,
                    $user_id
                );

                $update->execute();
                $update->close();

                // Store one-time success message
                $_SESSION["wallet_updated"] = true;

                // Renew security token
                $_SESSION["edit_wallet_token"] =
                    bin2hex(random_bytes(32));

                header("Location: wallets.php");
                exit;

            } catch (mysqli_sql_exception $e) {

                error_log($e->getMessage());
                $error = "Unable to update wallet.";
            }
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

    <title>CryptoTracker | Edit Wallet</title>

    <link rel="stylesheet" href="css/style.css">
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

    <main class="container">

        <section class="form-card">

            <h1>Edit Wallet</h1>

            <p>Update the name of your cryptocurrency wallet.</p>

            <?php if ($error !== ""): ?>

                <div class="error-message">
                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </div>

            <?php endif; ?>

            <form action="edit_wallet.php" method="POST">

                <input
                    type="hidden"
                    name="wallet_id"
                    value="<?= (int) $wallet_id ?>"
                >

                <input
                    type="hidden"
                    name="edit_wallet_token"
                    value="<?= htmlspecialchars(
                        $_SESSION["edit_wallet_token"],
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
                        value="<?= htmlspecialchars(
                            $wallet_name,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        required
                    >

                </div>

                <button type="submit" class="form-button">
                    Save Changes
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
