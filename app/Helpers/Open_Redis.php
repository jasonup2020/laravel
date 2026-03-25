<?php

namespace App\Helpers;

use Redis;

/**
 * redis操作类
 * 说明，任何为false的串，存在redis中都是空串。
 * 只有在key不存在时，才会返回false。
 * 这点可用于防止缓存穿透
 * class Open_Redis 
 */
class Open_Redis {

    private $redis;
    protected $dbId = 0; //当前数据库ID号
    protected $auth; //当前权限认证码

    /**
     * 实例化的对象,单例模式.
     * @var
     */
    static private $_instance = array();
    private $k;
    //连接属性数组
    protected $attr = array(
        'timeout' => 30, //连接超时时间，redis配置文件中默认为300秒
        'db_id' => 0, //选择的数据库。
    );
    //什么时候重新建立连接
    protected $expireTime;
    protected $host;
    protected $port;

    public function __construct($config = [], $attr = array()) {
        $this->attr = array_merge($this->attr, $attr);

        $this->port = empty($config['port']) ? (getenv("REDIS_PORT") ? getenv("REDIS_PORT") : 6379) : $config['port'];
        $this->host = empty($config['host']) ? (getenv("REDIS_HOST") ? getenv("REDIS_HOST") : "127.0.0.1") : $config['host'];
        $this->attr['timeout'] = $timeout = empty($this->attr['timeout']) ? 1 : $this->attr['timeout'];
        $this->redis = new \Redis();
        $this->redis->connect($this->host, $this->port, $timeout);
        if (!empty($config['auth'])) {
            $this->auth($config['auth']);
            $this->auth = $config['auth'];
        } else {
            $this->auth = "";
        }
        $this->expireTime = time() + $timeout;
        if (!empty($this->attr["db_id"])) {
            $this->redis->select($this->attr["db_id"]); //设置Redis的第几个库
        }

        if (!empty(getenv("REDIS_DB"))) {
            $this->redis->select(getenv("REDIS_DB")); //设置Redis的第几个库
        }
    }

    /**
     * 得到实例化的对象.
     * 为每个数据库建立一个连接
     * 如果连接超时，将会重新建立一个连接
     * @param array $config
     * @param int $dbId
     * @return
     */
    public static function getInstance($config, $attr = array()) {
        //如果是一个字符串，将其认为是数据库的ID号。以简化写法。
        if (!is_array($attr)) {
            $dbId = $attr;
            $attr = array();
            $attr['db_id'] = $dbId;
        }
        $attr['db_id'] = $attr['db_id'] ? $attr['db_id'] : 0;
        $k = md5(implode('', $config) . $attr['db_id']);
        if (!(static::$_instance[$k] instanceof self)) {
            static::$_instance[$k] = new self($config, $attr);
            static::$_instance[$k]->k = $k;
            static::$_instance[$k]->dbId = $attr['db_id'];
            //如果不是0号库，选择一下数据库。
            if ($attr['db_id'] != 0) {
                static::$_instance[$k]->select($attr['db_id']);
            }
        } elseif (time() > static::$_instance[$k]->expireTime) {
            static::$_instance[$k]->close();
            static::$_instance[$k] = new self($config, $attr);
            static::$_instance[$k]->k = $k;
            static::$_instance[$k]->dbId = $attr['db_id'];
            //如果不是0号库，选择一下数据库。
            if ($attr['db_id'] != 0) {
                static::$_instance[$k]->select($attr['db_id']);
            }
        }
        return static::$_instance[$k];
    }

    private function __clone() {
        
    }

    /**
     * 执行原生的redis操作
     * @return \Redis
     */
    public function getRedis() {
        return $this->redis;
    }

    /*     * ***************hash表操作函数****************** */

    /**
     * 得到hash表中一个字段的值
     * @param string $key 缓存key
     * @param string $field 字段
     * @return string|false
     */
    public function hGet($key, $field) {
        return $this->redis->hGet($key, $field);
    }

