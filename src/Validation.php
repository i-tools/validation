<?php

/**
 * @file        Validation.php
 * @description
 *
 * PHP Version  5.4.4
 *
 * @category
 * @plugin URI
 * @copyright   2015, Alex Krasnov. All Rights Reserved.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 * @author      Alex Krasnov <alex@i-tools.ru>
 * @link        http://www.i-tools.ru Author's homepage
 *
 * @created     12.09.15
 */
 
/**
 * ПРИМЕР ИСПОЛЬЗОВАНИЯ
 *
 * Validation::check($arg, 'int');
 * Validation::check($arg, array('int', 'min' => 1, 'max' => 100));
 *
 */

class Validation
{
    private static $_defaults = array();
    /**
     * Функция для валидация параметра по шаблону.
     *
     * @param mixed			$arg массив аргументов
     * @param string|array	$rules массив правил для аргументов, одно или несколько
     * правил для проверки:
     * [TYPES]
     * - 'int'
     * - 'float'
     * - 'num'
     * - 'string'
     * - 'not_empty'
     * - 'email'
     * - 'url'
     * - 'ipv4'
     * - 'ipv6'
     * - 'phone'
     * - 'json'
     * - 'custom'
     * - 'file'
     *    пока эксперементальный тип
     * [FLAGS]
     * - 'min'
     * - 'max'
     *    работает с 'int', 'float', 'num': min или max значение
     *    работает с 'string': min или max длина
     * - 'required'
     *    работает со всеми типами: проверяет на пустое значение. Пустым значением считается NULL или '',
     * 	  а значения равные 0, 0.0, "0" или array() не считаются пустыми, для проверки которых лучше использовать 'not_empty'
     * - 'pattern'
     *    работает с 'custom': шаблон для проверки @see preg_match
     * - 'check_mx'
     *    работает с типом 'email' проверяет домен на наличие MX записи в DNS.
     * @param callable		$callback анонимная функция для возврата параметров 'default'
     * @return bool
     */

    public static function check($arg, $rules, $callback = null)
    {
        if (is_array($arg)) {
            return self::checkList($arg, $rules, $callback);
        } else {
            return self::_checkArg($arg, $rules);
        }
    }

    /**
     * Функция для валидации массива параметров.
     *
     * @see self::check()
     *
     * @param mixed			$arg массив аргументов
     * @param string|array	$rules массив правил для аргументов
     * @param callable		$callback анонимная функция для возврата параметров 'default'
     * @return mixed
     */
    public static function checkList(array $arg, array $rules, $callback = null)
    {
        $result = array();
        self::$_defaults = array();

        foreach ($rules as $key => $itemRule) {
            $result[$key] = self::_checkArg($arg[$key], $itemRule, $key);
        }
        
        if (is_callable($callback)) {
            $callback(self::$_defaults);
        }

        return $result;
    }

