<?php
require_once __DIR__ . '/users/common/auth.php';

portal_logout();
// After logout, take the user to the public home page
portal_redirect('index.html');
