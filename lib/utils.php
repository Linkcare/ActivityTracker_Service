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
 * Returns the timezone offset respect to UTC of the current date.
 * The return value is expressed in hours, rounding to half hours.
 * If the date provided does not fall in a range of 12h around the UTC time, it is considered invalid and 0 will be returned
 *
 * @param string $date
 * @return number
 */
function timezoneOffset($timezone) {
    $datetime = new DateTime('now', new DateTimeZone('UTC'));
    $nowUTC = $datetime->format('Y-m-d H:i:s');

    if (strpos($timezone, 'UTC+') === 0) {
        $timezone = explode('UTC+', $timezone)[1];
    } elseif (strpos($timezone, 'UTC-') === 0) {
        $timezone = -explode('UTC-', $timezone)[1];
    }

    if (is_numeric($timezone)) {
        // Some timezones are not an integer number of hours
        $timezone = intval($timezone * 60);
        $d = strtotime($nowUTC);
        $dateInTimezone = date('Y-m-d H:i:s', strtotime("$timezone minutes", $d));
    } else {
        $datetime = new DateTime('now', new DateTimeZone($timezone));
        $dateInTimezone = $datetime->format('Y-m-d H:i:s');
    }

    // difference in hours (rounding to half hours):
    $interval = intval(strtotime($dateInTimezone) - strtotime($nowUTC));
    $sign = $interval < 0 ? -1 : 1;
    $interval = intval(($interval + 900 * $sign) / 1800) / 2;
    if ($interval > 12 || $interval < -11) {
        return 0;
    }
    return $interval;
}

/**
 * Calculates the current date in the specified timezone
 *
 * @param string|number $timezone
 * @return string
 */
function currentDate($timezone = null) {
    $tz_object = new DateTimeZone('UTC');
    $datetime = new DateTime();
    $datetime->setTimezone($tz_object);
    $dateUTC = $datetime->format('Y\-m\-d\ H:i:s');

    return dateInTimezone($dateUTC, $timezone);
}

/**
 * Applies a timezone shift to an UTC date
 * Timezone can be expressed as:
 * <ul>
 * <li>a numeric value expressing an offset in hours</li>
 * <li>a string representing a valid timezone (e.g. Europe/Madrid)</li>
 * </ul>
 *
 * @param string $dateUTC
 * @param number/string $timezone
 * @return string
 */
function dateInTimezone($dateUTC, $timezone = null) {
    if ($timezone === null) {
        $timezone = 0;
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
        if (!$d) {
            $d = strtotime(todayUTC());
        }
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
 * Returns a Unix timestamp (seconds since 1/1/1970 UTC) from a date expressed in a timezone
 *
 * @param string $localDate Local date in format 'yyyy-mm-dd hh:mm:ss'
 * @param string $timezone Timezone of the date
 * @return number
 */
function localDateToUnixTimestamp($localDate, $timezone) {
    // The dates must be a timestamp of a 13-digit integer, in milliseconds.
    $curTimeZone = date_default_timezone_get();
    date_default_timezone_set('UTC');
    $timezoneOffset = timezoneOffset($timezone) * 3600;

    $UTCTimestamp = strtotime($localDate) - $timezoneOffset;

    date_default_timezone_set($curTimeZone);

    return $UTCTimestamp;
}

/**
 * Converts a Unix timestamp (seconds since 1/1/1970 UTC) to a local date of a selected timezone
 *
 * @param number $timestamp
 * @param int|string $timezone
 * @param string $format (default = 'Y-m-d H:i:s')
 * @return string
 */
function UnixTimestampToLocalDate($timestamp, $timezone, $format = 'Y-m-d H:i:s') {
    $timezoneOffset = timezoneOffset($timezone) * 3600;

    $curTimeZone = date_default_timezone_get();
    date_default_timezone_set('UTC');
    $localDate = date($format, $timestamp + $timezoneOffset);
    date_default_timezone_set($curTimeZone);

    return $localDate;
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

/**
 * Implementation of mb_str_spli() for version of PHP < 7.4.0
 *
 * @param string $string
 * @param number $split_length
 * @param string $encoding
 * @return string
 */
function str_split_multibyte($string, $split_length = 1, $encoding = null) {
    if (null !== $string && !\is_scalar($string) && !(\is_object($string) && \method_exists($string, '__toString'))) {
        trigger_error('mb_str_split(): expects parameter 1 to be string, ' . \gettype($string) . ' given', E_USER_WARNING);
        return null;
    }
    if (null !== $split_length && !\is_bool($split_length) && !\is_numeric($split_length)) {
        trigger_error('mb_str_split(): expects parameter 2 to be int, ' . \gettype($split_length) . ' given', E_USER_WARNING);
        return null;
    }
    $split_length = (int) $split_length;
    if (1 > $split_length) {
        trigger_error('mb_str_split(): The length of each segment must be greater than zero', E_USER_WARNING);
        return false;
    }
    if (null === $encoding) {
        $encoding = mb_internal_encoding();
    } else {
        $encoding = (string) $encoding;
    }

    if (!in_array($encoding, mb_list_encodings(), true)) {
        static $aliases;
        if ($aliases === null) {
            $aliases = [];
            foreach (mb_list_encodings() as $encoding) {
                $encoding_aliases = mb_encoding_aliases($encoding);
                if ($encoding_aliases) {
                    foreach ($encoding_aliases as $alias) {
                        $aliases[] = $alias;
                    }
                }
            }
        }
        if (!in_array($encoding, $aliases, true)) {
            trigger_error('mb_str_split(): Unknown encoding "' . $encoding . '"', E_USER_WARNING);
            return null;
        }
    }

    $result = [];
    $length = mb_strlen($string, $encoding);
    for ($i = 0; $i < $length; $i += $split_length) {
        $result[] = mb_substr($string, $i, $split_length, $encoding);
    }
    return $result;
}

