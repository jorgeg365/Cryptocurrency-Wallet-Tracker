
<?php
session_start();

require_once __DIR__ . "/includes/db.php";

$error = "";
$email = "";

// Generate a token to protect the login form
if (empty($_SESSION["login_token"])) {
    $_SESSION["login_token"] = bin2hex(random_bytes(32));
}

// Process login form
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $token = $_POST["login_token"] ?? "";

    if (!hash_equals($_SESSION["login_token"], $token)) {
        $error = "Invalid form submission. Please try again.";

    } elseif (empty($email) || empty($password)) {
        $error = "Please enter your email and password.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email or password.";

    } else {

        // Find user using a prepared statement
        $sql = "SELECT user_id, first_name, password_hash
                FROM users
                WHERE email = ?
                LIMIT 1";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        $stmt->close();

        // Verify password against stored hash
        if ($user && password_verify(
            $password,
            $user["password_hash"]
        )) {

            // Protect against session fixation
            session_regenerate_id(true);

            // Remember logged-in user
            $_SESSION["user_id"] = $user["user_id"];
            $_SESSION["first_name"] = $user["first_name"];

            unset($_SESSION["login_token"]);

            header("Location: index.php");
            exit;

        } else {
            $error = "Invalid email or password.";
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

    <title>CryptoTracker | Login</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <!-- Navigation -->
    <header class="navbar">

        <div class="logo">CryptoTracker</div>

        <nav>
            <a href="index.php">Home</a>
            <a href="register.php">Register</a>
            <a href="login.php" class="active">Login</a>
        </nav>

    </header>

    <main class="container">

        <section class="form-card">

            <h1>Welcome Back</h1>

            <p>Log in to manage your cryptocurrency wallets.</p>

            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">

                <input
                    type="hidden"
                    name="login_token"
                    value="<?= htmlspecialchars(
                        $_SESSION["login_token"],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= htmlspecialchars(
                            $email,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="password">
                        Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                    >

                </div>

                <button type="submit" class="form-button">
                    Login
                </button>

            </form>

            <p style="margin-top: 20px;">
                Don't have an account?
                <a href="register.php">Register here</a>
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
