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
     * 模板列表
     * @auth true
     * @menu true
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
        $this->isAdmin = empty(self::authWhere()) ? 1 : 0;
        $query = $this->_query($this->table)->like('name');
        $query->timeBetween('created_at')->where(self::authWhere())->where(['is_deleted' => '0'])->order('id desc')->page();
    }

    protected function _index_page_filter(&$data)
    {
        foreach ($data as &$vo) {
//            $vo['preview_img'] = $vo['package_img']; // 预览缩略图url
            $vo['preview_url'] = url('preview', '', '') . '?id=' . $vo['id']; //预览页面url
            $vo['owner'] = Db::name('SystemUser')->where('id', $vo['uid'])->value('username');
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
            'download_type' => 1, // 应用下载方式，1普通下载，2openinstall
            'ad_config_install_type' => 1, // 安卓安装方式，1托管APK，2外部APK
            'ad_download_url' => $this->request->url(), // 安卓安装地址，预览时不需要下载
            'pg_config_install_type' => 1, // 苹果安装方式，1托管IPA，2外部IPA，3AppStore及其他，4外部plist
            'pg_download_url' => $this->request->url(), // 苹果安装地址，预览时不需要下载
        ];
        $extData = json_decode($tempData['ext_json'], true);
        if (!empty($extData)) {
            foreach ($extData as $k => $v) {
                $fetchData[$k] = $v;
            }
        }
        // 渲染视图
        if (is_file($tempData['package'] . '/m/index.html') && $this->request->isMobile()) {
            $fetchData['base_url'] .= 'm/';
            $this->fetch($tempData['package'] . '/m/index.html', $fetchData);
        } else if (is_file($tempData['package'] . '/index.html')) {
            $this->fetch($tempData['package'] . '/index.html', $fetchData);
        }
        abort(404, '模板不存在');
    }

    /**
     * 打包模板文件
     * @auth true
     * @menu true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function download()
    {
        $id = input('id');
        $tempData = Db::name('SystemTemplate')
            ->where('id', $id)
            ->where('uid', ['=', session('admin_user.id')], ['=', $this->getAdminId()], 'or')
            ->find();
        $filePath = './' . $tempData['package'];
        if (!is_dir($filePath)) {
            abort(404, '模板不存在');
        }
        //获取文件列表
        function list_dir($dir)
        {
            $result = [$dir];
//            $result = [];
            if (is_dir($dir)) {
                $file_dir = scandir($dir);
                foreach ($file_dir as $file) {
                    if ($file == '.' || $file == '..') {
                        continue;
                    } elseif (is_dir($dir . '/' . $file)) {
                        $result = array_merge($result, list_dir($dir . '/' . $file));
                    } else {
                        array_push($result, $dir . '/' . $file);
                    }
                }
            }

            return $result;
        }

        //获取列表
        $datalist = list_dir($filePath);
//        dump($datalist);
//        exit();
        $tmpSavePath = './muban_download';
        if (!is_dir($tmpSavePath)) {
            mkdir($tmpSavePath);
        }
        $filename = $tmpSavePath . '/' . md5(microtime(true)) . '.zip'; //最终生成的文件名（含路径）
        if (!file_exists($filename)) {
            //重新生成文件
            $zip = new ZipArchive();//使用本类，linux需开启zlib，windows需取消php_zip.dll前的注释
            if ($zip->open($filename, ZIPARCHIVE::CREATE) !== TRUE) {
                abort(500, '无法打开文件，或者文件创建失败');
            }
            foreach ($datalist as $val) {
                $tmpFileName = str_replace($datalist[0], '', $val);
                if (is_dir($val)) {
                    $zip->addEmptyDir($tmpFileName);

                } else {
                    $zip->addFile($val, $tmpFileName);//第二个参数是放在压缩包中的文件名称，如果文件可能会有重复，就需要注意一下
                }
            }
            $zip->close();//关闭
        }
        if (!file_exists($filename)) {
            abort(500, '打包压缩文件失败');
        }
        $dlName = $tempData['name'] . '.zip';
        header("Cache-Control: public");
        header("Content-Description: File Transfer");
        header('Content-disposition: attachment; filename=' . $dlName); //文件名
        header("Content-Type: application/zip"); //zip格式的
        header("Content-Transfer-Encoding: binary"); //告诉浏览器，这是二进制文件
        header('Content-Length: ' . filesize($filename)); //告诉浏览器，文件大小
        @readfile($filename);
    }


    /**
     * 添加模板
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
     * 编辑模板
     * @auth true
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

    protected function _form_filter(&$data)
    {
        if ($this->request->isPost()) {
            $uid = session('admin_user.id') ?? 0;
            if (isset($data['package']) && !empty($data['package'])) { // 有上传压缩包
                $file = str_replace('\\', '/', env('root_path') . "safefile/" . $data['package']);
                if (!is_file($file)) {
                    $this->error('上传的压缩文件找不到');
                }
                $savePath = str_replace('\\', '/', env('root_path') . 'public/tpl/' . $uid . '/' . md5_file($file));
                $zip = new ZipArchive;
                if ($zip->open($file) === TRUE) {
                    $zip->extractTo($savePath);
                    $zip->close();
                } else {
                    $this->error('压缩文件格式错误或已损坏');
                }
                $data['package'] = 'tpl/' . $uid . '/' . md5_file($file);
                $data['package_img'] = $data['package'] . '/index.png';

                $extJson = $data['package'] . '/index.json';
                if (is_file($extJson) && !empty(json_decode(file_get_contents($extJson), true))) { // 判断是否存在json配置文件
                    $data['ext_json'] = file_get_contents($extJson);
                } else {
                    $this->error('压缩包里的json文件不存在');
                }
            } else { // 未上传压缩包
                unset($data['package']);
            }
            if (isset($data['id'])) {
                $data['updated_at'] = time();
                Db::name('SystemTemplate')->where('id', $data['id'])->where(self::authWhere())->update($data);
            } else {
                $data['created_at'] = time();
                $data['uid'] = $uid;
            }
        }
    }

    /**
     * 删除模板
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function remove()
    {
        $this->applyCsrfToken();
        $this->_delete($this->table, '', self::authWhere());
    }

}
