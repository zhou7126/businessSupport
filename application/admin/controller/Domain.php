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
 * 域名管理控制器
 * Class Index
 * @package app\admin\controller
 */
class Domain extends Controller
{

    protected $table = 'SystemDomain';

    public function __construct()
    {
        parent::__construct();
        if (!NodeService::islogin()) {
            $this->error('未登录', url('@admin/login'));
        }
    }

    private function authWhere($field = 'uid')
    {
        $user = session('admin_user');
        return $user['username'] == 'admin' ? [] : [$field => $user['id']];
    }

    /**
     * 域名列表
     * @throws \ReflectionException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index()
    {
        $this->title = '域名管理';
        $query = $this->_query($this->table)->like('call_domain,jump_domain,remark')->equal('status');
        $query->where(self::authWhere())->timeBetween('created_at')->order('id desc')->page();
    }

    public function add()
    {
        $this->applyCsrfToken();
        $this->_form($this->table, 'form');
    }

    /**
     * 编辑域名
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
            if (!isset($data['id'])) {
                $call_domain = explode(PHP_EOL, trim($data['call_domain'], PHP_EOL));
                $jump_domain = explode(PHP_EOL, trim($data['jump_domain'], PHP_EOL));
                $num = count($call_domain);
                if ($num < 1 || $num != count($jump_domain)) {
                    $this->error('访问域名和跳转域名个数不匹配');
                }
                $all = [];
                $nowTime = time();
                for ($i = 0; $i < $num; $i++) {
                    $visitDomain = trim($call_domain[$i], "/");
                    Db::name($this->table)->where([
                        ['call_domain', '=', $visitDomain],
                    ])->where(self::authWhere())->find();
                    $jumpTmp = explode(' ', $jump_domain[$i]);
                    $jumpUrl = empty($jumpTmp[1]) ? trim($jumpTmp[0], "/") : trim($jumpTmp[0], "/") . '?ChannelCode=' . $jumpTmp[1];
                    $all[] = [
                        'uid' => session('admin_user.id'),
                        'call_domain' => trim(trim($call_domain[$i]), "/"),
                        'jump_domain' => trim($jumpUrl),
                        'remark' => $data['remark'],
                        'created_at' => $nowTime,
                    ];
                }
                Db::startTrans();
                try {
                    $data = $all;
                    $insertAll = [];
                    foreach ($all as $k => $v) {
                        if (empty(self::authWhere())) {
                            Db::name($this->table)->where('call_domain', $v['call_domain'])->delete();
                        } else {
                            Db::name($this->table)->where('call_domain', $v['call_domain'])->where(self::authWhere())->delete();
                            $otherNum = Db::name($this->table)->where([
                                ['call_domain', '=', $v['call_domain']],
                                ['uid', '<>', self::authWhere()['uid']]
                            ])->where(self::authWhere())->count();
                            if ($otherNum > 0) {
                                continue;
                            }
                        }
                        $insertAll[] = $v;
                    }
                    Db::name($this->table)->insertAll($insertAll);
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error("域名添加失败，{$e->getMessage()}");
                }
            } else {
                $call_domain = explode(PHP_EOL, trim($data['call_domain']));
                $jump_domain = explode(PHP_EOL, trim($data['jump_domain']));
                $data['call_domain'] = trim($call_domain[0]);
                $data['jump_domain'] = trim($jump_domain[0]);
                $update = [
                    'call_domain' => $data['call_domain'],
                    'jump_domain' => $data['jump_domain'],
                    'remark' => $data['remark']
                ];
                Db::name($this->table)->where('id', $data['id'])->where(self::authWhere())->update($update);
            }
        }
    }

    protected function _form_result($result)
    {
        if ($result && $this->request->isPost()) {
            $this->success('操作成功');
        }
    }

    /**
     * 禁用域名
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function forbid()
    {
        $this->applyCsrfToken();
        $this->_save($this->table, ['status' => '0'], '', self::authWhere());
    }

    /**
     * 启用域名
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function resume()
    {
        $this->applyCsrfToken();
        $this->_save($this->table, ['status' => '1'], '', self::authWhere());
    }

    /**
     * 删除域名
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function remove()
    {
        $this->applyCsrfToken();
        $this->_delete($this->table, '', self::authWhere());
    }

}
