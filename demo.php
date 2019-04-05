<?php

error_reporting(E_ALL);

setlocale (LC_ALL, "ru_RU.UTF-8");

function my_print_r($what)
{
        print "<pre>";
        print_r($what);
        print "</pre>";
}

require_once('./Validation.php');

$params = array(
	'foo' => 10.4,
	'bar' => 'itools',
	'baz' => NULL,
	'int' => NULL,
	'int2' => NULL
);

$result = Validation::check($params, array(
	'foo' => 'float',
	'baz' => array('int', 'min' => 2, 'requared', 'default' => 2),
	'bar' => array('string', 'min' => 0, 'requared', 'max' => 12, 'default' => 'itools', 'requared'),
	'int' => array('int', 'required'),
	'int2' => array('int', 'default' => 1000)
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

$params = array(
	'int' => 0,
	'login' => NULL
);

$result = Validation::check($params, array(
	'int' => array('int', 'min' => 2, 'requared', 'default' => 2),
	'login' => array('string', 'min' => 1, 'max' => 12, 'requared', 'default' => 'itools', 'requared')
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

/*$nickname = "";

function test($name, $callback = NULL) {
	if ( is_callable($callback) ) {
		$callback($name);
	}
}

test("ITOOLS 2", function($value) use (&$nickname) {
	$nickname = $value;
});

echo $nickname;*/


?>