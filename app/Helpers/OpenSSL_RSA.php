<?php

namespace App\Helpers;

/**
 * @param type //密钥长度 1024,2048,4096
 * @param type //密钥格式 PKCS#8
 * @param type //私钥密码 R356#pq
 * @param type //生成后的密文里的   [+/=]分别替换成 [-_ ] , 加密时拆成117位，解密时拆成128位
 * @abstract  publicEncrypt(公钥加密) -> privateDecrypt(私钥解密)
 * @abstract  privateEncrypt(私钥加密) -> ublicDecrypt(公钥解密)
 */
class OpenSSL_RSA {

//密钥长度 1024,2048,4096
//密钥格式 PKCS#8
//私钥密码 R356#pq
//生成后的密文里的   [+/=]分别替换成 [-_ ] , 加密时拆成117位，解密时拆成128位
//    const KEYSIZE = 2048;
//    const KEYSIZE = 1024;
//    const PRIVATEKEY = "RSA_Key/1024_private_key.pem";
//    const PUBLICKEY = "RSA_Key/1024_public_key.pem";
//    const KEYSIZE = 2048;
//    const PRIVATEKEY = "RSA_Key/2048_private_key.pem";
//    const PUBLICKEY = "RSA_Key/2048_public_key.pem";
//    
//    protected $CONF = 'alipay/openssl/openssl.cnf';
    //protected
    private $keysize = "2048";
    private $privatekey = "RSA_Key/2048_private_key.pem";
    private $publickey = "RSA_Key/2048_public_key.pem";
    private $publickey2 = "";
    private $conf = 'alipay/openssl/openssl.cnf';

    /**
     * 设置配置信息 ["keysize"=>1024,"privatekey"=>1024_private_key.pem,"publickey"=>1024_public_key.pem]
     * @param string $set_file
     */
    public function __construct($set_file = []) {
        if (!empty($set_file)) {
            if (!empty($set_file["keysize"])) {
                $this->keysize = $set_file["keysize"];
            }
            if (!empty($set_file["privatekey"])) {
                $this->privatekey = $set_file["privatekey"];
            }
            if (!empty($set_file["publickey"])) {
                $this->publickey = $set_file["publickey"];
            }
            if (!empty($set_file["publickey2"])) {
                $this->publickey2 = $set_file["publickey2"];
            }
        } else {
            $this->keysize = 2048;
            $this->privatekey = "RSA_Key/2048_private_key.pem";
            $this->publickey = "RSA_Key/2048_public_key.pem";
        }
    }

    /**
     * 獲得Config
     * @return array
     */
    public function getListConf() {
        return [
            [
                "keysize" => $this->keysize,
                "privatekey" => $this->privatekey,
                "publickey" => $this->publickey,
            ],
        ];
    }

    private static function keygen() {
        // window系统要设置openssl环境变量或通过配置信息指定配置文件
        $conf = array(
            'private_key_bits' => $this->keysize,
            'config' => $this->conf,
        );
        $res = openssl_pkey_new($conf);
        if ($res) {
            $d = openssl_pkey_get_details($res);
            $pub = $d['key'];
            $bits = $d['bits'];
            $filepath = $bits . '_rsa_private_key.pem';
            openssl_pkey_export($res, $pri, null, $conf);
            openssl_pkey_export_to_file($res, $filepath, null, $conf);
            print_r(["private_key" => $pri, "public_key" => $pub, "keysize" => $bits]);
        } else
            echo "openssl_pkey_new falls";
    }

    /**
     * 加密
     * @param string|int $msg
     * @param string|int $key
     * @param string|int $method
     * @param string|int $options
     * @return string
     */
    private function encrypt($msg, $key, $method = "AES-128-CBC", $options = OPENSSL_RAW_DATA) {
        $ivlen = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $cipher = openssl_encrypt($msg, $method, $key, $options, $iv);
        $hmac = hash_hmac('sha256', $cipher, $key, $as_binary = true);
        $cipher = base64_encode($iv . $hmac . $cipher);
        return $cipher;
    }

