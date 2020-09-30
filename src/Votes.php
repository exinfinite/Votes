<?php
namespace Exinfinite;
use phpFastCache\CacheManager;

class Votes {
    protected $limit = 3; //票數限制
    protected $duplicate = false; //是否允許重複
    protected $expire_after = "+1 day"; //多久有效
    function __construct($path = '', $options = []) {
        $opts = collect($options);
        $this->limit = $opts->get('limit', $this->limit);
        $this->duplicate = $opts->get('duplicate', $this->duplicate);
        $this->expire_after = $opts->get('expire_after', $this->expire_after);
        CacheManager::setDefaultConfig([
            "path" => $path,
        ]);
        $this->pool = CacheManager::getInstance('Sqlite');
        $this->exp = (new \DateTime(date('Y-m-d')))->modify($this->expire_after);
    }
    protected function getItem($key) {
        return $this->pool->getItem($key);
    }
    function isDuplicate($identity, $needle) {
        $item = $this->getItem($identity);
        if ($item->isHit()) {
            $data = $item->get();
            return is_array($data) && in_array($needle, $data);
        }
        return false;
    }
    function getDetail($identity) {
        $item = $this->getItem($identity);
        return $item->isHit() ?
        $item->get() :
        null;
    }
    function append($identity, $append = null) {
        $dft_data = [];
        if (is_null($append)) {
            return false;
        }
        try {
            $item = $this->getItem($identity);
            if (!$item->isHit()) {
                $item->set($dft_data)->expiresAt($this->exp);
            }
            $data = $item->get();
            if (is_array($data) && count($data) < $this->limit) {
                if ($this->duplicate || !$this->isDuplicate($identity, $append)) {
                    $item->append($append);
                    $this->pool->save($item);
                    return true;
                }
            }
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }
    function isReachLimits($identity) {
        if ($this->limit <= 0) {
            return false;
        }
        $item = $this->getItem($identity);
        if ($item->isHit()) {
            $data = $item->get();
            return is_array($data) && count($data) >= $this->limit;
        }
        return false;
    }
}
?>