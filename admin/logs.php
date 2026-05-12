<?php
require_once '../includes/auth.php';
checkAccess(['super_admin']);
// Redirect to the proper logs page with super admin context
header("Location: ../pages/logs.php");
exit();