    /**
     * 解密
     * @param string|int $cipher
     * @param string|int $key
     * @param string|int $method
     * @param string|int $options
     * @return boolean
     */
    private function decrypt($cipher, $key, $method = "AES-128-CBC", $options = OPENSSL_RAW_DATA) {
        $c = base64_decode($cipher);
        $ivlen = openssl_cipher_iv_length($method);
        $iv = substr($c, 0, $ivlen);
        $hmac = substr($c, $ivlen, $sha2len = 32);
        $cipher = substr($c, $ivlen + $sha2len);
        $msg = openssl_decrypt($cipher, $method, $key, $options, $iv);
        $calcmac = hash_hmac('sha256', $cipher, $key, $as_binary = true);
        if (hash_equals($hmac, $calcmac))
            return $msg; //PHP 5.6+ timing attack safe comparison
        return false;
    }

    /**
     * 获取公开密码匙
     * @return string
     */
    private function getPublicKey() {
//        $pem = file_get_contents("D:\wwwroot\www.test013.com\public\/$this->publickey");
        if (file_exists($this->publickey)) {
            $pem = file_get_contents($this->publickey);
        } else {
//            $pem = chunk_split(base64_encode($this->publickey2),64,"\n");
            if (strpos($this->publickey2, '-----BEGIN') !== false) {
                $pem = $this->publickey2;
            } else {
                $pem = chunk_split($this->publickey2, 64, "\n");
                $pem = "-----BEGIN PUBLIC KEY-----\n" . $pem . "-----END PUBLIC KEY-----\n";
            }
//            $pem = "-----BEGIN PUBLIC KEY-----\n".$pem."-----END PUBLIC KEY-----\n";
        }
        // $pem = chunk_split(base64_encode($pem),64,"\n"); // transfer to pem format
        // $pem = "-----BEGIN CERTIFICATE-----\n".$pem."-----END CERTIFICATE-----\n";
        $publicKey = openssl_pkey_get_public($pem);
        return $publicKey;
    }

    /**
     * 获取公开密码匙
     * @return string
     */
    private function getPrivateKey() {
        $pem = file_get_contents($this->privatekey);
        // $pem = chunk_split($pem,64,"\n"); // transfer to pem format
        // $pem = "-----BEGIN PRIVATE KEY-----\n".$pem."-----END PRIVATE KEY-----\n";
        $privateKey = openssl_pkey_get_private($pem);
        return $privateKey;
    }

    /**
     * Sing 加密
     * @param string|int $msg
     * @param string|int $algorithm
     * @return string
     */
    private function sign($msg, $algorithm = OPENSSL_ALGO_SHA256) {
        $sign = "";
        $key = self::getPrivateKey();
        // OPENSSL_ALGO_SHA256 OPENSSL_ALGO_MD5 OPENSSL_ALGO_SHA1
        openssl_sign($msg, $sign, $key, $algorithm);
        $sign = base64_encode($sign);
        openssl_free_key($key);
        return $sign;
    }

    private function verify($msg, $sign, $algorithm = OPENSSL_ALGO_SHA256) {
        $sign = base64_decode($sign);
        $key = self::getPublicKey();
        $result = openssl_verify($msg, $sign, $key, $algorithm);
        openssl_free_key($key);
        return $result;
    }

    /**
     * 加密码时把特殊符号替换成URL可以带的内容  [+/=]分别替换成 [-_ ]
     * @param string|int $string
     * @return array
     */
    private function urlsafe_b64encode($string) {
        $data = base64_encode($string);
        $data = str_replace(array('+', '/', '='), array('-', '_', ''), $data);
        return $data;
    }

    /**
     * 解密码时把转换后的符号替换特殊符号
     * @param string|int $string
     * @return string
     */
    private function urlsafe_b64decode($string) {
        $data = str_replace(array('-', '_'), array('+', '/'), $string);
        $mod4 = strlen($data) % 4;
        if ($mod4) {
            $data .= substr('====', $mod4);
        }
        return base64_decode($data);
    }

    public function retPublicKey() {
        $pem = file_get_contents($this->publickey);
        // $pem = chunk_split(base64_encode($pem),64,"\n"); // transfer to pem format
        // $pem = "-----BEGIN CERTIFICATE-----\n".$pem."-----END CERTIFICATE-----\n";
//        $publicKey = openssl_pkey_get_public($pem);
        return $pem;
    }

