<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2019 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://demo.thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/ThinkAdmin
// | github 代码仓库：https://github.com/zoujingli/ThinkAdmin
// +----------------------------------------------------------------------

namespace app\admin\controller;

use app\admin\service\NodeService;
use library\Controller;
use library\tools\Data;
use think\Db;

/**
 * APP管理
 * Class User
 * @package app\admin\controller
 */
class App extends Controller
{
    const AD = 1;
    const PG = 2;

    /**
     * 指定当前数据表
     * @var string
     */
    public $table = 'SystemApp';

    public $tablePackage = 'SystemPackage';

    /**
     * APP管理
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function index()
    {
        $this->title = 'APP管理';
        $query = $this->_query($this->table);
        $query->order('created_at desc')->page();
    }

    /**
     * 添加APP
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add()
    {
        $this->applyCsrfToken();
        $this->_form($this->table, 'form');
    }

    public function base()
    {
        $id = $this->request->param('id');
        $this->row = Db::name($this->table)->where('id',$id)->find();

        $query = $this->_query($this->table);
        $query->order('created_at desc')->page();
    }


    public function template()
    {
        $id = $this->request->param('id');
        $this->row = Db::name($this->table)->where('id',$id)->find();

        $this->applyCsrfToken();
        $this->_form($this->table, 'template');
    }


    public function ad()
    {
        $id = $this->request->param('id');
        $this->row = Db::name($this->table)->where('id',$id)->find();
        $apk = Db::name($this->tablePackage)->where('type',self::AD)->order('created_at desc')->find();
        if ($apk){
            $apk['data'] = json_decode($apk['data'],true);
        }
        $this->apk = $apk;
        $this->apks = Db::name($this->tablePackage)->where('type',self::AD)->order('created_at desc')->select();
        $query = $this->_query($this->tablePackage);
        $query->where('type',self::PG)->order('created_at desc')->page();
    }

    public function changeAdConfig()
    {
        if($this->request->isPost()){
            $param = $this->request->param();

            if(!$param['ad_config_install_type'] || !in_array($param['ad_config_install_type'],[1,2])) {
                $this->error('请选择安装方式！');
            }
            if ($param['ad_config_install_type'] == 1 && !$param['ad_config_trust_apk_id']){
                $this->error('请选择托管APK！');
            }
            if ($param['ad_config_install_type'] == 2 && !$param['ad_config_install_url']){
                $this->error('请输入APK地址！');
            }

            $tmp = $param['ad_config_install_type'] == 1
                ? ['ad_config_trust_apk_id'=>$param['ad_config_trust_apk_id']]
                : ['ad_config_install_url'=>$param['ad_config_install_url']];
            $data = array_merge(['ad_config_install_type' => $param['ad_config_install_type']],$tmp);
            $res = Db::name($this->table)->where('id',$param['id'])->update($data);
            if($res !== false){
                $this->success('保存成功');
            }
            $this->error('数据库操作失败，请重新操作');
        }else{
            $this->error('错误');
        }
    }

    public function changePgConfig()
    {
        if($this->request->isPost()){
            $param = $this->request->param();

            if($param['pg_config_is_myapp'] == 1 && !$param['pg_config_myapp_url']) {
                $this->error('请输入应用宝地址！');
            }
            if (!$param['pg_config_install_type'] || !in_array($param['pg_config_install_type'],[1,2,3,4])){
                $this->error('请选择安装方式！');
            }
            if ($param['pg_config_install_type'] == 1 && !$param['pg_config_trust_ipa_id']){
                $this->error('请选择托管ipa！');
            }
            if ($param['pg_config_install_type'] == 2 && !$param['pg_config_install_url']){
                $this->error('请输入下载地址！');
            }
            if ($param['pg_config_install_type'] == 3 && !$param['pg_config_app_store_url']){
                $this->error('请输入app store地址！');
            }
            if ($param['pg_config_install_type'] == 4 && !$param['pg_config_plist']){
                $this->error('请输入plist！');
            }
            $data = [];
            switch ($param['pg_config_install_type']){
                case 1:
                    $data['pg_config_trust_ipa_id'] = $param['pg_config_trust_ipa_id'];
                    break;
                case 2:
                    $data['pg_config_install_url'] = $param['pg_config_install_url'];
                    break;
                case 3:
                    $data['pg_config_install_type'] = $param['pg_config_install_type'];
                    break;
                case 4:
                    $data['pg_config_plist'] = $param['pg_config_plist'];
                    break;
            }
            $data['pg_config_install_type'] = $param['pg_config_install_type'];
            $data['pg_config_is_myapp'] = $param['pg_config_is_myapp'];
            if($param['pg_config_is_myapp'] == 1){
                $data['pg_config_myapp_url'] = $param['pg_config_myapp_url'];
            }
            $res = Db::name($this->table)->where('id',$param['id'])->update($data);
            if($res !== false){
                $this->success('保存成功');
            }
            $this->error('数据库操作失败，请重新操作');
        }else{
            $this->error('错误');
        }
    }

    protected function _ad_page_filter(&$data){
        foreach ($data as &$vo) {
            $vo['data'] = json_decode($vo['data'],true);
        }
    }

    protected function _ios_page_filter(&$data){
        foreach ($data as &$vo) {
            $vo['data'] = json_decode($vo['data'],true);
        }
    }


    public function addApk()
    {
        $this->applyCsrfToken();
        $url = $this->request->param('path');
        $appId = $this->request->param('appId');
        if(!$url) $this->error('路径为空');
        if(!$appId) $this->error('appId为空');
        $index = strpos($url,'upload/');
        $path = substr($url,$index);
        $savePath = ROOT_PATH . $path;
        include_once EXTEND_PATH . 'parseapp/ApkParser.php';
        $apkParser = new \ApkParser();
        if(!$apkParser->open($savePath)){
            $this->error('文件路径错误！');
        }

        $data = [
            'app_id' => $appId,
            'path' => $path,
            'size' => '55M',
            'type' => self::AD,
            'data' => json_encode([
                'package' => $apkParser->getPackage(),
                'versionName' => $apkParser->getVersionName(),
                'versionCode' => $apkParser->getVersionCode(),
            ]),
            'created_at' => time(),
        ];

        $res = Db::name($this->tablePackage)->insert($data);
        if($res){
            $this->success('操作成功');
        }else{
            $this->error('数据库添加失败！请重新操作');
        }
    }

    public function addIpa()
    {
        $this->applyCsrfToken();
        $url = $this->request->param('path');
        $appId = $this->request->param('appId');
        if(!$url) $this->error('路径为空');
        if(!$appId) $this->error('appId为空');
        $index = strpos($url,'upload/');
        $path = substr($url,$index);
        $savePath = ROOT_PATH . $path;
        include_once EXTEND_PATH . 'parseapp/IpaParser.php';
        $ipaParser = new \IpaParser();
        if(!$ipaParser->parse($savePath)){
            $this->error('文件路径错误！');
        }

        $data = [
            'app_id' => $appId,
            'path' => $path,
            'size' => '55M',
            'type' => self::PG,
            'data' => json_encode([
                'package' => $ipaParser->getPackage(),
                'versionName' => $ipaParser->getVersion(),
                'versionCode' => '',
            ]),
            'created_at' => time(),
        ];

        $res = Db::name($this->tablePackage)->insert($data);
        if($res){
            $this->success('操作成功');
        }else{
            $this->error('数据库添加失败！请重新操作');
        }
    }

    private function uploadPackage($type,$instance)
    {

    }

    /**
     * 删除Package
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function removePackage()
    {
        $this->applyCsrfToken();
        $this->_delete($this->tablePackage);
    }


    public function ios()
    {
        $id = $this->request->param('id');
        $this->row = Db::name($this->table)->where('id',$id)->find();
        $ipa = Db::name($this->tablePackage)->where('type',self::PG)->order('created_at desc')->find();
        if ($ipa){
            $ipa['data'] = json_decode($ipa['data'],true);
        }
        $this->ipa = $ipa;
        $this->ipas = Db::name($this->tablePackage)->where('type',self::PG)->order('created_at desc')->select();
        $query = $this->_query($this->tablePackage);
        $query->where('type',self::PG)->order('created_at desc')->page();
    }

    /**
     * template表单处理
     * @param array $data
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function template_form_filter(&$data)
    {
        if ($this->request->isPost()){
            if (empty($data['webTitle'])) $this->error('请输入webTitle！');
            if (empty($data['imgLogo'])) $this->error('请上传imgLogo！');
            if (empty($data['kefuUrl'])) $this->error('请输入kefuUrl！');
            if (empty($data['defaultChannelCode'])) $this->error('请输入defaultChannelCode！');

            $data['updated_at'] = time();
        }
    }


    /**
     * base表单处理
     * @param array $data
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function base_form_filter(&$data)
    {
        if ($this->request->isPost()){
            //if (empty($data['name'])) $this->error('请输入应用名称！');
            if (empty($data['img'])) $this->error('请上传应用图标！');
            if (empty($data['app_key'])) $this->error('请输入appkey！');

            $data['created_at'] = time();
            $data['updated_at'] = time();
        }
    }


    /**
     * 启用APP
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function change()
    {
//        $this->applyCsrfToken();
        $this->_save($this->table, [$this->request->param('field') => $this->request->param('value')]);
    }

    /**
     * 删除APP
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function remove()
    {
        $this->applyCsrfToken();
        $this->_delete($this->table);
    }

}
