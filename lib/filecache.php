<?php

class filecache
{
    /**
     * @var string
     */
    private $root = './';

    /**
     * @var int
     */
    private $default_expire = 3600;

    public function __construct($root = "cache/", $expire = 3600)
    {
        if(!is_dir($root)) {
            mkdir($root, 0750, true);
        }
        $this->root = $root;
        $this->default_expire = $expire;
    }

    /**
     * @param string $key key
     *
     * @throws InvalidArgumentException
     * @return array(int, object) expire time, value
     */
    public function get($key)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException('Expected string as key');
        }
        $file = $this->root . md5($key) . '.php';
        if (!file_exists($file)) {
            return array(0, null);
        }
        $cache = @include $file;
        return (array)$cache;
    }

    /**
     * @param string $key key
     * @param object $value value
     * @param int expire time in unix timestamp
     *
     * @throws InvalidArgumentException
     */
    public function set($key, $value, $expire_time = -1)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException('Expected string as key');
        }
        if (!is_int($expire_time) || $expire_time <= 0) {
            $expire_time = time() + $this->default_expire;
        }
        $file = $this->root . md5($key) . '.php';
        file_put_contents($file, "<?php return " . var_export(array($expire_time, $value), true) . ";", LOCK_EX);
		return array($expire_time, $value);
    }

    /**
     * @param string $key key
     *
     * @return null
     */
    public function delete($key)
    {
        $file = CACHE_PATH . md5($key) . '.php';
        @unlink($file);
    }

    /**
     * Delete all values from store
     *
     * @return null
     */
    public function deleteAll()
    {
        while ($file=readdir($this->root)) {
            if (pathinfo($file, PATHINFO_EXTENSION) != 'php') {
                continue;
            }
			@unlink($this->root.$file);
		}
    }

    /**
     * Clean up expired items
     *
     * @return null
     */
    public function clear()
    {
        if ($expire_time <= 0) return $this->deleteAll();
        while ($file=readdir($this->root)) {
            if (pathinfo($file, PATHINFO_EXTENSION) != 'php') {
                continue;
            }
            list($time, $value) = @include $this->root.$file;
            if (time() > $time) {
                @unlink($this->root.$file);
            }
		}
    }
}