    /**
     * 为hash表设定一个字段的值
     * @param string $key 缓存key
     * @param string $field 字段
     * @param string $value 值。
     * @return bool 
     */
    public function hSet($key, $field, $value) {
        return $this->redis->hSet($key, $field, $value);
    }

    /**
     * 判断hash表中，指定field是不是存在
     * @param string $key 缓存key
     * @param string $field 字段
     * @return bool
     */
    public function hExists($key, $field) {
        return $this->redis->hExists($key, $field);
    }

    /**
     * 删除hash表中指定字段 ,支持批量删除
     * @param string $key 缓存key
     * @param string $field 字段
     * @return int
     */
    public function hdel($key, $field) {
        $fieldArr = explode(',', $field);
        $delNum = 0;
        foreach ($fieldArr as $row) {
            $row = trim($row);
            $delNum += $this->redis->hDel($key, $row);
        }
        return $delNum;
    }

    /**
     * 返回hash表元素个数
     * @param string $key 缓存key
     * @return int|bool
     */
    public function hLen($key) {
        return $this->redis->hLen($key);
    }

    /**
     * 为hash表设定一个字段的值,如果字段存在，返回false
     * @param string $key 缓存key
     * @param string $field 字段
     * @param string $value 值。
     * @return bool
     */
    public function hSetNx($key, $field, $value) {
        return $this->redis->hSetNx($key, $field, $value);
    }

    /**
     * 为hash表多个字段设定值。
     * @param string $key
     * @param array $value
     * @return array|bool
     */
    public function hMset($key, $value) {
        if (!is_array($value))
            return false;
        return $this->redis->hMset($key, $value);
    }

    /**
     * 为hash表多个字段设定值。
     * @param string $key
     * @param array|string $value string以','号分隔字段
     * @return array|bool
     */
    public function hMget($key, $field) {
        if (!is_array($field))
            $field = explode(',', $field);
        return $this->redis->hMget($key, $field);
    }

    /**
     * 为hash表设这累加，可以负数
     * @param string $key
     * @param int $field
     * @param string $value
     * @return bool
     */
    public function hIncrBy($key, $field, $value) {
        $value = intval($value);
        return $this->redis->hIncrBy($key, $field, $value);
    }

    /**
     * 返回所有hash表的所有字段
     * @param string $key
     * @return array|bool
     */
    public function hKeys($key) {
        return $this->redis->hKeys($key);
    }

    /**
     * 返回所有hash表的字段值，为一个索引数组
     * @param string $key
     * @return array|bool
     */
    public function hVals($key) {
        return $this->redis->hVals($key);
    }

    /**
     * 返回所有hash表的字段值，为一个关联数组
     * @param string $key
     * @return array|bool
     */
    public function hGetAll($key) {
        return $this->redis->hGetAll($key);
    }

    /*     * *******************有序集合操作******************** */

    /**
     * 给当前集合添加一个元素
     * 如果value已经存在，会更新order的值。
     * @param string $key
     * @param string $order 序号
     * @param string $value 值
     * @return bool
     */
    public function zAdd($key, $order, $value) {
        return $this->redis->zAdd($key, $order, $value);
    }

    /**
     * 给$value成员的order值，增加$num,可以为负数
     * @param string $key
     * @param string $num 序号
     * @param string $value 值
     * @return string 返回新的order
     */
    public function zinCry($key, $num, $value) {
        return $this->redis->zinCry($key, $num, $value);
    }

    /**
     * 删除值为value的元素
     * @param string $key
     * @param string $value
     * @return bool
     */
    public function zRem($key, $value) {
        return $this->redis->zRem($key, $value);
    }

    /**
     * 集合以order递增排列后，0表示第一个元素，-1表示最后一个元素
     * @param string $key
     * @param int $start
     * @param int $end
     * @return array|bool
     */
    public function zRange($key, $start, $end) {
        return $this->redis->zRange($key, $start, $end);
    }

    /**
     * 集合以order递减排列后，0表示第一个元素，-1表示最后一个元素
     * @param string $key
     * @param int $start
     * @param int $end
     * @return array|bool
     */
    public function zRevRange($key, $start, $end) {
        return $this->redis->zRevRange($key, $start, $end);
    }

