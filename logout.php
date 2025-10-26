<?php
require_once __DIR__ . '/users/common/auth.php';

portal_logout();
portal_redirect('login.php');
