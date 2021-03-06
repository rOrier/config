<?php

namespace ROrier\Config\Components;

use ROrier\Config\Tools\CollectionTool;
use ArrayAccess;
use Exception;

/**
 * Class Bag
 */
class Bag implements ArrayAccess
{
    const REGEX_SYMLINK = '/^=(?<key>[a-z-A-Z0-9_-]+(\.[a-z-A-Z0-9_-]*)*)$/';

    private array $data = array();

    private array $reference = array();

    private ?string $separator = '.';

    public function __construct(array $data = array(), ?array $reference = null)
    {
        $this->data = $data;
        $this->reference = $reference ?? $data;
    }

    /**
     * @param string|null $separator
     */
    public function setSeparator(?string $separator): void
    {
        $this->separator = $separator;
    }

    public function toArray() : array
    {
        return $this->expand();
    }

    /**
     * @param string|bool $var
     * @return array|bool|mixed|Bag|null
     */
    public function copy($var = false)
    {
        $data = $this->searchData($var);

        return is_array($data) ? new self($data, $this->reference) : $data;
    }

    public function extract($var = false)
    {
        $data = $this->searchData($var);

        return !empty($data) ? $this->expand($data) : null;
    }

    public function merge(array $data)
    {
        CollectionTool::merge($this->data, $data);
    }

    private function searchData($key = false, &$data = false)
    {
        if (!$data) {
            $data =& $this->data;
        }

        if ($key === false) {
            return $data;
        }

        $path = explode($this->separator, $key);
        $key = array_shift($path);
        $next = implode($this->separator, $path);

        if (is_array($data) and isset($data[$key])) {
            if (is_string($data[$key]) && preg_match(self::REGEX_SYMLINK, $data[$key], $matches)) {
                $next = $matches['key'] . (!empty($next) ? $this->separator . $next : null);
                unset($data);
                $data =& $this->reference;
            } else {
                $data =& $data[$key];
            }
        } else {
            return null;
        }

        return empty($next) ? $data : $this->searchData($next, $data);
    }

    private function expand(&$data = false)
    {
        $extracted = null;

        if (!$data) {
            $data =& $this->data;
        }

        if (is_array($data)) {
            $extracted = [];

            foreach ($data as $key => $val) {
                if (is_string($val) && preg_match(self::REGEX_SYMLINK, $val, $matches)) {
                    $extracted[$key] = $this->expand($this->searchData($matches['key']));
                } elseif (is_array($val)) {
                    $extracted[$key] = $this->expand($val);
                } else {
                    $extracted[$key] = $val;
                }
            }
        } else {
            $extracted = $data;
        }

        return $extracted;
    }

    // ###################################################################
    // ###       sous-fonctions d'acc??s par tableau
    // ###################################################################

    /**
     * @param mixed $var
     * @param mixed $value
     * @throws Exception
     */
    public function offsetSet($var, $value)
    {
        throw new Exception("Write access forbidden.");
    }

    /**
     * @param mixed $var
     * @return bool
     */
    public function offsetExists($var)
    {
        return ($this->searchData($var) !== null);
    }

    /**
     * @param mixed $var
     * @throws Exception
     */
    public function offsetUnset($var)
    {
        throw new Exception("Write access forbidden.");
    }

    /**
     * @param mixed $var
     * @return array|bool|mixed|null
     */
    public function offsetGet($var)
    {
        return $this->extract($var);
    }
}