    /**
     * 集合以order递增排列后，返回指定order之间的元素。
     * min和max可以是-inf和+inf　表示最大值，最小值
     * @param string $key
     * @param int $start
     * @param int $end
     * @package array $option 参数
     *   withscores=>true，表示数组下标为Order值，默认返回索引数组
     *   limit=>array(0,1) 表示从0开始，取一条记录。
     * @return array|bool
     */
    public function zRangeByScore($key, $start = '-inf', $end = "+inf", $option = array()) {
        return $this->redis->zRangeByScore($key, $start, $end, $option);
    }

    /**
     * 集合以order递减排列后，返回指定order之间的元素。
     * min和max可以是-inf和+inf　表示最大值，最小值
     * @param string $key
     * @param int $start
     * @param int $end
     * @package array $option 参数
     *   withscores=>true，表示数组下标为Order值，默认返回索引数组
     *   limit=>array(0,1) 表示从0开始，取一条记录。
     * @return array|bool
     */
    public function zRevRangeByScore($key, $start = '-inf', $end = "+inf", $option = array()) {
        return $this->redis->zRevRangeByScore($key, $start, $end, $option);
    }

    /**
     * 返回order值在start end之间的数量
     * @param int|string $key
     * @param int|string $start
     * @param int|string $end
     */
    public function zCount($key, $start, $end) {
        return $this->redis->zCount($key, $start, $end);
    }

    /**
     * 返回值为value的order值
     * @param int|string $key
     * @param int|string $value
     */
    public function zScore($key, $value) {
        return $this->redis->zScore($key, $value);
    }

    /**
     * 返回集合以score递增加排序后，指定成员的排序号，从0开始。
     * @param int|string $key
     * @param int|string $value
     */
    public function zRank($key, $value) {
        return $this->redis->zRank($key, $value);
    }

    /**
     * 返回集合以score递增加排序后，指定成员的排序号，从0开始。
     * @param int|string $key
     * @param int|string $value
     */
    public function zRevRank($key, $value) {
        return $this->redis->zRevRank($key, $value);
    }

    /**
     * 删除集合中，score值在start end之间的元素　包括start end
     * min和max可以是-inf和+inf　表示最大值，最小值
     * @param int|string $key
     * @param int|string $start
     * @param int|string $end
     * @return 删除成员的数量。
     */
    public function zRemRangeByScore($key, $start, $end) {
        return $this->redis->zRemRangeByScore($key, $start, $end);
    }

    /**
     * 返回集合元素个数。
     * @param int|string $key
     */
    public function zCard($key) {
        return $this->redis->zCard($key);
    }

    /*     * *******************队列操作命令*********************** */

    /**
     * 在队列尾部插入一个元素
     * @param int|string $key
     * @param int|string $value
     * 返回队列长度
     */
    public function rPush($key, $value) {
        return $this->redis->rPush($key, $value);
    }

    /**
     * 在队列尾部插入一个元素 如果key不存在，什么也不做
     * @param int|string $key
     * @param int|string $value
     * 返回队列长度
     */
    public function rPushx($key, $value) {
        return $this->redis->rPushx($key, $value);
    }

    /**
     * 在队列头部插入一个元素
     * @param int|string $key
     * @param int|string $value
     * 返回队列长度
     */
    public function lPush($key, $value) {
        return $this->redis->lPush($key, $value);
    }

    /**
     * 在队列头插入一个元素 如果key不存在，什么也不做
     * @param int|string $key
     * @param int|string $value
     * 返回队列长度
     */
    public function lPushx($key, $value) {
        return $this->redis->lPushx($key, $value);
    }

    /**
     * 返回队列长度
     * @param int|string $key
     */
    public function lLen($key) {
        return $this->redis->lLen($key);
    }

    /**
     * 返回队列指定区间的元素
     * @param int|string $key
     * @param int|string $start
     * @param int|string $end
     */
    public function lRange($key, $start, $end) {
        return $this->redis->lrange($key, $start, $end);
    }