    /**
     * Вспомогательная функция для self::check()
     * возвращает true если $arg проходит валидацию $rules;
     * в противном случае возвращается false
     *
     * @see self::check()
     *
     * @param $arg
     * @param $rules
     * @param null $key
     * @return bool
     */
    protected static function _checkArg($arg, $rules, $key = null)
    {
        $rules = (array)$rules;
        $min = isset($rules['min']) ? (float) $rules['min'] : null;
        $max = isset($rules['max']) ? (float) $rules['max'] : null;
        $pattern = isset($rules['pattern']) ? $rules['pattern'] : null;
        $default = isset($rules['default']) ? true : false;
        $required = false;
        $checkMX = false;
        $result = false;

        // Проверка флага 'default'
        if ($default && !self::isRequired($arg) && $key) {
            $arg = $rules['default'];
            self::$_defaults[$key] = $rules['default'];
        }
        if (isset($rules['default'])) {
            unset($rules['default']);
        }

        // Обработка флага 'required'
        $required_keys = array_keys($rules, 'required', true);

        if ($required_keys) {
            $required = true;
            foreach ($required_keys as $key) {
                unset($rules[$key]);
            }
        }
        unset($required_keys);

        // Обработка флага 'check_mx'
        $check_mx_keys = array_keys($rules, 'check_mx', true);

        if ($check_mx_keys) {
            $checkMX = true;
            foreach ($check_mx_keys as $key) {
                unset($rules[$key]);
            }
        }
        unset($check_mx_keys);

        // Проверка на необходимость значения
        if ($required && !self::isRequired($arg)) {
            return false;
        }

        // Удаляем вспомогательные флаги
        if (isset($rules['min'])) {
            unset($rules['min']);
        }
        if (isset($rules['max'])) {
            unset($rules['max']);
        }
        if (isset($rules['pattern'])) {
            unset($rules['pattern']);
        }

        // Пробегаем по массиву типов
        foreach ($rules as $rule) {
            switch (strtolower($rule)) {
                 case 'int':
                     if (preg_match('/^\+?\d+$/', $arg)) {
                         $result = self::checkNumRange($arg, $min, $max);
                     }
                     break;
                 case 'float':
                     if (preg_match('/\d+(\.\d+)?/', $arg)) {
                         $result = self::checkNumRange($arg, $min, $max);
                     }
                     break;
                 case 'num':
                     if (is_numeric($arg)) {
                         $result = self::checkNumRange($arg, $min, $max);
                     }
                     break;
                 case 'string':
                     if (is_string($arg)) {
                         $result = self::checkStrLen($arg, $min, $max);
                     }
                     break;
                 case 'not_empty':
                     if (!empty($arg)) {
                         return true;
                     }
                     break;
                 case 'email':
                     // Валидация сделана на regex т.к. на момент написания стандартная проверка не работала с кириллическими доменами
                    $emailPattern = '/(^[а-яА-ЯёЁa-zA-Z0-9_\.-]{1,}@([а-яА-ЯёЁa-zA-Z0-9_-]{1,}\.){1,}[а-яА-ЯёЁa-zA-Z0-9_-]{2,}$)/iu';
                     if (preg_match($emailPattern, $arg)) {
                         $result = true;
                     }

                     if (isset($checkMX)) {
                         $domain = $domain = substr($arg, strpos($arg, '@') + 1);
                         $result = self::checkMX($domain);
                     }
                     break;
                 case 'url':
                    // Валидация сделана на regex т.к. на момент написания стандартная проверка не работала с кириллическими доменами
                    $urlPattern = '/\b(?:(?:https?|ftps?|sftp):\/\/|www\.)[-а-яА-ЯёЁa-zA-Z0-9+&@#\/%?=~_|!:,.;]*[-а-яА-ЯёЁa-zA-Z0-9+&@#\/%=~_|]/iu';
                     if (preg_match($urlPattern, $arg)) {
                         $result = true;
                     }
                    break;
                 case 'ipv4':
                     $ipv4Pattern = '/^((\d|[1-9]d|2[0-4]\d|25[0-5]|1\d\d)(?:\.(\d|[1-9]\d|2[0-4]\d|25[0-5]|1\d\d)){3})$/';
                     if (preg_match($ipv4Pattern, $arg)) {
                         $result = true;
                     }
                     break;
                 case 'ipv6':
                    $ipv6Pattern = '/^(((?=(?>.*?(::))(?!.+)))?|([\dA-F]{1,4}(|:(?!$)|$)|))(?4){5}((?4){2}|((2[0-4]|1\d|[1-9])?\d|25[0-5])(\.(?7)){3})\z/i';
                     if (preg_match($ipv6Pattern, $arg)) {
                         $result = true;
                     }
                     break;
                 case 'phone':
                    //@todo Проверка не только российского кода страны
                    $phonePattern = "/^\s*(?:\+?(\d{1,3}))?([-. (]*(\d{3})[-. )]*)?((\d{3})[-. ]*(\d{2,4})(?:[-.x ]*(\d+))?)\s*$/";
                     if ($data = preg_match_all($phonePattern, $arg)) {
                         $result = true;
                     }
                    break;
                case 'json':
                    $result = is_string($arg) && is_array(json_decode($arg, true)) && (json_last_error() == JSON_ERROR_NONE) ? true : false;
                    break;
                 case 'custom':
                     if (preg_match($pattern, $arg)) {
                         return true;
                     }
                     break;
                 case 'file':
                     if ($arg['error'] == UPLOAD_ERR_OK) {
                         $result = true;
                     }
                     break;
                default:
                     $result = false;
             }
        }

        return $result;
    }

    /**
     * Вспомогательная функция для self::_checkArg()
     * проверяет $arg на отсутствие данных (NULL или '').
     *
     * @param  mixed          $arg
     * @return bool
     */
    public static function isRequired($arg)
    {
        return (is_null($arg) || (is_string($arg) && strlen($arg) == 0)) ? false : true;
    }
     
    /**
     * Вспомогательная функция для self::_checkArg()
     * проверяет $arg на нахождение в указанном числовом диапозоне.
     *
     * @param  mixed          $arg
     * @param  int|float|null $min установите в 'null' для пропуска проврки
     * @param  int|float|null $max установите в 'null' для пропуска проврки
     * @return bool
     */
    public static function checkNumRange($arg, $min, $max)
    {
        if ($min !== null && $arg < $min) {
            return false;
        }
        if ($max !== null && $arg > $max) {
            return false;
        }

        return true;
    }
    
    /**
     * Вспомогательная функция для self::_checkArg()
     * проверяет длину строки $arg на нахождение в указанном числовом диапозоне.
     *
     * @param  string         $arg
     * @param  int|float|null $min установите в 'null' для пропуска проврки
     * @param  int|float|null $max установите в 'null' для пропуска проврки
     * @return bool
     */
    public static function checkStrLen($arg, $min, $max)
    {
        $length = strlen($arg);

        if ($min !== null && $length < $min) {
            return false;
        }
        if ($max !== null && $length > $max) {
            return false;
        }

        return true;
    }

    /**
     * Вспомогательная функция для self::_checkArg()
     * проверяет домен на наличие MX записи.
     *
     * @param $domain
     * @return bool
     */
    public static function checkMX($domain)
    {
        return (function_exists('checkdnsrr')) ? (bool)checkdnsrr($domain, 'MX') : false;
    }
}