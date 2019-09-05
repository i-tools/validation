<?php

error_reporting(E_ALL);

setlocale(LC_ALL, "ru_RU.UTF-8");

function my_print_r($what)
{
    print "<pre>";
    print_r($what);
    print "</pre>";
}

require_once('../src/Validation.php');

Validation::check(null, array('email', 'required'), 'email');
$msg = Validation::success() ? "Good" : null;
my_print_r(
    Validation::result($msg)
);

Validation::check(
    array(
        'name' => 'may_name',
        'created' => 34234234,
        'mail' => 'dsfdfd@mail.ru',
        'phone' => '+7 (3452) 555-555',
        'domain' => 'www.crtweb.ru',
        'url' => 'https://www.crtweb.ru/contact/'
    ),
    array(
        'name'     => array('string', 'min' => 5, 'max' => 20),
        'created'  => array('int', 'required'),
        'mail'     => array('email', 'check_mx'),
        'phone'    => 'phone',
        'domain' => array('domain', 'required'),
        'url' => array('url', 'required')
    )
);

my_print_r(
    Validation::result()
);
