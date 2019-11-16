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
 * 模板分组控制器
 * Class Index
 * @package app\admin\controller
 */
class TemplateClass extends Controller
{

    protected $table = 'SystemTemplateClass';

    public function __construct()
    {
        parent::__construct();
        if (!NodeService::islogin()) {
            $this->error('访问授权失败，请重新登录授权再试！');
        }
    }

    /**
     * 模板分组列表
     * @throws \ReflectionException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index()
    {
        $this->title = '模板分组';
        $query = $this->_query($this->table)->like('name');
        $query->timeBetween('created_at')->where(['is_deleted' => '0'])->order('id desc')->page();
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
            if (isset($data['id'])) {
                $data['updated_at'] = time();
            } else {
                $data['created_at'] = time();
            }
        } else {
            //$this->tempCate = Db::name('SystemTemplateClass')->order('created_at desc')->select();
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

}
