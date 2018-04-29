<?php

namespace Mod;

/**
 * Console
 * functional usage for CLI mode
 *
 * @package Pagon
 */
class Console
{
    /**
     * @var array Colors
     */
    public static $COLORS = array(
        'reset' => 0,
        'bold' => 1,
        'italic' => 3,
        'underline' => 4,
        'blink' => 5,
        'inverse' => 7,
        'linethrough' => 9,
        'black' => 30,
        'red' => 31,
        'green' => 32,
        'yellow' => 33,
        'blue' => 34,
        'purple' => 35,
        'cyan' => 36,
        'white' => 37,
        'grey' => 90,
        'greybg' => '49;5;8',
        'blackbg' => 40,
        'redbg' => 41,
        'greenbg' => 42,
        'yellowbg' => 43,
        'bluebg' => 44,
        'purplebg' => 45,
        'cyanbg' => 46,
        'whitebg' => 47,
    );

    /**
     * Print the text
     *
     * @param string $text The text to print
     * @param string|array|boolean $color The color or options for display
     * @param bool|string $autoBr
     */
    public static function log($text, $color = null, $autoBr = true)
    {
        echo Console::text($text, $color, $autoBr);
    }

    /**
     * Done with something
     *
     * @param $text
     */
    public static function success($text)
    {
        self::log($text, 'green');
    }

    /**
     * Done with something
     *
     * @param $text
     */
    public static function warn($text)
    {
        self::log($text, 'yellow');
    }

    /**
     * Fail with something
     *
     * @param $text
     */
    public static function error($text)
    {
        self::log($text, 'red');
    }

    /**
     * Color output text for the CLI
     *
     * @param string $text The text to print
     * @param string|array|boolean $color The color or options for display
     * @param bool|string $autoBr
     * @return string
     * @example
     *
     *  text('Hello', 'red');               // "Hello" with red color
     *  text('Hello', true);                // "Hello" with PHP_EOL
     *  text('Hello', 'red', true)          // "Hello" with red color and PHP_EOL
     *  text('Hello', 'red', "\r")          // "Hello" with red color and "\r" line ending
     *  text('Hello', array('red', 'bold')) // "Hello" with red color and bold style
     *  text('<grey red-bg>H<reset>ello')   // "H" with grey color and red background, "ello" with normal color
     */
    public static function text($text, $color = null, $autoBr = false)
    {
        // Normal colors set
        if (is_string($color) || is_array($color)) {
            $options = array();
            if (is_string($color)) {
                // String match
                if (isset(self::$COLORS[$color])) $options[] = self::$COLORS[$color];
            } else if (is_array($color)) {
                // Loop array to match all colors
                foreach ($color as $value) {
                    if (!empty(self::$COLORS[$value])) {
                        $options[] = self::$COLORS[$value];
                    }
                }
            }
            $text = $options ? "\033[" . join(';', $options) . "m$text\033[0m" : $text;
        } else if (strpos($text, '<') !== false) {
            // Text template for colorize, Support "#{red}I'm red#{reset}"
            $text = preg_replace_callback('/<(\/?.*?)>/', function ($match) {
                if ($match[1]{0} == '/') return "\033[0m";
                $options = array();
                // Support {red bold}
                $values = explode(' ', $match[1]);
                foreach ($values as $value) {
                    $value = trim($value);
                    if (is_numeric($value)) {
                        $options[] = $value;
                    } else if (isset(Console::$COLORS[$value])) {
                        $options[] = Console::$COLORS[$value];
                    }
                }
                return $options ? "\033[" . join(';', $options) . "m" : $match[1];
            }, $text);
        }
        $color === true && $autoBr = $color;
        return $text . ($autoBr ? (is_bool($autoBr) ? PHP_EOL : $autoBr) : '');
    }

    /**
     * Prompt a message and get return
     *
     * @param string $text
     * @param bool|int $hide
     * @param int $retryOnEmpty
     * @throws \RuntimeException
     * @return string
     * @example
     *
     *  prompt('Your username: ')           // Display input
     *  prompt('Your password: ', true)     // Hide input
     *  prompt('Your password: ', true, 10) // Hide input and retry 3
     *  prompt('Your username: ', 5)        // Display input and retry 5
     */
    public static function prompt($text, $hide = false, $retryOnEmpty = 0)
    {
        $input = '';
        if (is_numeric($hide)) {
            $retryOnEmpty = $hide;
            $hide = false;
        }

        while (!$input && $retryOnEmpty > -1) {
            if (!$hide) {
                echo self::text($text);
                $input = trim(fgets(STDIN, 1024), "\n");
            } else {
                $command = "/usr/bin/env bash -c 'echo OK'";
                if (rtrim(shell_exec($command)) !== 'OK') {
                    throw new \RuntimeException("Can not invoke bash to input password!");
                }
                $command = "/usr/bin/env bash -c 'read -s -p \""
                    . self::text(addslashes($text))
                    . "\" input && echo \$input'";
                $input = rtrim(shell_exec($command));
                echo "\n";
            }
            $retryOnEmpty--;
        }
        return $input;
    }

    /**
     * Interactive mode
     *
     * @param string $title Prompt title
     * @param \Closure $callback Line callback
     * @param bool|\Closure $autoBr Auto br or completion function
     */
    public static function interactive($title, $callback, $autoBr = true)
    {
        if ($autoBr instanceof \Closure) readline_completion_function($autoBr);
        while (true) {
            $input = readline(self::text($title));
            if ($input === false) {
                exit(0);
            }
            if (strlen($input) == 0) continue;
            readline_add_history($input);
            $callback($input);
            if ($autoBr === true) echo PHP_EOL;
        }
    }

    /**
     * Confirm message
     *
     * @param string $text
     * @param bool $defaultYes
     * @param int $retry
     * @return bool
     * @example
     *
     *  confirm('Are you sure?', true)  // Will confirm with default "true" by empty input
     */
    public static function confirm($text, $defaultYes = false, $retry = 3)
    {
        print self::text((string)$text . ' [' . ($defaultYes ? 'Y/n' : 'y/N') . ']: ');
        $retry--;
        while (($input = trim(strtolower(fgets(STDIN, 1024)))) && !in_array($input, array('', 'y', 'n')) && $retry > 0) {
            echo PHP_EOL . 'Confirm: ';
            $retry--;
        }
        if ($retry == 0) die(PHP_EOL . 'Error input');
        $ret = $input === '' ? ($defaultYes === true ? true : false) : ($input === 'y' ? true : false);
        return $ret;
    }
}
