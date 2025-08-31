<?php
require_once __DIR__ . "/../config/Session.php";

Session::start();
Session::destroy();

header("Location: index.php");
exit;
