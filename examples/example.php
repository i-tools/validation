<?php

error_reporting(E_ALL);

setlocale (LC_ALL, "ru_RU.UTF-8");

function my_print_r($what)
{
        print "<pre>";
        print_r($what);
        print "</pre>";
}

require_once('../src/Validation.php');

$params = array(
	'foo' => 10.4,
	'bar' => 'itools',
	'baz' => NULL,
	'int' => NULL,
	'int2' => NULL,
);

$result = Validation::check($params, array(
        'foo' => 'float',
        'baz' => array('int', 'min' => 2, 'required', 'default' => 2),
        'bar' => array('string', 'min' => 0, 'required', 'max' => 12, 'default' => 'itools', 'required'),
        'int' => array('int', 'required'),
        'int2' => array('int', 'default' => 1000),
	),
	function($defaults) use (&$params) {
		$params = array_replace($params, $defaults);
	}
);

my_print_r($params);

if ( in_array(false, $result) ) {
	my_print_r(array(
		'success' => false,
		'fields' => $result
	));
}

// CHeck email
$params = array(
    'email' => 'alex@i-tools.ru'
);

    /** @var array $result */
    $result = Validation::check($params, array(
    'email' => array('email', 'check_mx')
),
    function($defaults) use (&$params) {
        $params = array_replace($params, $defaults);
    }
);


if ( in_array(false, $result) ) {
    my_print_r(array(
        'success' => false,
        'fields' => $result
    ));
}

$params = array(
	'int' => 0,
	'login' => NULL
);

$result = Validation::check($params, array(
	'int' => array('int', 'min' => 2, 'required', 'default' => 2),
	'login' => array('string', 'min' => 1, 'max' => 12, 'required', 'default' => 'itools', 'required')
	),
	function($defaults) use (&$params) {
		$params = array_replace($params, $defaults);
	}
);

my_print_r($params);

if ( in_array(false, $result) ) {
	my_print_r(array(
		'success' => false,
		'fields' => $result
	));
}
