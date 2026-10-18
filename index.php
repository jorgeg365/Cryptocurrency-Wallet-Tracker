<?php
session_start();

require_once __DIR__ . "/includes/db.php";




// Retrieve cryptocurrencies from MySQL
$sql = "SELECT crypto_name, symbol
        FROM cryptocurrencies
        ORDER BY crypto_name";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CryptoTracker | Home</title>

    <link rel="stylesheet" href="css/style.css?v=10">
</head>

<body>

    <!-- Website Navigation -->
    <header class="navbar">
        <div class="logo">CryptoTracker</div>


<nav>
    <a href="index.php">Home</a>

    <?php if (isset($_SESSION["user_id"])): ?>

        <a href="wallets.php">My Wallets</a>
        <a href="transactions.php">Transactions</a>
        <a href="portfolio.php">Portfolio Summary</a>

        <span class="welcome-user">
            Welcome,
            <?= htmlspecialchars(
                $_SESSION["first_name"],
                ENT_QUOTES,
                "UTF-8"
            ) ?>
        </span>

        <form action="logout.php" method="POST"
              class="logout-form">
            <button type="submit" class="logout-button">
                Logout
            </button>
        </form>

    <?php else: ?>

        <a href="register.php">Register</a>
        <a href="login.php">Login</a>

    <?php endif; ?>
</nav>

    </header>

    <!-- Welcome Section -->
    <main class="container">

        <section class="hero">
            <h1>Cryptocurrency Wallet Tracker</h1>

            <p>
                Manage your crypto wallets, track transactions,
                and monitor your cryptocurrency holdings
                in one convenient place.
            </p>
        </section>

<!-- MAIN WEBSITE FEATURES -->

<section class="features">

    <!-- MY WALLETS -->
    <a href="wallets.php" class="card feature-card">

        <h2>My Wallets</h2>

        <p>
            Create, view, edit, and delete your
            cryptocurrency wallets.
        </p>

        <span>View My Wallets &rarr;</span>

    </a>

    <!-- TRANSACTIONS -->
    <a href="transactions.php" class="card feature-card">

        <h2>Transactions</h2>

        <p>
            Record purchases and sales, manage
            transactions, and track your activity.
        </p>

        <span>View Transactions &rarr;</span>

    </a>

    <!-- PORTFOLIO -->
    <a href="portfolio.php" class="card feature-card">

        <h2>Portfolio Summary</h2>

        <p>
            Monitor your cryptocurrency holdings
            and automatically calculated balances.
        </p>

        <span>View Portfolio &rarr;</span>

    </a>

</section>

        <!-- Cryptocurrency List -->
        <section class="crypto-section">

            <h2>Supported Cryptocurrencies</h2>

            <table>
                <thead>
                    <tr>
                        <th>Cryptocurrency</th>
                        <th>Symbol</th>
                    </tr>
                </thead>

                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($row["crypto_name"]) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($row["symbol"]) ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

        </section>

    </main>

    <!-- Footer -->
    <footer>
        <p>CIS 344 Group Project | Cryptocurrency Wallet Tracker</p>
    </footer>

</body>
</html>

<?php
$conn->close();
?>
