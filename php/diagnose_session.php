<?php
session_start();
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "Session Save Path: " . (session_save_path() ?: ini_get('session.save_path')) . "\n";
echo "Cookie Path: " . ini_get('session.cookie_path') . "\n";
echo "Session data:\n";
print_r($_SESSION);
echo "\nCookie header: " . ($_SERVER['HTTP_COOKIE'] ?? 'NOT SET');
echo "</pre>";