    /**
     * 利用公钥加密 publicEncrypt->privateDecrypt
     * @param string $source_data
     * @return string
     */
    public function publicEncrypt($source_data) {
        $data = "";
        $key = self::getPublicKey();
//        $dataArray = str_split($source_data, $this->keysize/8);
        $KEYSIZE = $this->keysize / 8 - 11;
        $dataArray = str_split($source_data, $KEYSIZE);
        $base64_encode = [];
        foreach ($dataArray as $value) {
            $encryptedTemp = "";
            openssl_public_encrypt($value, $encryptedTemp, $key);
//            $data .= $encryptedTemp;
            $data .= $encryptedTemp;
//            $base64_encode[] = ["KEYSIZE/8" => $KEYSIZE, "value" => $value, "encryptedTemp" => $encryptedTemp];
        }
        openssl_free_key($key);
        return self::urlsafe_b64encode(base64_encode($data));
    }

    /**
     * 私钥解密
     * @param string|int $eccryptData
     * @return string
     */
    public function privateDecrypt($eccryptData) {
        $decrypted = "";
        $decodeStr = self::urlsafe_b64decode(base64_decode($eccryptData));
        $key = self::getPrivateKey();
//        $enArray = str_split($decodeStr, $this->keysize/8);
        $enArray = str_split($decodeStr, $this->keysize / 8);
        $base64_decode = [];

        foreach ($enArray as $va) {
            $decryptedTemp = "";
            openssl_private_decrypt($va, $decryptedTemp, $key);
            $decrypted .= $decryptedTemp;
//            $base64_decode[] = ["KEYSIZE/8" => $this->keysize / 8, "value" => $va, "encryptedTemp" => $decryptedTemp];
        }
        openssl_free_key($key);
        return $decrypted;
    }

    /**
     * 利用私钥加密 privateEncrypt->publicDecrypt
     * @param string|int $source_data
     * @return string|int
     */
    public function privateEncrypt($source_data) {
        $data = "";

        $KEYSIZE = $this->keysize / 8 - 11;
        $dataArray = str_split($source_data, $KEYSIZE);
//        $dataArray = str_split($source_data, $this->keysize / 8);
        $key = self::getPrivateKey();
        foreach ($dataArray as $value) {
            $encryptedTemp = "";
            openssl_private_encrypt($value, $encryptedTemp, $key);
//            var_dump( strlen($encryptedTemp));
            $data .= $encryptedTemp;
        }
        openssl_free_key($key);
//        return base64_encode($data);
        return self::urlsafe_b64encode(base64_encode($data));
    }

    /**
     * 公钥解密
     * @param string|int $eccryptData
     * @return string
     */
    public function publicDecrypt($eccryptData) {
        $decrypted = "";
        $decodeStr = self::urlsafe_b64decode(base64_decode($eccryptData));
//        $decodeStr = base64_decode($eccryptData);
        $key = self::getPublicKey();

        $enArray = str_split($decodeStr, $this->keysize / 8);

        foreach ($enArray as $va) {
            $decryptedTemp = "";
            openssl_public_decrypt($va, $decryptedTemp, $key);
            $decrypted .= $decryptedTemp;
        }
        openssl_free_key($key);
        return $decrypted;
    }

}

//<!--$plain  = "Some secret here for you ...";
//$key    = openssl_random_pseudo_bytes(16);
//$cipher = OpenSSL_RSA::encrypt($plain, $key);
//$msg    = OpenSSL_RSA::decrypt($cipher, $key);
//print_r(['明文'=>$plain, '解密'=>$msg, '密文'=>$cipher]);
//
//$plain  = "利用公钥加密，私钥解密做数据保密通信!";
//$cipher = OpenSSL_RSA::publicEncrypt($plain);
//$msg    = OpenSSL_RSA::privateDecrypt($cipher);
//print_r(['明文'=>$plain, '解密'=>$msg, '密文'=>$cipher]);
//
//$plain  = "利用私钥加密，公钥解密可以做身份验证";
//$cipher = OpenSSL_RSA::privateEncrypt($plain);
//$msg    = OpenSSL_RSA::publicDecrypt($cipher);
//print_r(['明文'=>$plain, '解密'=>$msg, '密文'=>$cipher]);
//
//$msg    = 'a=123';
//$sign   = OpenSSL_RSA::sign($msg);
//$verify = OpenSSL_RSA::verify($msg, $sign);
//print_r(['预签'=>$msg, '签名'=>$sign, '验证'=>$verify==1?"PASS":"FAIL"]);-->
