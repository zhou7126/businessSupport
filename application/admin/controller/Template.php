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
use ZipArchive;
use think\Console;
use think\Db;
use think\exception\HttpResponseException;


/**
 * 模板管理控制器
 * Class Index
 * @package app\admin\controller
 */
class Template extends Controller
{

    protected $table = 'systemTemplate';

    public function __construct()
    {
        parent::__construct();
        if (!NodeService::islogin()) {
            $this->error('未登录', url('@admin/login'));
        }
    }

    /**
     * 模板列表
     * @throws \ReflectionException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index()
    {
//        $this->title = '模板管理';
//        $this->fetch();

        $this->title = '模板管理';
        $classData = Db::name('SystemTemplateClass')->order('created_at desc')->select();
        $this->className = array_column($classData, 'name', 'id');
        $query = $this->_query($this->table)->like('name')->equal('class_id');
        $query->timeBetween('created_at')->where(['is_deleted' => '0'])->order('id desc')->page();
    }

    protected function _index_page_filter(&$data)
    {
        foreach ($data as &$vo) {
            $vo['preview_img'] = $vo['package_img']; // 预览缩略图url
            $vo['preview_url'] = url('preview', '', '') . '?id=' . $vo['id']; //预览页面url
        }
    }

    /**
     * 预览模板
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function preview()
    {
        $id = input('id');
        $schemeDomain = $this->request->domain();
        $tempData = Db::name('SystemTemplate')->where([
            ['id', '=', $id],
            ['is_deleted', '=', 0]
        ])->find();
        if (empty($tempData)) {
            $this->error('模板不存在', '');
            abort(404, '模板不存在');
            return;
        }
        $fetchData = [
            'base_url' => $schemeDomain . '/' . $tempData['package'] . '/', // 页面默认URL
            'web_title' => 'XXX', // 页面名称
            'img_logo' => '', // logo图标
            'kefu_url' => '', // 客服地址
            'download_type' => 1, // 应用下载方式，1普通下载，2openinstall
            'channel_code' => 'xxxxxxx', // 渠道号
            'ad_config_install_type' => 1, // 安卓安装方式，1托管APK，2外部APK
            'ad_download_url' => '', // 安卓安装地址
            'pg_config_install_type' => 1, // 苹果安装方式，1托管IPA，2外部IPA，3AppStore及其他，4外部plist
            'pg_download_url' => '', // 苹果安装地址
            'statistics_code' => '', // 统计代码
            'openintsall_app_key' => 'xxxxxxxxxxx',
            'ext_img1' => '',
            'ext_img2' => '',
            'ext_img3' => '',
            'ext_img4' => '',
            'ext_img5' => '',
        ];
        if ($this->request->isMobile()) {
            $this->fetch($tempData['package'] . '/m/index.html', $fetchData);
        } else {
            $this->fetch($tempData['package'] . '/index.html', $fetchData);
        }
    }


    public function add()
    {
        $this->applyCsrfToken();
        $this->_form($this->table, 'form');
    }

    /**
     * 编辑模板
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit()
    {
        $this->applyCsrfToken();
        $this->_form($this->table, 'form');
    }

    public function _form_filter(&$data)
    {
        if ($this->request->isPost()) {
            $data['class_id'] = isset($data['class_id']) ? intval($data['class_id']) : 0;
            if (Db::name('SystemTemplateClass')->where(['id' => $data['class_id']])->count() < 1) {
                $this->error('模板分组不存在');
            }
            if (isset($data['package']) && !empty($data['package'])) { // 有上传压缩包
                $file = str_replace('\\', '/', env('root_path') . "safefile/" . $data['package']);
                if (!is_file($file)) {
                    $this->error('上传的压缩文件找不到');
                }
                $savePath = str_replace('\\', '/', env('root_path') . 'public/tpl/' . $data['class_id'] . '/' . md5_file($file));
                $zip = new ZipArchive;
                if ($zip->open($file) === TRUE) {
                    $zip->extractTo($savePath);
                    $zip->close();
                } else {
                    $this->error('压缩文件格式错误或已损坏');
                }
                $data['package'] = 'tpl/' . $data['class_id'] . '/' . md5_file($file);
                $data['package_img'] = $data['package'] . '/index.png';
            } else { // 未上传压缩包
                unset($data['package']);
            }
            if (isset($data['id'])) {
                $data['updated_at'] = time();
            } else {
                $data['created_at'] = time();
            }
        } else {
            $this->tempCate = Db::name('SystemTemplateClass')->order('created_at desc')->select();
        }
    }

    /**
     * 删除模板
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function remove()
    {
        $this->applyCsrfToken();
        $this->_delete($this->table);
    }

    /**
     * 模板分组列表
     */
    public function cate()
    {

    }

    public function cate_add()
    {

    }

    public function cate_edit()
    {

    }

    public function cate_del()
    {

    }
}
