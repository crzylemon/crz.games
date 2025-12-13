<?php
require_once 'session.php';

destroySession();
header('Location: /');
exit();
?>