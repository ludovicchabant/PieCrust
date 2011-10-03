#!/usr/bin/php
<?php

require_once 'piecrust.php';
piecrust_setup('chef');

$chef = new PieCrust\Chef\Chef();
$chef->run();
