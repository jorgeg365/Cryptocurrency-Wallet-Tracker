
<?php
session_start();

// ======================================
// REQUIRE USER LOGIN
// ======================================

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Only allow POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: wallets.php");
    exit;
}

require_once __DIR__ . "/includes/db.php";

$user_id = (int) $_SESSION["user_id"];

// ======================================
// VALIDATE SECURITY TOKEN
// ======================================

$expected_token = $_SESSION["delete_wallet_token"] ?? "";
$submitted_token = $_POST["delete_wallet_token"] ?? "";

if (
    !is_string($submitted_token) ||
    $expected_token === "" ||
    !hash_equals($expected_token, $submitted_token)
) {
    $_SESSION["wallet_delete_error"] =
        "Invalid request. Please try again.";

    header("Location: wallets.php");
    exit;
}


// ======================================
// VALIDATE WALLET ID
// ======================================

$wallet_id_input = $_POST["wallet_id"] ?? "";

if (
    !is_string($wallet_id_input) ||
    !ctype_digit($wallet_id_input) ||
    (int) $wallet_id_input <= 0
) {
    $_SESSION["wallet_delete_error"] =
        "Invalid wallet selected.";

    header("Location: wallets.php");
    exit;
}

$wallet_id = (int) $wallet_id_input;


// ======================================
// DELETE WALLET
// ======================================

try {

    // Delete only wallets owned by this user.
    // Do not delete wallets containing transactions.

    $sql = "
        DELETE FROM wallets
        WHERE wallet_id = ?
          AND user_id = ?
          AND NOT EXISTS (
              SELECT 1
              FROM wallet_transactions
              WHERE wallet_id = ?
          )
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "iii",
        $wallet_id,
        $user_id,
        $wallet_id
    );

    $stmt->execute();

    if ($stmt->affected_rows === 1) {

        $_SESSION["wallet_deleted"] = true;

    } else {

        $_SESSION["wallet_delete_error"] =
            "Wallet could not be deleted. It may contain transactions or no longer be available.";

    }

    $stmt->close();

} catch (mysqli_sql_exception $e) {

    error_log($e->getMessage());

    $_SESSION["wallet_delete_error"] =
        "Unable to delete wallet. Please try again.";

}

$conn->close();

// Return to My Wallets
header("Location: wallets.php");
exit;
?>
