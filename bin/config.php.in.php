<?php

$config = \Tk\Config::getInstance();


// Remember to remove this in a live environment
$config['debug'] = false;


//$server= '{imap.gmail.com:993/imap/ssl}INBOX';
$config['server'] = '{imap.gmail.com:993/imap/ssl/novalidate-cert}INBOX';
$config['username'] = 'username@gmail.com';
$config['password'] = 'password';

