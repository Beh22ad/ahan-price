<?php
namespace AhanPrice\Admin;

class DateConverter
{
    /**
     * Convert numbers between English and Persian.
     *
     * @param string $str The input string containing numbers.
     * @param string $mod The mode of conversion ('en' for English, 'fa' for Persian).
     * @param string $mf The character to use as the decimal point in Persian.
     * @return string The converted string.
     */
    public static function trNum($str, $mod = 'en', $mf = '٫')
    {
        $num_a = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '.');
        $key_a = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', $mf);
        return ($mod == 'fa') ? str_replace($num_a, $key_a, $str) : str_replace($key_a, $num_a, $str);
    }

    /**
     * Convert a Gregorian date to a Jalali (Persian) date.
     *
     * @param int $gy Gregorian year.
     * @param int $gm Gregorian month.
     * @param int $gd Gregorian day.
     * @param string $mod The mode of output ('' for array, 'string' for formatted string).
     * @return array|string The Jalali date as an array or formatted string.
     */
    public static function gregorianToJalali($gy, $gm, $gd, $mod = '')
    {
        list($gy, $gm, $gd) = explode('_', self::trNum($gy . '_' . $gm . '_' . $gd)); // Convert numbers if necessary
        $g_d_m = array(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334);
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];
        $jy = -1595 + (33 * ((int)($days / 12053)));
        $days %= 12053;
        $jy += 4 * ((int)($days / 1461));
        $days %= 1461;
        if ($days > 365) {
            $jy += (int)(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + (int)($days / 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + (int)(($days - 186) / 30);
            $jd = 1 + (($days - 186) % 30);
        }
        return ($mod == '') ? array($jy, $jm, $jd) : $jy . $mod . $jm . $mod . $jd;
    }
}