    /**
     * 返回队列中指定索引的元素
     * @param int|string $key
     * @param int|string $index
     */
    public function lIndex($key, $index) {
        return $this->redis->lIndex($key, $index);
    }

    /**
     * 设定队列中指定index的值。
     * @param int|string $key
     * @param int|string $index
     * @param int|string $value
     */
    public function lSet($key, $index, $value) {
        return $this->redis->lSet($key, $index, $value);
    }

    /**
     * 删除值为vaule的count个元素
     * PHP-REDIS扩展的数据顺序与命令的顺序不太一样，不知道是不是bug
     * count>0 从尾部开始
     * >0　从头部开始
     * =0　删除全部
     * @param int|string $key
     * @param int|string $count
     * @param int|string $value
     */
    public function lRem($key, $count, $value) {
        return $this->redis->lRem($key, $value, $count);
    }

    /**
     * 删除并返回队列中的头元素。
     * @param int|string $key
     */
    public function lPop($key) {
        return $this->redis->lPop($key);
    }

    /**
     * 删除并返回队列中的尾元素
     * @param int|string $key
     */
    public function rPop($key) {
        return $this->redis->rPop($key);
    }

    /*     * ***********redis字符串操作命令**************** */

    /**
     * 设置一个key
     * @param int|string $key
     * @param int|string $value
     */
    public function set($key, $value) {
        return $this->redis->set($key, $value);
    }

    /**
     * 得到一个key
     * @param int|string $key
     */
    public function get($key) {
        return $this->redis->get($key);
    }

    /**
     * 设置一个有过期时间的key
     * @param int|string $key
     * @param int|string $expire
     * @param int|string $value
     */
    public function setex($key, $expire, $value) {
        return $this->redis->setex($key, $expire, $value);
    }

    /**
     * 设置一个key,如果key存在,不做任何操作.
     * @param int|string $key
     * @param int|string $value
     */
    public function setnx($key, $value) {
        return $this->redis->setnx($key, $value);
    }

    /**
     * 批量设置key
     * @param array $arr
     */
    public function mset($arr) {
        return $this->redis->mset($arr);
    }

    /*     * ***********redis　无序集合操作命令**************** */

    /**
     * 返回集合中所有元素
     * @param int|string $key
     */
    public function sMembers($key) {
        return $this->redis->sMembers($key);
    }

    /**
     * 求2个集合的差集
     * @param int|string $key1
     * @param int|string $key2
     */
    public function sDiff($key1, $key2) {
        return $this->redis->sDiff($key1, $key2);
    }

    /**
     * 添加集合。由于版本问题，扩展不支持批量添加。这里做了封装
     * @param int|string $key
     * @param string|array $value
     */
    public function sAdd($key, $value) {
        if (!is_array($value))
            $arr = array($value);
        else
            $arr = $value;
        foreach ($arr as $row)
            $this->redis->sAdd($key, $row);
    }

    /**
     * 返回无序集合的元素个数
     * @param int|string $key
     */
    public function scard($key) {
        return $this->redis->scard($key);
    }

    /**
     * 从集合中删除一个元素
     * @param int|string $key
     * @param int|string $value
     */
    public function srem($key, $value) {
        return $this->redis->srem($key, $value);
    }

    /*     * ***********redis管理操作命令**************** */

    /**
     * 选择数据库
     * @param int $dbId 数据库ID号
     * @return bool
     */
    public function select($dbId) {
        $this->dbId = $dbId;
        return $this->redis->select($dbId);
    }

    /**
     * 清空当前数据库
     * @return bool
     */
    public function flushDB() {
        return $this->redis->flushDB();
    }

    /**
     * 返回当前库状态
     * @return array
     */
    public function info() {
        return $this->redis->info();
    }

    /**
     * 同步保存数据到磁盘
     */
    public function save() {
        return $this->redis->save();
    }

    /**
     * 异步保存数据到磁盘
     */
    public function bgSave() {
        return $this->redis->bgSave();
    }

