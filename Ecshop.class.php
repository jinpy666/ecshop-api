<?php
/**
 * ECshop接口,客户端
 * Class Ecshop
 * @author  jipi （im@jipi.cc）
 */
class Ecshop {
    protected $api = '';
    protected $param = array ();

    public function __construct() {
        self::$api = 'http://www.xxxsx.com/Ecshop.php';    //api地址
        self::$param['key'] = $key;     //key 密钥
        self::$param['api_id'] = 1; //数字，api主键,用于多家Ecshop商城区分
    }


    /**
     * 更新指定订单的信息
     * @param int   $id    订单号
     * @param array $param 需要更新的键值  key=>val
     * @return  boolean 是否成功
     */
    public function update($id, array $param) {
        self::$param['m'] = 'update_order';
        self::$param['oid'] = $id;
        self::$param['param'] = $param;
        $data = self::post();
        return $data;
    }


    /**
     * 获取所有订单,适合第一次同步订单
     * @return array 结果集
     */
    public function getAll() {
        self::$param['m'] = 'get_order';
        $data = self::post();
        return $data;
    }


    /**
     * 获取最新的订单信息
     * @param int id 上一次同步的id
     * @return array 结果集
     */
    public function getNew($id) {
        self::$param['m'] = 'get_order';
        self::$param['oid'] = $id;
        $data = self::post();
        return $data;
    }


    /**
     * 获取单条数据,用于同步单条数据
     * @param int $id 订单id
     * @return array 结果集
     */
    public function getOne($id) {
        self::$param['m'] = 'get_order_id';
        self::$param['oid'] = $id;
        $data = self::post();
        return $data;
    }


    /**
     * 根据订单号,获取指定的订单信息
     * @param $sn 订单号
     * @return mixed 结果集
     */
    public function getOneBySn($sn) {
        self::$param['m'] = 'get_order_sn';
        self::$param['sn'] = $sn;
        $data = self::post();
        return $data;
    }


    /**
     * 根据订单号,获取指定的商品列表
     * @param $id 订单号
     * @return mixed 结果集
     */
    public function getGoods($id){
        self::$param['m'] = 'get_goods_list';
        self::$param['oid'] = $id;
        $data = self::post();
        return $data;
    }


    /**
     * 接口访问post函数
     * @return mixed    返回接受到的数据
     */
    private function post() {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, self::$api);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_NOBODY, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query(self::$param));
        $data = curl_exec($curl);
        curl_close($curl);
        return json_decode($data, ture);
    }


}
