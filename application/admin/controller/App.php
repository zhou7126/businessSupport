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

    const UPLOAD_PACKAGE_DOMAIN = "https://www.armshores.com";
    /**
     * 指定当前数据表
     * @var string
     */
    public $table = 'SystemApp';

    public $tablePackage = 'SystemPackage';

    private function authWhere($field = 'uid')
    {
        $user = session('admin_user');
        return $user['id'] == 10000 ? [] : [$field => $user['id']];
    }

    private function getAdminId()
    {
        return 10000;
    }

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
        $query = $this->_query($this->table)->where(self::authWhere());
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

    /**
     * 基础信息
     * @auth true
     *
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function base()
    {
        $id = $this->request->param('id');
        $this->row = Db::name($this->table)->where('id', $id)->where(self::authWhere())->find();
        if (!$this->row) $this->error('该应用不存在！');
        $bindDomainData = Db::name('SystemAppDomain')->where(self::authWhere())->where('app_id', $id)->select();
        $domains = [];
        foreach ($bindDomainData as $k => $v) {
            $domains[] = empty($v['channel_code']) ? $v['domain'] : $v['domain'] . ' ' . $v['channel_code'];
        }
        $this->bindDomains = implode("\n", $domains);
        $this->applyCsrfToken();
        $this->_form($this->table, 'base');
    }


    /**
     * 配置模板
     * @auth true
     *
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function template()
    {
        $id = $this->request->param('id');
        $this->row = Db::name($this->table)->alias('a')
            ->leftJoin('system_template t', 'a.template_id = t.id')
            ->where('a.id', $id)
            ->where(self::authWhere('a.uid'))
            ->field('a.*,t.name as tem_name')
            ->find();
        if (!$this->row) $this->error('该应用不存在！');

        $this->templates = Db::name('SystemTemplate')
            ->alias('t')
            ->leftJoin(
                Db::name('SystemTemplateHistory')
                    ->field('distinct template_id')
                    ->where('app_id', $id)
                    ->where(self::authWhere())
                    ->buildSql()
                . ' h', 't.id = h.template_id'
            )
            ->where('t.is_deleted', 0)
            ->where(self::authWhere("t.uid"))
            ->field('t.*,h.template_id as hid')
            ->order('t.created_at desc')->select();

        if (!empty($this->row['ext_json'])) {
            $this->ext_json = json_decode($this->row['ext_json'], true);
        } else {
            $this->ext_json = [];
        }
        $this->hisTempData = Db::name('SystemTemplateHistory')
            ->alias('th')
            ->join('system_template t', 't.id=th.template_id')
            ->where('app_id', $id)
            ->where(self::authWhere("th.uid"))
            ->order('th.id desc')
            ->field('th.template_id,t.name')
            ->select();

        $this->applyCsrfToken();
        $this->_form($this->table, 'template');
    }

    protected function _template_form_result()
    {
        if ($this->request->isPost()) {
            $url = '/bet365bet.php#' . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'];
            $url = preg_replace("/&temId=[0-9]*/", '', $url);
            $this->success('保存成功', ['url' => $url]);
        }
    }

    /**
     * 获取模板扩展数据和历史配置数据
     * @auth true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getTemplateInfo()
    {
        $classId = $this->request->param('classId');
        $appId = $this->request->param('appId');
        if (empty(self::authWhere())) {
            $tempData = Db::name('systemTemplate')
                ->where(['id' => $classId, 'is_deleted' => 0])
                ->field('id,package,ext_json')->find();
        } else {
            $tempData = Db::name('systemTemplate')
                ->where(['id' => $classId, 'is_deleted' => 0])
                ->where('uid', ['=', session('admin_user.id')], ['=', $this->getAdminId()], 'or')
                ->field('id,package,ext_json')->find();
        }
        if (empty($tempData)) {
            $this->error('模板不存在');
        }
        $extData = json_decode($tempData['ext_json'] ?? '', true);
        if (empty($extData)) {
            $extData = [];
        } else {
            foreach ($extData as $k => $v) {
                if (substr($k, 0, 3) == 'img') {
                    if (isset($v['val'])) {
                        $extData[$k]['val'] = $tempData['package'] . '/' . str_replace("../", "", $v['val'] ?? '');
                    } else {
                        $extData[$k] = $tempData['package'] . '/' . str_replace("../", "", $v);
                    }
                }
            }
        }
        $hisTempData = Db::name('systemTemplateHistory')->where([
            'template_id' => $classId,
            'app_id' => $appId,
        ])->where(self::authWhere())->order('id desc')->find();
        if (!empty($hisTempData)) {
            $tempData['ext_json'] = json_decode($hisTempData['data'], true);
        } else {
            $tempData['ext_json'] = $extData;
        }
        $this->success('成功', $tempData['ext_json']);
    }


    /**
     * Android集成
     * @auth true
     *
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function ad()
    {
        $id = $this->request->param('id');
        $this->row = Db::name($this->table)->where(self::authWhere())->where('id', $id)->find();
        if (!$this->row) $this->error('该应用不存在！');

        $this->row['ad_config_data'] = json_decode($this->row['ad_config_install_data'], true);
        $apk = Db::name($this->tablePackage)
            ->where('type', self::AD)
            ->where('app_id', $id)
            ->where(self::authWhere())
            ->order('created_at desc')
            ->find();
        if ($apk) {
            $apk['data'] = json_decode($apk['data'], true);
        }
        $this->apk = $apk;
        $this->apks = Db::name($this->tablePackage)
            ->where('type', self::AD)
            ->where('app_id', $id)
            ->where(self::authWhere())
            ->order('created_at desc')
            ->select();
        $query = $this->_query($this->tablePackage);
        $query->where('type', self::AD)
            ->where('app_id', $id)
            ->where(self::authWhere())
            ->order('created_at desc')
            ->page();
    }


    /**
     * Android配置
     *
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function changeAdConfig()
    {
        if ($this->request->isPost()) {
            $param = $this->request->param();

            if (!$param['ad_config_install_type'] || !in_array($param['ad_config_install_type'], [1, 2])) {
                $this->error('请选择安装方式！');
            }
            $config = json_decode($param['ad_config_install_data'], true);
            if ($param['ad_config_install_type'] == 1 && empty($config['apk_id'])) {
                $this->error('请选择托管APK！');
            }
            if ($param['ad_config_install_type'] == 2 && empty($config['download_url'])) {
                $this->error('请输入APK地址！');
            }

            $data = [
                'ad_config_install_type' => $param['ad_config_install_type'],
                'ad_config_install_data' => $param['ad_config_install_data'],
            ];
            $res = Db::name($this->table)->where(self::authWhere())->where('id', $param['id'])->update($data);
            if ($res !== false) {
                $this->success('保存成功');
            }
            $this->error('数据库操作失败，请重新操作');
        } else {
            $this->error('错误');
        }
    }

    /**
     * Ios配置
     *
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function changePgConfig()
    {
        if ($this->request->isPost()) {
            $param = $this->request->param();

            if ($param['pg_config_is_myapp'] == 1 && !$param['pg_config_myapp_url']) {
                $this->error('请输入应用宝地址！');
            }
            if (!$param['pg_config_install_type'] || !in_array($param['pg_config_install_type'], [1, 2, 3, 4])) {
                $this->error('请选择安装方式！');
            }
            if (empty($param['pg_config_install_data'])) {
                $this->error('config_data为空');
            }
            $config = json_decode($param['pg_config_install_data'], true);
            if ($param['pg_config_install_type'] == 1 && empty($config['ipa_id'])) {
                $this->error('请选择托管ipa！');
            }
            if ($param['pg_config_install_type'] == 2 && !$config['download_url']) {
                $this->error('请输入下载地址！');
            }
            if ($param['pg_config_install_type'] == 3 && !$config['app_store_url']) {
                $this->error('请输入app store地址！');
            }
            if ($param['pg_config_install_type'] == 4 && !$config['plist']) {
                $this->error('请输入plist！');
            }
            $data = [];

            $data['pg_config_install_type'] = $param['pg_config_install_type'];
            $data['pg_config_install_data'] = $param['pg_config_install_data'];
            $data['pg_config_is_myapp'] = $param['pg_config_is_myapp'];
            if ($param['pg_config_is_myapp'] == 1) {
                $data['pg_config_myapp_url'] = $param['pg_config_myapp_url'];
            }
            $res = Db::name($this->table)->where(self::authWhere())->where('id', $param['id'])->update($data);
            if ($res !== false) {
                $this->success('保存成功');
            }
            $this->error('数据库操作失败，请重新操作');
        } else {
            $this->error('错误');
        }
    }

    protected function _ad_page_filter(&$data)
    {
        foreach ($data as &$vo) {
            $vo['data'] = json_decode($vo['data'], true);
        }
    }

    protected function _ios_page_filter(&$data)
    {
        foreach ($data as &$vo) {
            $vo['data'] = json_decode($vo['data'], true);
        }
    }


    /**
     * 上传apk
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function addApk()
    {
        $this->applyCsrfToken();
        $updateData = $this->request->param('updateData');
        $appId = $this->request->param('appId');
        if (!$updateData) $this->error('包数据为空');
        if (!$appId) $this->error('appId为空');


        switch (sysconf('package_upload_type')) {
            case 'tx' :
                $path = $updateData['oss_path'];
                $iconimage = self::UPLOAD_PACKAGE_DOMAIN . $updateData['iconimage'];
                $img = $iconimage;
                $is_oss = 1;
                $oss_status = 2;
                break;
            default:
                $http = sysconf('download_package_http_type');
                $domain = sysconf('download_package_domain');
                $path = $updateData['oss_path'];
                $iconimage = "{$http}://" . $domain . $updateData['iconimage'];
                $img = $iconimage;
                $is_oss = 1;
                $oss_status = 2;
        }
        if (!$path) $this->error('上传文件路径为空');

        $data = [
            'uid' => session("admin_user")['id'],
            'app_id' => $appId,
            'path' => $path,
            'size' => $updateData['size'] . 'M',
            'type' => self::AD,
            'is_oss' => $is_oss,
            'oss_status' => $oss_status,
            'data' => json_encode([
                'package' => $updateData['signname'],
                'iconimage' => $iconimage,
                'versionName' => $updateData['version'],
                'versionCode' => $updateData['version'],
                'path' => $updateData['package_file'],
            ]),
            'created_at' => time(),
        ];
        $res = Db::name($this->tablePackage)->insert($data);
        Db::name($this->table)->where(self::authWhere())->where('id', $appId)->update(['img' => $img]);

        if ($res) {
            $this->success('操作成功');
        } else {
            $this->error('数据库添加失败！请重新操作');
        }
    }


    /**
     * 上传ipa
     *
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function addIpa()
    {
        $this->applyCsrfToken();
        $updateData = $this->request->param('updateData');
        $appId = $this->request->param('appId');
        if (!$updateData) $this->error('包数据为空');
        if (!$appId) $this->error('appId为空');

        switch (sysconf('package_upload_type')) {
            case 'tx' :
                $path = $updateData['oss_path'];
                $iconimage = self::UPLOAD_PACKAGE_DOMAIN . $updateData['iconimage'];
                $plist_path = self::UPLOAD_PACKAGE_DOMAIN . $updateData['plist_path'];
                $is_oss = 1;
                $oss_status = 2;
                break;
            default:
                $http = sysconf('download_package_http_type');
                $domain = sysconf('download_package_domain');
                $path = $updateData['oss_path'];
                $iconimage = "{$http}://" . $domain . $updateData['iconimage'];
                $plist_path = "{$http}://" . $domain . $updateData['plist_path'];
                $is_oss = 1;
                $oss_status = 2;
        }
        if (!$path) $this->error('上传文件路径为空');

        $data = [
            'uid' => session("admin_user")['id'],
            'app_id' => $appId,
            'path' => $path,
            'size' => $updateData['size'] . 'M',
            'type' => self::PG,
            'is_oss' => $is_oss,
            'oss_status' => $oss_status,
            'data' => json_encode([
                'iconimage' => $iconimage,
                'package' => $updateData['signname'],
                'versionName' => $updateData['version'],
                'versionCode' => $updateData['version'],
                'plist_path' => $plist_path,
                'path' => $updateData['package_file'],
            ]),
            'created_at' => time(),
        ];

        $res = Db::name($this->tablePackage)->insert($data);
        if ($res) {
            $this->success('操作成功');
        } else {
            $this->error('数据库添加失败！请重新操作');
        }
    }


    /**
     * 删除包文件
     * @auth true
     * @auth menu
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function removePackage()
    {
        $this->applyCsrfToken();
        $this->_delete($this->tablePackage, 'id', self::authWhere());
    }


    /**
     * ios集成
     * @auth true
     * @menu true
     */
    public function ios()
    {
        $id = $this->request->param('id');
        $this->row = Db::name($this->table)->where(self::authWhere())->where('id', $id)->find();
        if (!$this->row) $this->error('该应用不存在！');

        $this->row['pg_config_data'] = json_decode($this->row['pg_config_install_data'], true);

        $ipa = Db::name($this->tablePackage)
            ->where('type', self::PG)
            ->where('app_id', $id)
            ->where(self::authWhere())
            ->order('created_at desc')->find();
        if ($ipa) {
            $ipa['data'] = json_decode($ipa['data'], true);
        }
        $this->ipa = $ipa;
        $this->ipas = Db::name($this->tablePackage)
            ->where('type', self::PG)
            ->where('app_id', $id)
            ->where(self::authWhere())
            ->order('created_at desc')
            ->select();
        $query = $this->_query($this->tablePackage);
        $query->where('type', self::PG)
            ->where('app_id', $id)
            ->where(self::authWhere())
            ->order('created_at desc')
            ->page();
    }


    protected function _add_form_filter(&$data)
    {
        if ($this->request->isPost()) {
            if (empty($data['name'])) $this->error('请输入应用名称！');
            $data['app_key'] = substr(md5(uniqid(rand(), true)), 0, 8);
            $data['uid'] = session('admin_user.id');
            $data['status'] = 1;
            $data['download_type'] = 1;
            $data['created_at'] = time();
            $data['updated_at'] = time();
        }
    }

    protected function _template_page_filter(&$data)
    {
        dump($data);
        exit();
        foreach ($data as &$vo) {
//
        }
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
        if ($this->request->isPost()) {
            if (empty($data['template_id'])) $this->error('请选择模板！');

            $ext_data = [];
            if (isset($data['ext_key']) && count($data['ext_key']) > 0) {

                $ext_key = $data['ext_key'];
                $ext_value = $data['ext_value'];
                $ext_desc = $data['ext_desc'];
                if (count($ext_key) != count($ext_value)) {
                    $this->error('扩展数据个数不匹配');
                }
                for ($i = 0; $i < count($ext_key); $i++) {
                    if (empty(trim($ext_key[$i]))) {
                        $this->error('扩展数据不能留空');
                    }
                    $tempExtData = str_replace($this->request->domain(), '', trim($ext_value[$i]));
                    if (substr($tempExtData, 0, 3) == 'tpl') {
                        $tempExtData = '/' . $tempExtData;
                    }
                    $ext_data[trim($ext_key[$i])] = [
                        'val' => $tempExtData,
                        'desc' => trim($ext_desc[$i] ?? '')
                    ];
                }
            }

//            $extData = Db::name('SystemTemplate')->where(self::authWhere())->where('id', $data['template_id'])->value('ext_json');
//            $extData = json_decode($extData, true);
//            if (empty($extData) || count($extData) < 1) {
//                // $this->error('模板配置数据异常');
//                $extData = [];
//            }
//            if (count($ext_data) != count($extData)) {
//                $this->error('提交数据和模板配置数量不匹配');
//            }
//            if (count(array_diff_key($ext_data, $extData)) > 0) {
//                $this->error('提交数据的键和模板配置有冲突');
//            }
            $data['ext_json'] = json_encode($ext_data, JSON_UNESCAPED_UNICODE);
            unset($data['ext_key'], $data['ext_value']);
            $hisExist = Db::name('SystemTemplateHistory')
                ->where([
                    'app_id' => $data['id'],
                    'uid' => session('admin_user')['id'],
                    'template_id' => $data['template_id'],
                ])
                ->find();
            if (!empty($hisExist)) {
                Db::name('SystemTemplateHistory')
                    ->where('id', $hisExist['id'])
                    ->update([
                        'data' => $data['ext_json'],
                    ]);
            } else {
                Db::name('SystemTemplateHistory')->insert([
                    'app_id' => $data['id'],
                    'uid' => session('admin_user')['id'],
                    'template_id' => $data['template_id'],
                    'data' => $data['ext_json'],
                    'created_at' => time(),
                ]);
            }

            $data['updated_at'] = time();
            unset($data['tem_name']);
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
        if ($this->request->isPost()) {
            $id = input('id');
            $data['updated_at'] = time();
            $appData = Db::name('SystemApp')->where(self::authWhere())->where('id', $id)->find();
            if (empty($appData)) {
                $this->error('应用不存在');
            }
            Db::name('SystemAppDomain')->where(self::authWhere())->where('app_id', $id)->delete();
            $domains = input('domains');
            $domainsArr = explode("\n", trim($domains));
            if (empty($domainsArr)) {
                return;
            }
            foreach ($domainsArr as $k => $v) {
                $lineDomain = explode(" ", trim($v));
                $lineDomainKey = trim($lineDomain[0] ?? '');
                $lineDomainVal = trim($lineDomain[1] ?? '');
                if (empty($lineDomainKey)) {
                    continue;
                }
                $domainExist = Db::name('SystemAppDomain')->where(self::authWhere())->where('domain', $lineDomainKey)->count();
                if ($domainExist > 0) {
                    continue;
                }
                try {
                    Db::name('SystemAppDomain')->insert([
                        'app_id' => $id,
                        'uid' => session('admin_user.id'),
                        'domain' => $lineDomainKey,
                        'channel_code' => $lineDomainVal,
                        'statistics_code' => '',
                        'created_at' => time(),
                    ]);
                } catch (\Exception $e) {
                    continue;
                }
            }
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
     * @auth menu
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function remove()
    {
        $this->applyCsrfToken();
        $this->_delete($this->table, 'id', self::authWhere());
    }

}
