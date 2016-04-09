<?php
/**
 * ECshop订单同步接口,服务端
 * @author  jipi （im@jipi.cc）
 */

//key需一致，否则禁止访问
@$_POST['key'] == '{$key}' or die('禁止访问');

define('IN_ECS', true);
require dirname(__FILE__) . '/includes/init.php';

// 根据请求类型进入相应的接口处理程序
switch ($_POST['m']) {
    //获取最近的订单,或者id大于指定id的订单
    case 'get_order':
        get_order();
        break;
    //更新指定id的订单信息
    case 'update_order':
        update_order($_POST['oid'], $_POST['param']);
        break;
    //获取指定id的订单信息
    case 'get_order_id':
        get_order_one($_POST['oid'], 0, 0);
        break;
    //获取指定单号的订单信息
    case 'get_order_sn':
        get_order_one(0, 0, $_POST['sn']);
        break;
    //获取指定单号的商品列表
    case 'get_goods_list':
        get_goods_list($_POST['oid'], 0);
        break;
    default:
        die('禁止访问!');
        break;
}


/**
 * 取得订单信息
 * @param   int    $oid      订单id（如果order_id > 0 就按id查，否则按sn查）
 * @param   string $order_sn 订单号
 * @return  array   订单信息（金额都有相应格式化的字段，前缀是formated_）
 */

//===============================
//order_status    tinyint(1)     否    作何操作0,未确认, 1已确认; 2已取消; 3无效; 4退货
//shipping_status    tinyint(1)     否    发货状态; 0未发货; 1已发货  2已取消  3备货中
//pay_status    tinyint(1)     否    支付状态 0未付款;  1已付款中;  2已付款
//===============================
function get_order() {
    $sql = "SELECT  order_id,order_sn FROM " . $GLOBALS['ecs']->table('order_info') . "WHERE order_status IN(1,0) AND
     shipping_status=0";
    //如果传递了上一次拉取的订单id,则获取大于该id的数据
    if (isset($_POST['oid'])) {
        $sql .= " AND order_id > {$_POST['oid']}";
    }
    $sql .= " ORDER BY order_id";
    $order = $GLOBALS['db']->getAll($sql);
    //无数据,则返回false
    $order || die(json_encode(false));
    foreach ($order as $value) {
        $data[] = get_order_one($value['order_id'], 1, 0);
    }
    $last_sync_id = getLastId();
    echo json_encode(array ('data' => $data, 'last_sync_id' => $last_sync_id));
}


/**
 * 取得最大id
 */
function getLastId() {
    $sql = "SELECT max(order_id) AS 'last_sync_id' FROM " . $GLOBALS['ecs']->table('order_info');
    $time = $GLOBALS['db']->getRow($sql);
    return $time['last_sync_id'];
}


/**
 * 修改订单信息表
 * @param   int   $order_id 订单id
 * @param   array $param    key => value
 * @return  bool 成功与否
 */
function update_order($order_id, $param) {
    $res = $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('order_info'), $param, 'UPDATE', "order_id = '$order_id'");
    if ($res) {
        echo json_encode(true);
    } else {
        echo json_encode(false);
    }
}


/**
 * 取得单张订单信息
 * @param   int    $oid      订单id（如果order_id > 0 就按id查，否则按sn查）
 * @param   string $order_sn 订单号
 * @param   int    $type     1为return结果集,0为输出json
 * @return  array   订单信息（金额都有相应格式化的字段，前缀是formated_）
 */
function get_order_one($oid, $type = 1, $order_sn = '') {
    // 计算订单各种费用之和的语句
    $total_fee = " (goods_amount - discount + tax + shipping_fee + insure_fee + pay_fee + pack_fee + card_fee) AS total_fee ";
    if ($oid) {
        $condition = " order_id = " . $oid;
    } else if ($order_sn) {
        $condition = " order_sn = " . $order_sn;
    }

    $sql = "SELECT  order_id,order_sn,o.user_id,order_status,shipping_status,pay_status,consignee,
        province,city,district,address,zipcode,tel,mobile,o.email,
        postscript,goods_amount,add_time,confirm_time,pay_time,shipping_fee,insure_fee,pack_fee,
        card_fee,pay_fee,shipping_fee,tax,shipping_time,invoice_no,to_buyer,pay_note,discount,
        " . $total_fee . ",IFNULL(u.user_name, '匿名用户') AS buyer FROM
        " . $GLOBALS['ecs']->table('order_info') . " AS o LEFT JOIN " . $GLOBALS['ecs']->table('users')
        . " AS u ON u.user_id = o.user_id WHERE " . $condition . " ORDER BY order_id DESC";

    $order = $GLOBALS['db']->getRow($sql);
    //无结果则退出
    if (!$order) die (json_encode(false));
    //追加抓取的用户id,用以区分数据
    $order['api_id'] = $_POST['api_id'];
    //追加地址,根据地区id,拼装地址,用空格隔开
    $address = get_address($order['order_id']);
    $order['province_name'] = $address['province'];
    $order['city_name'] = $address['city'];
    $order['district_name'] = $address['district'];
    //追加商品列表
    $order['goods_list'] = get_goods_list($order['order_id']);

    if ($type) return $order;
    echo json_encode($order);
}


