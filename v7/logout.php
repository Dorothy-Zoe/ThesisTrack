<?php
session_start();
session_destroy();
header('Location: portal.php?success=logout');
exit();
?>
