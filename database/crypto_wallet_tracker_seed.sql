
-- ============================================
-- CRYPTOCURRENCY WALLET TRACKER
-- STARTER DATA
-- CIS 344 GROUP PROJECT
-- ============================================

USE crypto_wallet_tracker;

-- ============================================
-- INSERT SUPPORTED CRYPTOCURRENCIES
-- ============================================

INSERT IGNORE INTO cryptocurrencies
    (crypto_name, symbol)
VALUES
    ('Bitcoin', 'BTC'),
    ('Ethereum', 'ETH'),
    ('Solana', 'SOL'),
    ('Cardano', 'ADA'),
    ('Dogecoin', 'DOGE');


-- ============================================
-- VERIFY CRYPTOCURRENCY DATA
-- ============================================

SELECT *
FROM cryptocurrencies
ORDER BY crypto_name;
