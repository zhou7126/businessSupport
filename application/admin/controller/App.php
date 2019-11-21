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

    const UPLOAD_PACKAGE_DOMAIN = "http://www.armshores.com";
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
        $this->row = Db::name($this->table)->alias('a')
            ->leftJoin('system_template t','a.template_id = t.id')
            ->where('a.id',$id)
            ->field('a.*,t.name as tem_name,t.class_id')
            ->find();
        $this->templates = Db::name('SystemTemplate')->where('is_deleted',0)->order('created_at desc')->select();
        $this->templateClasss = Db::name('SystemTemplateClass')
            ->where('is_deleted',0)
            ->where('is_deleted',0)
            ->select();

        $this->applyCsrfToken();
        $this->_form($this->table, 'template');
    }


    public function getTemClass()
    {
        $classId = $this->request->param('classId');
        $where = [];
        if($classId) $where['class_id'] = $classId;
        $templates = Db::name('SystemTemplate')
            ->where('is_deleted',0)
            ->where($where)
            ->order('created_at desc')->select();
        $this->success('成功',$templates);
    }


    public function ad()
    {
        $id = $this->request->param('id');
        $this->row = Db::name($this->table)->where('id',$id)->find();
        $this->row['ad_config_data'] = json_decode($this->row['ad_config_install_data'],true);
        $apk = Db::name($this->tablePackage)->where('type',self::AD)->order('created_at desc')->find();
        if ($apk){
            $apk['data'] = json_decode($apk['data'],true);
        }
        $this->apk = $apk;
        $this->apks = Db::name($this->tablePackage)->where('type',self::AD)->order('created_at desc')->select();
        $query = $this->_query($this->tablePackage);
        $query->where('type',self::AD)->order('created_at desc')->page();
    }

    public function changeAdConfig()
    {
        if($this->request->isPost()){
            $param = $this->request->param();

            if(!$param['ad_config_install_type'] || !in_array($param['ad_config_install_type'],[1,2])) {
                $this->error('请选择安装方式！');
            }
            $config = json_decode($param['ad_config_install_data'],true);
            if ($param['ad_config_install_type'] == 1 && empty($config['apk_id'])){
                $this->error('请选择托管APK！');
            }
            if ($param['ad_config_install_type'] == 2 && empty($config['download_url'])){
                $this->error('请输入APK地址！');
            }

            $data = [
                'ad_config_install_type' => $param['ad_config_install_type'],
                'ad_config_install_data' => $param['ad_config_install_data'],
            ];
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
            if(empty($param['pg_config_install_data'])){
                $this->error('config_data为空');
            }
            $config = json_decode($param['pg_config_install_data'],true);
            if ($param['pg_config_install_type'] == 1 && empty($config['ipa_id'])){
                $this->error('请选择托管ipa！');
            }
            if ($param['pg_config_install_type'] == 2 && !$config['download_url']){
                $this->error('请输入下载地址！');
            }
            if ($param['pg_config_install_type'] == 3 && !$config['app_store_url']){
                $this->error('请输入app store地址！');
            }
            if ($param['pg_config_install_type'] == 4 && !$config['plist']){
                $this->error('请输入plist！');
            }
            $data = [];

            $data['pg_config_install_type'] = $param['pg_config_install_type'];
            $data['pg_config_install_data'] = $param['pg_config_install_data'];
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
        $updateData = $this->request->param('updateData');
        $appId = $this->request->param('appId');
        if(!$updateData) $this->error('包数据为空');
        if(!$appId) $this->error('appId为空');
        $data = [
            'app_id' => $appId,
            'path' => self::UPLOAD_PACKAGE_DOMAIN . $updateData['package_file'],
            'size' => $updateData['size'] . 'M',
            'type' => self::AD,
            'data' => json_encode([
                'package' => $updateData['signname'],
                'iconimage' => self::UPLOAD_PACKAGE_DOMAIN .$updateData['iconimage'],
                'versionName' => $updateData['version'],
                'versionCode' => $updateData['version'],
            ]),
            'created_at' => time(),
        ];
        $res = Db::name($this->tablePackage)->insert($data);
        Db::name($this->table)->where('id',$appId)->update(['img'=>self::UPLOAD_PACKAGE_DOMAIN .$updateData['iconimage']]);

        if($res){
            $this->success('操作成功');
        }else{
            $this->error('数据库添加失败！请重新操作');
        }
    }

    public function addIpa()
    {
        $this->applyCsrfToken();
        $updateData = $this->request->param('updateData');
        $appId = $this->request->param('appId');
        if(!$updateData) $this->error('包数据为空');
        if(!$appId) $this->error('appId为空');

        $data = [
            'app_id' => $appId,
            'path' => self::UPLOAD_PACKAGE_DOMAIN . $updateData['package_file'],
            'size' => $updateData['size'] . 'M',
            'type' => self::PG,
            'data' => json_encode([
                'iconimage' =>self::UPLOAD_PACKAGE_DOMAIN . $updateData['iconimage'],
                'package' => $updateData['signname'],
                'versionName' => $updateData['version'],
                'versionCode' => $updateData['version'],
                'plist_path' => self::UPLOAD_PACKAGE_DOMAIN . $updateData['plist_path'],
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


    /**
     * ios
     * @auth true
     * @menu true
     */
    public function ios()
    {
        $id = $this->request->param('id');
        $this->row = Db::name($this->table)->where('id',$id)->find();
        $this->row['pg_config_data'] = json_decode($this->row['pg_config_install_data'],true);

        $ipa = Db::name($this->tablePackage)->where('type',self::PG)->order('created_at desc')->find();
        if ($ipa){
            $ipa['data'] = json_decode($ipa['data'],true);
        }
        $this->ipa = $ipa;
        $this->ipas = Db::name($this->tablePackage)->where('type',self::PG)->order('created_at desc')->select();
        $query = $this->_query($this->tablePackage);
        $query->where('type',self::PG)->order('created_at desc')->page();
    }


    protected function _add_form_filter(&$data)
    {
        if ($this->request->isPost()){
            if (empty($data['name'])) $this->error('请输入应用名称！');
            if (empty($data['img'])) $this->error('请上传应用图标！');
            $data['app_key'] =substr(md5(uniqid(rand(),true)),0,8);
            $data['created_at'] = time();
            $data['updated_at'] = time();
        }
    }

    private function suiji()
    {

    }


    /**
     * template表单处理
     * @param array $data
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function _template_form_filter(&$data)
    {
        if ($this->request->isPost()){
            if (empty($data['template_id'])) $this->error('请选择模板！');
            if (empty($data['web_title'])) $this->error('请输入webTitle！');
            if (empty($data['img_logo'])) $this->error('请上传imgLogo！');
            if (empty($data['kefu_url'])) $this->error('请输入kefuUrl！');
            if (empty($data['channel_code'])) $this->error('请输入channelCode！');

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
    protected function _base_form_filter(&$data)
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