    /**
     * 返回最后保存到磁盘的时间
     */
    public function lastSave() {
        return $this->redis->lastSave();
    }

    /**
     * 返回key,支持*多个字符，?一个字符
     * 只有*　表示全部
     * @param string $key
     * @return array
     */
    public function keys($key) {
        return $this->redis->keys($key);
    }

    /**
     * 删除指定key
     * @param int|string $key
     */
    public function del($key) {
        return $this->redis->del($key);
    }

    /**
     * 判断一个key值是不是存在
     * @param int|string $key
     */
    public function exists($key) {
        return $this->redis->exists($key);
    }

    /**
     * 为一个key设定过期时间 单位为秒
     * @param int|string $key
     * @param int|string $expire
     */
    public function expire($key, $expire) {
        return $this->redis->expire($key, $expire);
    }

    /**
     * 返回一个key还有多久过期，单位秒
     * @param int|string $key
     */
    public function ttl($key) {
        return $this->redis->ttl($key);
    }

    /**
     * 设定一个key什么时候过期，time为一个时间戳
     * @param int|string $key
     * @param int|string $time
     */
    public function exprieAt($key, $time) {
        return $this->redis->expireAt($key, $time);
    }

    /**
     * 关闭服务器链接
     */
    public function close() {
        return $this->redis->close();
    }

    /**
     * 关闭所有连接
     */
    public static function closeAll() {
        foreach (static::$_instance as $o) {
            if ($o instanceof self)
                $o->close();
        }
    }

    /** 这里不关闭连接，因为session写入会在所有对象销毁之后。
      public function __destruct()
      {
      return $this->redis->close();
      }
     * */

    /**
     * 返回当前数据库key数量
     */
    public function dbSize() {
        return $this->redis->dbSize();
    }

    /**
     * 返回一个随机key
     */
    public function randomKey() {
        return $this->redis->randomKey();
    }

    /**
     * 得到当前数据库ID
     * @return int
     */
    public function getDbId() {
        return $this->dbId;
    }

    /**
     * 返回当前密码
     */
    public function getAuth() {
        return $this->auth;
    }

    public function getHost() {
        return $this->host;
    }

    public function getPort() {
        return $this->port;
    }

    public function getConnInfo() {
        return array(
            'host' => $this->host,
            'port' => $this->port,
            'auth' => $this->auth
        );
    }

    /*     * *******************事务的相关方法*********************** */

    /**
     * 监控key,就是一个或多个key添加一个乐观锁
     * 在此期间如果key的值如果发生的改变，刚不能为key设定值
     * 可以重新取得Key的值。
     * @param int|string $key
     */
    public function watch($key) {
        return $this->redis->watch($key);
    }

    /**
     * 取消当前链接对所有key的watch
     * EXEC 命令或 DISCARD 命令先被执行了的话，那么就不需要再执行 UNWATCH 了
     */
    public function unwatch() {
        return $this->redis->unwatch();
    }

    /**
     * 开启一个事务
     * 事务的调用有两种模式Redis::MULTI和Redis::PIPELINE，
     * 默认是Redis::MULTI模式，
     * Redis::PIPELINE管道模式速度更快，但没有任何保证原子性有可能造成数据的丢失
     */
    public function multi($type = \Redis::MULTI) {
        return $this->redis->multi($type);
    }

    /**
     * 执行一个事务
     * 收到 EXEC 命令后进入事务执行，事务中任意命令执行失败，其余的命令依然被执行
     */
    public function exec() {
        return $this->redis->exec();
    }

    /**
     * 回滚一个事务
     */
    public function discard() {
        return $this->redis->discard();
    }

    /**
     * 测试当前链接是不是已经失效
     * 没有失效返回+PONG
     * 失效返回false
     */
    public function ping() {
        return $this->redis->ping();
    }

    public function auth($auth) {
        return $this->redis->auth($auth);
    }

    /**     * ******************自定义的方法,用于简化操作*********************** */

