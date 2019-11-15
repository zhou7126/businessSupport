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
            $this->error('访问授权失败，请重新登录授权再试！');
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
        $query = $this->_query($this->table)->like('name')->equal('class_id');
        $query->dateBetween('create_at')->where(['is_deleted' => '0'])->order('id desc')->page();
        exit();
        dump($query);
        exit();
    }

    public function add()
    {
        $this->applyCsrfToken();
        $this->_form($this->table, 'form');
//        var_dump($vo);
    }

    public function _form_filter(&$data)
    {
        if ($this->request->isPost()) {
            $data['created_at'] = time();
            $data['class_id'] = intval($data['class_id']);
            if (Db::name('SystemTemplateClass')->where(['id' => $data['class_id']])->count() < 1) {
                $this->error('模板分组不存在');
            }
            $file = str_replace('\\', '/', env('root_path') . "safefile/" . $data['package']);
            $savePath = str_replace('\\', '/', env('root_path') . 'tpl/' . $data['class_id'] . '/' . md5_file($file));
            if (!file_exists($file)) {
                $this->error('上传的压缩文件找不到');
            }
            $zip = new ZipArchive;
            if ($zip->open($file) === TRUE) {
                $zip->extractTo($savePath);
                $zip->close();//关闭处理的zip文件
            } else {
                $this->error('压缩文件格式错误或已损坏');
            }
            $data['package'] = $data['class_id'] . '/' . md5_file($file);
        } else {
            $this->tempCate = Db::name('SystemTemplateClass')->order('created_at desc')->select();
        }
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
