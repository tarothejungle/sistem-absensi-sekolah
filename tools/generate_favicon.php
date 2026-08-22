<?php

$png = file_get_contents(__DIR__.'/../public/icons/icon-192.png');
if ($png === false) {
    throw new RuntimeException('PWA icon is missing.');
}

$ico = pack('vvv', 0, 1, 1)
    .pack('CCCCvvVV', 192, 192, 0, 0, 1, 32, strlen($png), 22)
    .$png;

file_put_contents(__DIR__.'/../public/favicon.ico', $ico);
