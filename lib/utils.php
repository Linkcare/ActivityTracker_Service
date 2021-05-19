<?php

/**
 * Returns true if the string $needle is found exactly at the begining of $haystack
 *
 * @param string $needle
 * @param string $haystack
 * @return boolean
 */
function startsWith($needle, $haystack) {
    return (strpos($haystack, $needle) === 0);
}

/**
 * Returns true if the $value passed is strictly equal to null or an empty string or a string composed only by spaces
 *
 * @param string $value
 */
function isNullOrEmpty($value) {
    return is_null($value) || trim($value) === "";
}

/**
 * Converts a string variable to boole
 * - return true if $text = ['y', 'yes', 'true', '1']
 * - return false otherwise
 *
 * @param string $value
 * @return bool
 */
function textToBool($text) {
    $text = trim(strtolower($text));
    $valNum = 0;
    if (is_numeric($text)) {
        $valNum = intval($text);
    }

    if (in_array($text, ['s', 'y', 'yes', 'true', '1']) || $valNum) {
        $boolValue = true;
    } else {
        $boolValue = false;
    }

    return $boolValue;
}

/**
 * Converts a value to a equivalent boolean string ('true' or 'false')
 *
 * @param string $value
 * @return string
 */
function boolToText($value) {
    return $value ? 'true' : 'false';
}

/**
 * Converts a expression to an integer if it is not null nor empty string.
 * Otherwise returns null
 *
 * @param mixed $value
 * @return NULL|number
 */
function NullableInt($value) {
    if (isNullOrEmpty($value)) {
        return null;
    }
    return intval($value);
}

/**
 * Converts a expression to an string if it is not null.
 * Otherwise returns null.
 * An zero-length string is considered NULL
 *
 * @param mixed $value
 * @return NULL|string
 */
function NullableString($value) {
    if ($value !== null) {
        $value = "" . $value;
    }
    if ($value === "") {
        $value = null;
    }
    return $value;
}

/**
 * Generate a trace on STDERR
 *
 * @param string $log
 * @param number $tabLevel
 */
function log_trace($log, $tabLevel = 0) {
    if (!$GLOBALS["DEBUG_LOG"]) {
        return;
    }
    $stackTrace = debug_backtrace();
    $function = $stackTrace[1]['function'];
    if ($stackTrace[1]['class']) {
        // If is a member of a class, add the class name
        $function = $stackTrace[1]['class'] . "::" . $function;
    }

    $line = $stackTrace[0]['line'];
    $depth = count($stackTrace) - 2;
    if ($depth < 0) {
        $depth = 0;
    }

    $maxLength = 15;

    $message[] = str_pad($function, $maxLength, " ", STR_PAD_RIGHT);
    $message[] = str_pad($line, 4, "0", STR_PAD_LEFT);
    $message[] = str_repeat(" ", 2 * ($tabLevel + $depth)) . $log;
    error_log('@' . implode(' ', $message));
}

/**
 * Generate a service log
 *
 * @param string $log_msg
 */
function service_log($log_msg) {
    if (!is_dir("logs/")) {
        mkdir("logs/");
    }

    if (is_dir("logs/")) {
        file_put_contents("logs/" . date("Y-m-d") . "-log.log", "-----Date:" . date("d-m-Y H:i:s") . " $log_msg\n", FILE_APPEND);
    }
}

/**
 * Sets the time zone based on the Operative System configuration
 */
function setSystemTimeZone() {
    $timezone = $GLOBALS["DEFAULT_TIMEZONE"];
    if (is_link('/etc/localtime')) {
        // Mac OS X (and older Linuxes)
        // /etc/localtime is a symlink to the
        // timezone in /usr/share/zoneinfo.
        $filename = readlink('/etc/localtime');
        if (strpos($filename, '/usr/share/zoneinfo/') === 0) {
            $timezone = substr($filename, 20);
        }
    } elseif (file_exists('/etc/timezone')) {
        // Ubuntu / Debian.
        $data = file_get_contents('/etc/timezone');
        if ($data) {
            $timezone = $data;
        }
    } elseif (file_exists('/etc/sysconfig/clock')) {
        // RHEL / CentOS
        $data = parse_ini_file('/etc/sysconfig/clock');
        if (!empty($data['ZONE'])) {
            $timezone = $data['ZONE'];
        }
    }
    date_default_timezone_set($timezone);
}

/**
 * Calculates the current date in the specified timezone
 *
 * @param number $timezone
 * @return string
 */
function currentDate($timezone = null) {
    $tz_object = new DateTimeZone('UTC');
    $datetime = new DateTime();
    $datetime->setTimezone($tz_object);
    $dateUTC = $datetime->format('Y\-m\-d\ H:i:s');

    if ($timezone === null) {
        return $dateUTC;
    }

    if (startsWith('UTC+', $timezone)) {
        $timezone = explode('UTC+', $timezone)[1];
    } elseif (startsWith('UTC-', $timezone)) {
        $timezone = -explode('UTC-', $timezone)[1];
    }

    if (is_numeric($timezone)) {
        // Some timezones are not an integer number of hours
        $timezone = intval($timezone * 60);
        $d = strtotime($dateUTC);
        $dateInTimezone = date('Y-m-d H:i:s', strtotime("$timezone minutes", $d));
    } else {
        try {
            $datetime = new DateTime($dateUTC);
            $tz_object = new DateTimeZone($timezone);
            $datetime->setTimezone($tz_object);
        } catch (Exception $e) {
            // If an invalid timezone has been provided, ignore it
            if (!$datetime) {
                $datetime = new DateTime();
            }
        }
        $dateInTimezone = $datetime->format('Y-m-d H:i:s');
    }
    return $dateInTimezone;
}

/**
 * Set the language of the website
 */
function setLanguage() {
    /* Initialize user language */
    if (!($lang = $_GET['culture'])) {
        $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
    }
    Localization::init($lang);
}

/**
 * Calculates the median value for an array of numbers
 *
 * @param float[] $arrValues
 * @return float
 */
function array_median($arrValues) {
    $res = 0;
    sort($arrValues, SORT_NUMERIC);
    $total = count($arrValues);
    if ($total % 2 == 1) {
        $pt1 = (int) (($total - 1) / 2);
        $res = $arrValues[$pt1];
    } else {
        $pt1 = $total / 2;
        $pt2 = $pt1 - 1;
        $res = ($arrValues[$pt1] + $arrValues[$pt2]) / 2;
    }

    return $res;
}

/**
 * Calculates the average value for an array of numbers
 *
 * @param float[] $arrValues
 * @return float
 */
function array_average($arrValues) {
    $res = 0;
    foreach ($arrValues as $y) {
        $res += $y;
    }
    $res = $res / count($arrValues);

    return $res;
}