    /**
     * 得到一组的ID号
     * @param int|string $prefix
     * @param int|string $ids
     */
    public function hashAll($prefix, $ids) {
        if ($ids == false)
            return false;
        if (is_string($ids))
            $ids = explode(',', $ids);
        $arr = array();
        foreach ($ids as $id) {
            $key = $prefix . '.' . $id;
            $res = $this->hGetAll($key);
            if ($res != false)
                $arr[] = $res;
        }
        return $arr;
    }

    /**
     * 生成一条消息，放在redis数据库中。使用0号库。
     * @param string|array $msg
     */
    public function pushMessage($lkey, $msg) {
        if (is_array($msg)) {
            $msg = json_encode($msg);
        }
        $key = md5($msg);
        //如果消息已经存在，删除旧消息，已当前消息为准
        //echo $n=$this->lRem($lkey, 0, $key)."\n";
        //重新设置新消息
        $this->lPush($lkey, $key);
        $this->setex($key, 3600, $msg);
        return $key;
    }

    /**
     * 得到条批量删除key的命令
     * @param int|string $keys
     * @param int|string $dbId
     */
    public function delKeys($keys, $dbId) {
        $redisInfo = $this->getConnInfo();
        $cmdArr = array(
            'redis-cli',
            '-a',
            $redisInfo['auth'],
            '-h',
            $redisInfo['host'],
            '-p',
            $redisInfo['port'],
            '-n',
            $dbId,
        );
        $redisStr = implode(' ', $cmdArr);
        $cmd = "{$redisStr} KEYS \"{$keys}\" | xargs {$redisStr} del";
        return $cmd;
    }

    /**
     * 用列参考
     */
    private function dome_dome() {
        //https://blog.csdn.net/Gaby_JJ/article/details/78278418
        $array = $num = $int_num = $key_arr = $key_str = $key2 = $key3 = $score1 = $member1 = $scoreN = $memberN = $start = $stop = $min = $max = $config = [];
        /* 1.Connection */
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379, 1); //短链接，本地host，端口为6379，超过1秒放弃链接
        $redis->open('127.0.0.1', 6379, 1); //短链接(同上)
        $redis->pconnect('127.0.0.1', 6379, 1); //长链接，本地host，端口为6379，超过1秒放弃链接
        $redis->popen('127.0.0.1', 6379, 1); //长链接(同上)
        $redis->auth('password'); //登录验证密码，返回【true | false】
        $redis->select(0); //选择redis库,0~15 共16个库
        $redis->close(); //释放资源
        $redis->ping(); //检查是否还再链接,[+pong]
        $redis->ttl('key'); //查看失效时间[-1 | timestamps]
        $redis->persist('key'); //移除失效时间[ 1 | 0]
        $redis->sort('key', [$array]); //返回或保存给定列表、集合、有序集合key中经过排序的元素，$array为参数limit等！【配合$array很强大】 [array|false]

        /* 2.共性的运算归类 */
        $redis->expire('key', 10); //设置失效时间[true | false]
        $redis->move('key', 15); //把当前库中的key移动到15库中[0|1]
        //string
        $redis->strlen('key'); //获取当前key的长度
        $redis->append('key', 'string'); //把string追加到key现有的value中[追加后的个数]
        $redis->incr('key'); //自增1，如不存在key,赋值为1(只对整数有效,存储以10进制64位，redis中为str)[new_num | false]
        $redis->incrby('key', $num); //自增$num,不存在为赋值,值需为整数[new_num | false]
        $redis->decr('key'); //自减1，[new_num | false]
        $redis->decrby('key', $num); //自减$num，[ new_num | false]
        $redis->setex('key', 10, 'value'); //key=value，有效期为10秒[true]
        //list
        $redis->llen('key'); //返回列表key的长度,不存在key返回0， [ len | 0]
        //set
        $redis->scard('key'); //返回集合key的基数(集合中元素的数量)。[num | 0]
        $redis->sMove('key1', 'key2', 'member'); //移动，将member元素从key1集合移动到key2集合。[1 | 0]
        //Zset
        $redis->zcard('key'); //返回集合key的基数(集合中元素的数量)。[num | 0]
        $redis->zcount('key', 0, -1); //返回有序集key中，score值在min和max之间(默认包括score值等于min或max)的成员。[num | 0]
        //hash
        $redis->hexists('key', 'field'); //查看hash中是否存在field,[1 | 0]
        $redis->hincrby('key', 'field', $int_num); //为哈希表key中的域field的值加上量(+|-)num,[new_num | false]
        $redis->hlen('key'); //返回哈希表key中域的数量。[ num | 0]

