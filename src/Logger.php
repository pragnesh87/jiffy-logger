<?php

namespace Jiffy\Logger;

//use Psr\Log\LoggerInterface;
use DateTimeZone;
use DateTime;
use Psr\Log\AbstractLogger;
use Exception;

class Logger extends AbstractLogger
{
    protected string $name;
    protected DateTimeZone $timezone;
    public string $logDir = __DIR__;
    protected string $message_type;

    protected const OS_LOGGING = 0; // Operating System's system logging mechanism
    protected const EMAIL_LOGGING = 1; // Message is sent by email to the address
    protected const NO_LOGGING = 2; // No longer an option.
    protected const DESTFILE_LOGGING = 3; // Message is appended to the file destination.
    protected const SAPI_LOGGING = 4; // Message is sent directly to the SAPI logging handler.

    protected string $log_channel = 'daily'; //option daily/stacked
    protected array $allowed_log_channel = ['daily', 'stacked'];

    public function __construct(string $name, ?DateTimeZone $timezone = null, $msg_type = self::DESTFILE_LOGGING)
    {
        $this->name = $name;
        $this->timezone = $timezone ?: new DateTimeZone(date_default_timezone_get() ?: 'UTC');
        $this->message_type = $msg_type;
    }

    /**
     * Set Default LogChannel
     *
     * @param string $type
     * @return void
     */
    public function setLogChannel(string $type)
    {
        if (!in_array($type, $this->allowed_log_channel)) {
            throw new Exception("Invalid Channel Type, allowed daily or stacked");
        }
        $this->log_channel = $type;
    }

    /**
     * @inheritDoc
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
     * @param  string $message
     * @param  array $context
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

            $replace['{' . $key . '}'] = filter_var($val, FILTER_SANITIZE_STRING);
        }

        // interpolate replacement values into the message and return
        return strtr($message, $replace);
    }

    /**
     * Write Error Log
     *
     * @param  mixed $logLevel
     * @param  string $message
     * @return void
     */
    public function writeLog($logLevel, $message)
    {
        $date = new DateTime('now', $this->timezone);
        $logStr = '[' . $date->format('c') . '] ' . $this->name . '.' . strtoupper($logLevel) . ': ' . $message . PHP_EOL;

        if ($this->log_channel == 'daily') {
            $path = $this->logDir . '/' . date('Y-m-d') . '.log';
            error_log($logStr, $this->message_type, $path);
        } else {
            $path = $this->logDir . '/' . $this->name . '.log';
            error_log($logStr, $this->message_type, $path);
        }
    }
}
