<?php

/**
 * @file        Validation.php
 * @description
 *
 * PHP Version  5.6
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

define('ERROR_NO_ERROR', 0);

define('ERROR_INCORRECT_VALUE', -6);
define('ERROR_PARAMETER_REQUIRED', -61);
define('ERROR_DUPLICATION_VALUE', -62);
define('ERROR_RANGE_VALUE', -63);
define('ERROR_EMAIL_DOMAIN', -64);

class Validation
{
    private static $_fields;
    private static $_error;
    private static $_errors;
    private static $_success;
    private static $_states;

    /**
     * Функция для валидация параметра по шаблону.
     *
     * @param mixed $arg массив аргументов
     * @param string|array $rules массив правил для аргументов, одно или несколько
     * @param string $name имя ключа
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
     *      а значения равные 0, 0.0, "0" или array() не считаются пустыми, для проверки которых лучше использовать 'not_empty'
     * - 'pattern'
     *    работает с 'custom': шаблон для проверки @see preg_match
     * - 'check_mx'
     *    работает с типом 'email' проверяет домен на наличие MX записи в DNS.
     * @param null $callable
     * @return bool
     */

    public static function check($arg, $rules, $name = 'arg', $callable = null)
    {
        self::$_fields = array();
        self::$_errors = array();
        self::$_states = array();
        self::$_error = ERROR_NO_ERROR;
        self::$_success = false;

        if (is_array($arg) && !empty($arg)) {
            self::$_states = self::checkList($arg, $rules);
            if (in_array(false, self::$_states)) {
                self::$_success = false;
            } else {
                self::$_success = true;
            }

            return self::$_states;
        } else {
            self::$_success = self::_checkArg($arg, $rules, $name);
            self::$_states[$name] = self::$_success;

            return self::$_success;
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
        self::$_fields = array();
        self::$_errors = array();
        self::$_states = array();
        self::$_error = ERROR_NO_ERROR;
        self::$_success = false;
        foreach ($rules as $key => $itemRule) {
            if (!isset($arg[$key])) {
                $arg[$key] = null;
            }
            self::$_states[$key] = self::_checkArg($arg[$key], $itemRule, $key);
        }

        if (in_array(false, self::$_states)) {
            self::$_success = false;
        } else {
            self::$_success = true;
        }

        return self::$_states;
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
        $name = $key;
        $rules = (array)$rules;
        $min = isset($rules['min']) ? (float)$rules['min'] : null;
        $max = isset($rules['max']) ? (float)$rules['max'] : null;
        $pattern = isset($rules['pattern']) ? $rules['pattern'] : null;
        $default = isset($rules['default']) ? true : false;
        $required = false;
        $checkMX = false;
        $result = false;

        // Проверка флага 'default'
        if ($default && !self::isRequired($arg) && $key) {
            $arg = $rules['default'];
            self::$_fields[$key] = $rules['default'];
        } else {
            self::$_fields[$key] = $arg;
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
        if ($required && !self::isRequired($arg, $name)) {
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
                         $result = self::checkNumRange($arg, $min, $max, $name);
                     }
                     break;
                 case 'float':
                     if (preg_match('/\d+(\.\d+)?/', $arg)) {
                         $result = self::checkNumRange($arg, $min, $max, $name);
                     }
                     break;
                 case 'num':
                     if (is_numeric($arg)) {
                         $result = self::checkNumRange($arg, $min, $max, $name);
                     }
                     break;
                 case 'string':
                     if (is_string($arg)) {
                         $result = self::checkStrLen($arg, $min, $max, $name);
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

                     if ($checkMX == true && isset($arg)) {
                         $domain = substr($arg, strpos($arg, '@') + 1);
                         $result = self::checkMX($domain, $name);
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
                    $phonePattern = "/^((8|\+7)[\- ]?)?(\(?\d{3}\)?[\- ]?)?[\d\- ]{7,10}$/";
                     if ($data = preg_match_all($phonePattern, $arg)) {
                         var_dump('HERE');
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

        if ($result == false) {
            self::_setMainError(ERROR_INCORRECT_VALUE);
            //self::_error($name, ERROR_INCORRECT_VALUE, _("Incorrect value type."));
        }

        return $result;
    }

    /**
     * Вспомогательная функция для self::_checkArg()
     * проверяет $arg на отсутствие значение параметра (NULL или '').
     *
     * @param mixed $arg
     * @param string $name имя проверяемого значения, по умодчанию 'null', используется внутри валидатора
     * @return bool
     */
    public static function isRequired($arg, $name = null)
    {
        if (is_null($arg) || (is_string($arg) && strlen($arg) == 0)) {
            if ($name !== null) {
                self::_error($name, ERROR_PARAMETER_REQUIRED, _("Parameter required."));
                self::_setMainError(ERROR_PARAMETER_REQUIRED);
            }
            return false;
        }

        return true;
    }

    /**
     * Вспомогательная функция для self::_checkArg()
     * проверяет $arg на нахождение в указанном числовом диапозоне.
     *
     * @param mixed $arg
     * @param int|float|null $min установите в 'null' для пропуска проврки
     * @param int|float|null $max установите в 'null' для пропуска проврки
     * @param string $name имя проверяемого значения, по умодчанию 'null', используется внутри валидатора
     * @return bool
     */
    public static function checkNumRange($arg, $min, $max, $name = null)
    {
        $result = true;

        if ($min !== null && $arg < $min) {
            $result = false;
        }
        if ($max !== null && $arg > $max) {
            $result = false;
        }

        if (!$result && $name !== null) {
            self::_setMainError(ERROR_INCORRECT_VALUE);
            self::_error($name, ERROR_RANGE_VALUE, _("Range validation error."));
        }

        return $result;
    }

    /**
     * Вспомогательная функция для self::_checkArg()
     * проверяет длину строки $arg на нахождение в указанном числовом диапозоне.
     *
     * @param string $arg
     * @param int|float|null $min установите в 'null' для пропуска проврки
     * @param int|float|null $max установите в 'null' для пропуска проврки
     * @param string $name имя проверяемого значения, по умодчанию 'null', используется внутри валидатора
     * @return bool
     */
    public static function checkStrLen($arg, $min, $max, $name = null)
    {
        $length = strlen($arg);
        $result = true;

        if ($min !== null && $length < $min) {
            $result = false;
        }
        if ($max !== null && $length > $max) {
            $result = false;
        }

        if (!$result && $name !== null) {
            self::_setMainError(ERROR_INCORRECT_VALUE);
            self::_error($name, ERROR_RANGE_VALUE, _("Range validation error."));
        }

        return $result;
    }

    /**
     * Вспомогательная функция для self::_checkArg()
     * проверяет домен на наличие MX записи.
     *
     * @param $domain
     * @param string $name имя проверяемого значения, по умодчанию 'null', используется внутри валидатора
     * @return bool
     */
    public static function checkMX($domain, $name = null)
    {
        $result = (function_exists('checkdnsrr')) ? (bool)checkdnsrr($domain, 'MX') : false;

        if (!$result && $name !== null) {
            self::_setMainError(ERROR_INCORRECT_VALUE);
            self::_error($name, ERROR_EMAIL_DOMAIN, _("MX record not found for domain."));
        }

        return $result;
    }

    /**
     * Вспомогательная функция для записи ошибки валидации
     *
     * @see self::check()
     *
     * @param string $key имя параметра для валидации.
     * @param int $code код ошибки.
     * @param string $msg расшифровка кода ошибки.
     * @return bool
     */
    protected static function _error($key, $code, $msg)
    {
        self::$_errors[$key] = array(
            'error'   => $code,
            'message' => $msg
        );

        return false;
    }

    /**
     * Установка основной ошибки
     *
     * @param int $code Код ошибки
     */
    protected static function _setMainError($code)
    {
        if (self::$_error == ERROR_NO_ERROR) {
            self::$_error = $code;
        }
    }

    /**
     * Функция для получения списка ошибок
     *
     * @return mixed
     */
    public static function error()
    {
        return !empty(self::$_errors) ? self::$_errors : ERROR_NO_ERROR;
    }

    /**
     * Функция для получения состояния проверки.
     *
     * @return bool
     */
    public static function success()
    {
        return self::$_success;
    }

    /**
     * Функция для получения значений параметров после проверки.
     *
     * @return array
     */
    public static function fields()
    {
        return self::$_fields;
    }

    /**
     * Функция для генерации готового ответа сервера.
     *
     * @param string $msg сообщение в ответе по умолчанию 'API validation error.'.
     * @return array
     */
    public static function result($msg = 'API validation error.')
    {
        return array(
            'success' => self::success(),
            'error'   => self::$_error,
            'message' => $msg,
            'fields'  => self::$_states,
            'errors'  => self::error()
        );
    }
}
