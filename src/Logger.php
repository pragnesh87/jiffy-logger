<?php

namespace Jiffy\Logger;

//use Psr\Log\LoggerInterface;
use DateTimeZone;
use DateTime;
use Psr\Log\AbstractLogger;


class Logger extends AbstractLogger
{
    protected string $name;
    protected DateTimeZone $timezone;
    protected string $logDir = __DIR__;
    protected string $message_type;

    protected const OS_LOGGING = 0; // Operating System's system logging mechanism
    protected const EMAIL_LOGGING = 1; // Message is sent by email to the address
    protected const NO_LOGGING = 2; // No longer an option.
    protected const DESTFILE_LOGGING = 3; // Message is appended to the file destination.
    protected const SAPI_LOGGING = 4; // Message is sent directly to the SAPI logging handler.

    protected string $log_channel = 'daily'; //option daily/stacked

    public function __construct(string $name, ?DateTimeZone $timezone = null, $msg_type = self::DESTFILE_LOGGING)
    {
        $this->name = $name;
        $this->timezone = $timezone ?: new DateTimeZone(date_default_timezone_get() ?: 'UTC');
        $this->message_type = $msg_type;
    }

    /**
     * Set Log Channel
     * 
     * @param stirng $type
     * @return void
     */
    public function setLogChannel(string $type)
    {
        $this->log_channel = $type;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     * @return void
     */
    public function log($level, $message, array $context = array())
    {
        // Parse Placeholder
        $message = $this->interpolate($message, $context);

        if (!is_string($message)) {
            $message = print_r($message, true);
        }

        $this->writeLog($level, $message);
    }

    /**
     * interpolate
     *
     * @param  mixed $message
     * @param  mixed $context
     * @return void
     */
    protected function interpolate($message, array $context = [])
    {
        if (!is_string($message)) {
            return $message;
        }

        // build a replacement array with braces around the context keys
        $replace = [];

        foreach ($context as $key => $val) {
            // Verify that the 'exception' key is actually an exception
            // or error, both of which implement the 'Throwable' interface.
            if ($key === 'exception' && $val instanceof \Throwable) {
                $val = $val->getMessage() . ' ' . $val->getFile() . ':' . $val->getLine();
            }

            // todo - sanitize input before writing to file?
            $replace['{' . $key . '}'] = $val;
        }

        // Add special placeholders
        $replace['{post_vars}'] = '$_POST: ' . print_r($_POST, true);
        $replace['{get_vars}']  = '$_GET: ' . print_r($_GET, true);

        if (isset($_SESSION)) {
            $replace['{session_vars}'] = '$_SESSION: ' . print_r($_SESSION, true);
        }

        // interpolate replacement values into the message and return
        return strtr($message, $replace);
    }

    public function writeLog($logLevel, $message)
    {
        $date = new DateTime('now', $this->timezone);
        $logStr = '[' . $date->format('c') . '] ' . $this->name . '.' . strtoupper($logLevel) . ': ' . $message . PHP_EOL;

        if ($this->log_channel == 'daily') {
            $path = $this->logDir . '/' . date('Y-m-d') . '.log';
            error_log($logStr, $this->message_type, $path);
        } else {
            $path = $this->logDir . '/' . 'logs.log';
            error_log($logStr, $this->message_type, $path);
        }
    }
}