/**
 * 获取订单商品列表
 * @param  int $oid 订单id
 * @return array 商品数组
 */
function get_goods_list($oid, $type = 1) {
    $sql = "SELECT o.order_id,o.goods_id,o.goods_name,o.goods_number,o.goods_price,o.goods_attr, o.goods_attr_id,
        (o.goods_price * o.goods_number) AS total_price,g.goods_thumb FROM  " . $GLOBALS['ecs']->table('order_goods') . "AS o
                LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON o.goods_id = g.goods_id WHERE o.order_id = " . $oid;

    $goods = $GLOBALS['db']->getAll($sql);
    foreach ($goods as &$value) {
        $value['api_id'] = $_POST['api_id'];
    }
    if ($type) return $goods;
    echo json_encode($goods);
}


/**
 * 根据订单id,获取收货人地址
 * @param  integer $oid 订单id
 * @return string  返回带空格间隔的 国 省 市 区
 */
function get_address($oid) {
    $sql = "SELECT
            p.region_name AS province,
            t.region_name AS city,
            d.region_name AS district
            FROM " . $GLOBALS['ecs']->table('order_info') . " AS o
            LEFT JOIN " . $GLOBALS['ecs']->table('region') . " AS p ON o.province = p.region_id
            LEFT JOIN " . $GLOBALS['ecs']->table('region') . " AS t ON o.city = t.region_id
            LEFT JOIN " . $GLOBALS['ecs']->table('region') . " AS d ON o.district = d.region_id
            WHERE o.order_id = " . $oid;
    $address = $GLOBALS['db']->getRow($sql);
    return $address;
}

/**
 * 取得订单总金额
 * @param   int  $oid          订单id
 * @param   bool $include_gift 是否包括赠品
 * @return  float   订单总金额
 */
function order_amount($oid, $include_gift = true) {
    $sql = "SELECT SUM(goods_price * goods_number) " .
        "FROM " . $GLOBALS['ecs']->table('order_goods') .
        " WHERE order_id = '$oid'";
    if (!$include_gift) {
        $sql .= " AND is_gift = 0";
    }
    return floatval($GLOBALS['db']->getOne($sql));
}


/**
 * 更新订单商品信息
 * @param   int   $order_id 订单 id
 * @param   array $_sended  Array(‘商品id’ => ‘此单发货数量’)
 * @param   array $goods_list
 * @return  Bool
 */
function update_order_goods($order_id, $_sended, $goods_list = array ()) {
    if (!is_array($_sended) || empty($order_id)) {
        return false;
    }

    foreach ($_sended as $key => $value) {
        // 超值礼包
        if (is_array($value)) {
            if (!is_array($goods_list)) {
                $goods_list = array ();
            }

            foreach ($goods_list as $goods) {
                if (($key != $goods['rec_id']) || (!isset($goods['package_goods_list']) || !is_array($goods['package_goods_list']))) {
                    continue;
                }

                $goods['package_goods_list'] = package_goods($goods['package_goods_list'], $goods['goods_number'], $goods['order_id'], $goods['extension_code'], $goods['goods_id']);
                $pg_is_end = true;

                foreach ($goods['package_goods_list'] as $pg_key => $pg_value) {
                    if ($pg_value['order_send_number'] != $pg_value['sended']) {
                        $pg_is_end = false; // 此超值礼包，此商品未全部发货

                        break;
                    }
                }

                // 超值礼包商品全部发货后更新订单商品库存
                if ($pg_is_end) {
                    $sql = "UPDATE " . $GLOBALS['ecs']->table('order_goods') . "
                            SET send_number = goods_number
                            WHERE order_id = '$order_id'
                            AND goods_id = '" . $goods['goods_id'] . "' ";

                    $GLOBALS['db']->query($sql, 'SILENT');
                }
            }
        } // 商品（实货）（货品）
        elseif (!is_array($value)) {
            // 检查是否为商品（实货）（货品)
            foreach ($goods_list as $goods) {
                if ($goods['rec_id'] == $key && $goods['is_real'] == 1) {
                    $sql = "UPDATE " . $GLOBALS['ecs']->table('order_goods') . "
                            SET send_number = send_number + $value
                            WHERE order_id = '$order_id'
                            AND rec_id = '$key' ";
                    $GLOBALS['db']->query($sql, 'SILENT');
                    break;
                }
            }
        }
    }

    return true;
}
