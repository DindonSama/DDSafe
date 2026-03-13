<?php

$auth->logout();
session_destroy();
header('Location: /login');
exit;
