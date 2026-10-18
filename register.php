
<?php
session_start();

require_once __DIR__ . "/includes/db.php";

// Create a security token for the registration form
if (empty($_SESSION["register_token"])) {
    $_SESSION["register_token"] = bin2hex(random_bytes(32));
}

$error = "";
$success = "";

$first_name = "";
$last_name = "";
$email = "";

// Process the registration form
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $first_name = trim($_POST["first_name"] ?? "");
    $last_name = trim($_POST["last_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";
    $token = $_POST["register_token"] ?? "";

    // Validate the submitted information
    if (!hash_equals($_SESSION["register_token"], $token)) {
        $error = "Invalid form submission. Please try again.";
    } elseif (
        empty($first_name) ||
        empty($last_name) ||
        empty($email) ||
        empty($password)
    ) {
        $error = "Please fill in all fields.";
    } elseif (
        strlen($first_name) > 50 ||
        strlen($last_name) > 50
    ) {
        $error = "First and last names must be 50 characters or fewer.";
    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 255
    ) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {

        // Hash password before storing it
        $password_hash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        try {
            // Insert the new user using a prepared statement
            $sql = "INSERT INTO users
                    (first_name, last_name, email, password_hash)
                    VALUES (?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                "ssss",
                $first_name,
                $last_name,
                $email,
                $password_hash
            );

            $stmt->execute();
            $stmt->close();

            $success = "Account created successfully!";

            // Clear the form
            $first_name = "";
            $last_name = "";
            $email = "";

            // Renew the form token
            $_SESSION["register_token"] = bin2hex(random_bytes(32));

        } catch (mysqli_sql_exception $e) {

            if ($e->getCode() == 1062) {
                $error = "This email is already registered.";
            } else {
                error_log($e->getMessage());
                $error = "Registration failed. Please try again.";
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

    <title>CryptoTracker | Register</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <!-- Navigation -->
    <header class="navbar">
        <div class="logo">CryptoTracker</div>

        <nav>
            <a href="index.php">Home</a>
           <!--<a href="wallets.php">My Wallets</a>
            <a href="transactions.php">Transactions</a>
            <a href="portfolio.php">Portfolio Summary</a> -->
            <a href="register.php" class="active">Register</a> 
        </nav>
    </header>

    <!-- Registration Form -->
    <main class="container">

        <section class="form-card">

            <h1>Create an Account</h1>

            <p>Register to start tracking your cryptocurrency wallets.</p>

            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="success-message">
                    <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST">

                <input
                    type="hidden"
                    name="register_token"
                    value="<?= htmlspecialchars(
                        $_SESSION["register_token"],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                <div class="form-group">
                    <label for="first_name">First Name</label>

                    <input
                        type="text"
                        id="first_name"
                        name="first_name"
                        maxlength="50"
                        value="<?= htmlspecialchars(
                            $first_name,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="last_name">Last Name</label>

                    <input
                        type="text"
                        id="last_name"
                        name="last_name"
                        maxlength="50"
                        value="<?= htmlspecialchars(
                            $last_name,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        maxlength="255"
                        value="<?= htmlspecialchars(
                            $email,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        minlength="8"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="confirm_password">
                        Confirm Password
                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        minlength="8"
                        required
                    >
                </div>

                <button type="submit" class="form-button">
                    Create Account
                </button>

            </form>

        </section>

    </main>

    <footer>
        <p>CIS 344 Group Project | Cryptocurrency Wallet Tracker</p>
    </footer>

</body>
</html>

<?php $conn->close(); ?>
