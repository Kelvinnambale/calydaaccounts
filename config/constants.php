<?php
if (!defined('SYSTEM_ACCESS')) {
    die('Direct access not allowed');
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'calyda_accounts');
define('DB_USER', 'root');
define('DB_PASS', '');

// System Configuration
define('SYSTEM_NAME', 'Calyda Accounts - Internal Record Management System');
define('SYSTEM_VERSION', '1.0.0');
define('DEFAULT_TIMEZONE', 'Africa/Nairobi');

// User Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_USER', 'user');

// Set timezone
date_default_timezone_set(DEFAULT_TIMEZONE);

// Kenyan Counties
define('KENYAN_COUNTIES', [
    'Baringo', 'Bomet', 'Bungoma', 'Busia', 'Elgeyo-Marakwet', 'Embu', 'Garissa', 
    'Homa Bay', 'Isiolo', 'Kajiado', 'Kakamega', 'Kericho', 'Kiambu', 'Kilifi', 
    'Kirinyaga', 'Kisii', 'Kisumu', 'Kitui', 'Kwale', 'Laikipia', 'Lamu', 'Machakos', 
    'Makueni', 'Mandera', 'Marsabit', 'Meru', 'Migori', 'Mombasa', 'Murang\'a', 
    'Nairobi', 'Nakuru', 'Nandi', 'Narok', 'Nyamira', 'Nyandarua', 'Nyeri', 
    'Samburu', 'Siaya', 'Taita-Taveta', 'Tana River', 'Tharaka-Nithi', 'Trans Nzoia', 
    'Turkana', 'Uasin Gishu', 'Vihiga', 'Wajir', 'West Pokot'
]);

// Tax Obligations
define('TAX_OBLIGATIONS', [
    'VAT' => 'Value Added Tax',
    'Income Tax' => 'Income Tax',
    'PAYEE' => 'Pay As You Earn',
    'Rental' => 'Rental Income Tax',
    'TOT' => 'Turnover Tax',
    'Partnership' => 'Partnership Tax',
    'Other' => 'Other Tax Obligations'
]);

// Client Types
define('CLIENT_TYPES', [
    'Individual' => 'Individual',
    'Company' => 'Company',
    'Both' => 'Both Individual & Company'
]);
?>
