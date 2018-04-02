<?php

class sqlite implements Countable
{
    /**
     * @var PDO
     */
    private $db = null;

    /**
     * @var string
     */
    private $name = null;
    
    /**
     * @var int
     */
    private $default_expire = 3600;

    public function __construct($name, $filename = "data.sqlite3", $expire = 3600)
    {
        $this->db = new PDO('sqlite:' . $filename);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->name = $name;
        $this->default_expire = $expire;
        $this->createTable();
    }

    /**
     * @param string $key key
     *
     * @throws InvalidArgumentException
     * @return array(int, array|string)|null
     */
    public function get($key)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException('Expected string as key');
        }

        $stmt = $this->db->prepare(
            'SELECT value, expire FROM ' . $this->name . ' WHERE key = :key;'
        );
        $stmt->bindParam(':key', $key, PDO::PARAM_STR);
        $stmt->execute();

        if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
            return array($row->expire, unserialize($row->value));
        }

        return array(0, null);
    }

    /**
     * @param string $key key
     * @param array|string $value value
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

        $queryString = 'REPLACE INTO ' . $this->name . ' VALUES (:key, :value, :expire);';
        $stmt = $this->db->prepare($queryString);
        $stmt->bindParam(':key', $key, \PDO::PARAM_STR);
        $stmt->bindParam(':value', serialize($value), \PDO::PARAM_STR);
        $stmt->bindParam(':expire', $expire_time, \PDO::PARAM_INT);
        $stmt->execute();

        return array(time(), $value);
    }

    /**
     * @param string $key key
     *
     * @return null
     */
    public function delete($key)
    {
        $stmt = $this->db->prepare(
            'DELETE FROM ' . $this->name . ' WHERE key = :key;'
        );
        $stmt->bindParam(':key', $key, \PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * Delete all values from store
     *
     * @return null
     */
    public function deleteAll()
    {
        $stmt = $this->db->prepare('DELETE FROM ' . $this->name);
        $stmt->execute();
    }

    /**
     * Clean up expired items
     *
     * @return null
     */
    public function clear()
    {
        $stmt = $this->db->prepare(
            'DELETE FROM ' . $this->name . ' WHERE expire < strftime(\'%s\',\'now\')'
        );
        $stmt->execute();
    }

    /**
     * @return int
     */
    public function count()
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM ' . $this->name)->fetchColumn();
    }

    /**
     * Create storage table in database if not exists
     *
     * @return null
     */
    private function createTable()
    {
        $stmt = 'CREATE TABLE IF NOT EXISTS `' . $this->name . '`';
        $stmt.= '(key TEXT PRIMARY KEY, value TEXT, expire INTEGER NOT NULL);';
        $this->db->exec($stmt);
    }
}