        /* 3.Server */
        $redis->dbSize(); //返回当前库中的key的个数
        $redis->flushAll(); //清空整个redis[总true]
        $redis->flushDB(); //清空当前redis库[总true]
        $redis->save(); //同步??把数据存储到磁盘-dump.rdb[true]
        $redis->bgsave(); //异步？？把数据存储到磁盘-dump.rdb[true]
        $redis->info(); //查询当前redis的状态 [verson:2.4.5....]
        $redis->lastSave(); //上次存储时间key的时间[timestamp]
        $redis->watch('key', 'keyn'); //监视一个(或多个) key ，如果在事务执行之前这个(或这些) key 被其他命令所改动，那么事务将被打断 [true]
        $redis->unwatch('key', 'keyn'); //取消监视一个(或多个) key [true]
        $redis->multi(Redis::MULTI); //开启事务，事务块内的多条命令会按照先后顺序被放进一个队列当中，最后由 EXEC 命令在一个原子时间内执行。
        $redis->multi(Redis::PIPELINE); //开启管道，事务块内的多条命令会按照先后顺序被放进一个队列当中，最后由 EXEC 命令在一个原子时间内执行。
        $redis->exec(); //执行所有事务块内的命令，；【事务块内所有命令的返回值，按命令执行的先后顺序排列，当操作被打断时，返回空值 false】

        /* 4.String，键值对，创建更新同操作 */
        $redis->setOption(Redis::OPT_PREFIX, 'hf_'); //设置表前缀为hf_
        $redis->set('key', 1); //设置key=aa value=1 [true]
        $redis->mset($arr); //设置一个或多个键值[true]
        $redis->setnx('key', 'value'); //key=value,key存在返回false[|true]
        $redis->get('key'); //获取key [value]
        $redis->mget($arr); //(string|arr),返回所查询键的值
        $redis->del($key_arr); //(string|arr)删除key，支持数组批量删除【返回删除个数】
        $redis->delete($key_str, $key2, $key3); //删除keys,[del_num]
        $redis->getset('old_key', 'new_value'); //先获得key的值，然后重新赋值,[old_value | false]

        /* 5.List栈的结构,注意表头表尾,创建更新分开操作 */
        $redis->lpush('key', 'value'); //增，只能将一个值value插入到列表key的表头，不存在就创建 [列表的长度 |false]
        $redis->rpush('key', 'value'); //增，只能将一个值value插入到列表key的表尾 [列表的长度 |false]
        $redis->lInsert('key', Redis::AFTER, 'value', 'new_value'); //增，将值value插入到列表key当中，位于值value之前或之后。[new_len | false]
        $redis->lpushx('key', 'value'); //增，只能将一个值value插入到列表key的表头，不存在不创建 [列表的长度 |false]
        $redis->rpushx('key', 'value'); //增，只能将一个值value插入到列表key的表尾，不存在不创建 [列表的长度 |false]
        $redis->lpop('key'); //删，移除并返回列表key的头元素,[被删元素 | false]
        $redis->rpop('key'); //删，移除并返回列表key的尾元素,[被删元素 | false]
        $redis->lrem('key', 'value', 0); //删，根据参数count的值，移除列表中与参数value相等的元素count=(0|-n表头向尾|+n表尾向头移除n个value) [被移除的数量 | 0]
        $redis->ltrim('key', start, end); //删，列表修剪，保留(start,end)之间的值 [true|false]
        $redis->lset('key', index, 'new_v'); //改，从表头数，将列表key下标为第index的元素的值为new_v, [true | false]
        $redis->lindex('key', index); //查，返回列表key中，下标为index的元素[value|false]
        $redis->lrange('key', 0, -1); //查，(start,stop|0,-1)返回列表key中指定区间内的元素，区间以偏移量start和stop指定。[array|false]
        /* 6.Set，没有重复的member，创建更新同操作 */
        $redis->sadd('key', 'value1', 'value2', 'valuen'); //增，改，将一个或多个member元素加入到集合key当中，已经存在于集合的member元素将被忽略。[insert_num]
        $redis->srem('key', 'value1', 'value2', 'valuen'); //删，移除集合key中的一个或多个member元素，不存在的member元素会被忽略 [del_num | false]
        $redis->smembers('key'); //查，返回集合key中的所有成员 [array | '']
        $redis->sismember('key', 'member'); //判断member元素是否是集合key的成员 [1 | 0]
        $redis->spop('key'); //删，移除并返回集合中的一个随机元素 [member | false]
        $redis->srandmember('key'); //查，返回集合中的一个随机元素 [member | false]
        $redis->sinter('key1', 'key2', 'keyn'); //查，返回所有给定集合的交集 [array | false]
        $redis->sunion('key1', 'key2', 'keyn'); //查，返回所有给定集合的并集 [array | false]
        $redis->sdiff('key1', 'key2', 'keyn'); //查，返回所有给定集合的差集 [array | false]

