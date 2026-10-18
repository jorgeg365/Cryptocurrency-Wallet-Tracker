
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
    header("Location: transactions.php");
    exit;
}

require_once __DIR__ . "/includes/db.php";

$user_id = (int) $_SESSION["user_id"];

// ======================================
// VALIDATE SECURITY TOKEN
// ======================================

$expected_token =
    $_SESSION["delete_transaction_token"] ?? "";

$submitted_token =
    $_POST["delete_transaction_token"] ?? "";

if (
    !is_string($submitted_token) ||
    $expected_token === "" ||
    !hash_equals($expected_token, $submitted_token)
) {
    $_SESSION["transaction_delete_error"] =
        "Invalid request. Please try again.";

    header("Location: transactions.php");
    exit;
}

// ======================================
// VALIDATE TRANSACTION ID
// ======================================

$transaction_id = filter_var(
    $_POST["transaction_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if (!$transaction_id) {
    $_SESSION["transaction_delete_error"] =
        "Invalid transaction selected.";

    header("Location: transactions.php");
    exit;
}

// ======================================
// DELETE TRANSACTION
// ======================================

try {

    $conn->begin_transaction();

    // Find transaction and verify ownership
    $sql = "
        SELECT
            t.wallet_id,
            t.crypto_id
        FROM wallet_transactions AS t
        INNER JOIN wallets AS w
            ON t.wallet_id = w.wallet_id
        WHERE t.transaction_id = ?
          AND w.user_id = ?
        FOR UPDATE
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "ii",
        $transaction_id,
        $user_id
    );

    $stmt->execute();

    $transaction = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$transaction) {
        throw new RuntimeException(
            "Transaction not found or unavailable."
        );
    }

    $wallet_id = (int) $transaction["wallet_id"];
    $crypto_id = (int) $transaction["crypto_id"];

    // ======================================
    // CHECK BALANCE AFTER DELETION
    // ======================================

    // Calculate balance without this transaction
    $balance_sql = "
        SELECT
            COALESCE(
                SUM(
                    CASE
                        WHEN transaction_type IN
                            ('BUY', 'DEPOSIT')
                            THEN quantity

                        WHEN transaction_type IN
                            ('SELL', 'WITHDRAWAL')
                            THEN -quantity

                        ELSE 0
                    END
                ), 0
            ) >= 0 AS valid_balance
        FROM wallet_transactions
        WHERE wallet_id = ?
          AND crypto_id = ?
          AND transaction_id <> ?
    ";

    $check = $conn->prepare($balance_sql);

    $check->bind_param(
        "iii",
        $wallet_id,
        $crypto_id,
        $transaction_id
    );

    $check->execute();

    $balance = $check->get_result()->fetch_assoc();

    $check->close();

    if ((int) $balance["valid_balance"] !== 1) {
        throw new RuntimeException(
            "Cannot delete this transaction because it would make the wallet balance negative."
        );
    }

    // ======================================
    // DELETE RECORD FROM DATABASE
    // ======================================

    $delete_sql = "
        DELETE FROM wallet_transactions
        WHERE transaction_id = ?
          AND wallet_id = ?
    ";

    $delete = $conn->prepare($delete_sql);

    $delete->bind_param(
        "ii",
        $transaction_id,
        $wallet_id
    );

    $delete->execute();

    if ($delete->affected_rows !== 1) {
        throw new RuntimeException(
            "Transaction could not be deleted."
        );
    }

    $delete->close();

    $conn->commit();

    // Store one-time success message
    $_SESSION["transaction_deleted"] = true;

    // Renew security token
    $_SESSION["delete_transaction_token"] =
        bin2hex(random_bytes(32));

} catch (RuntimeException $e) {

    $conn->rollback();

    $_SESSION["transaction_delete_error"] =
        $e->getMessage();

} catch (mysqli_sql_exception $e) {

    $conn->rollback();

    error_log($e->getMessage());

    $_SESSION["transaction_delete_error"] =
        "Unable to delete transaction. Please try again.";
}

$conn->close();

// Return to Transaction History
header("Location: transactions.php");
exit;
?>
