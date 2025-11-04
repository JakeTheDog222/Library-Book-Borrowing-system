<?php
require 'helpers.php';
session_destroy();
session_start();
flash_set('Logged out.');
header('Location: index.php');
exit;
?>