        /* 7.Zset，没有重复的member，有排序顺序,创建更新同操作 */
        $redis->zAdd('key', $score1, $member1, $scoreN, $memberN); //增，改，将一个或多个member元素及其score值加入到有序集key当中。[num | 0]
        $redis->zrem('key', 'member1', 'membern'); //删，移除有序集key中的一个或多个成员，不存在的成员将被忽略。[del_num | 0]
        $redis->zscore('key', 'member'); //查,通过值反拿权 [num | null]
        $redis->zrange('key', $start, $stop); //查，通过(score从小到大)【排序名次范围】拿member值，返回有序集key中，【指定区间内】的成员 [array | null]
        $redis->zrevrange('key', $start, $stop); //查，通过(score从大到小)【排序名次范围】拿member值，返回有序集key中，【指定区间内】的成员 [array | null]
//        $redis->zrangebyscore('key',$min,$max["",$config]);//查，通过scroe权范围拿member值，返回有序集key中，指定区间内的(从小到大排)成员[array | null]
//        $redis->zrevrangebyscore('key',$max,$min["",$config]);//查，通过scroe权范围拿member值，返回有序集key中，指定区间内的(从大到小排)成员[array | null]
        $redis->zrank('key', 'member'); //查，通过member值查(score从小到大)排名结果中的【member排序名次】[order | null]
        $redis->zrevrank('key', 'member'); //查，通过member值查(score从大到小)排名结果中的【member排序名次】[order | null]
        $redis->ZINTERSTORE(); //交集
        $redis->ZUNIONSTORE(); //差集
        /* 8.Hash，表结构，创建更新同操作 */
        $redis->hset('key', 'field', 'value'); //增，改，将哈希表key中的域field的值设为value,不存在创建,存在就覆盖【1 | 0】
        $redis->hget('key', 'field'); //查，取值【value|false】
        $arr = array('one' => 1, 2, 3);
        $arr2 = array('one', 0, 1);
        $redis->hmset('key', $arr); //增，改，设置多值$arr为(索引|关联)数组,$arr[key]=field, [ true ]
        $redis->hmget('key', $arr2); //查，获取指定下标的field，[$arr | false]
        $redis->hgetall('key'); //查，返回哈希表key中的所有域和值。[当key不存在时，返回一个空表]
        $redis->hkeys('key'); //查，返回哈希表key中的所有域。[当key不存在时，返回一个空表]
        $redis->hvals('key'); //查，返回哈希表key中的所有值。[当key不存在时，返回一个空表]
        $redis->hdel('key', $arr2); //删，删除指定下标的field,不存在的域将被忽略,[num | false]
    }

    
}
