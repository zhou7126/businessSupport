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

namespace app\index\controller;

use library\Controller;
use think\Db;
use think\exception\HttpException;
use think\Request;

/**
 * 模板路由控制器
 * Class Index
 * @package app\index\controller
 */
class Index extends Controller
{
    /**
     * 入口域名判断
     */
    public function index()
    {
        $domain = $this->request->host();
        $schemeDomain = $this->request->domain();
        //dump($domain);
        //dump($schemeDomain);

        // 域名跳转
        $jumpData = Db::name('SystemDomain')->where(['call_domain' => $domain, 'status' => 1])->find();
        //dump($jumpData);
        if (!empty($jumpData) && !empty($jumpData['jump_domain'])) {
            $jumpUrl = stripos($jumpData['jump_domain'], 'http') !== false ? $jumpData['jump_domain'] : "http://" . $jumpData['jump_domain'];
            echo "<script language='JavaScript'>self.location='{$jumpUrl}';</script>";
            exit();
        }

        // 域名绑定
        $bindData = Db::name('SystemApp')->where([
            ['domain', 'like', "%{$domain}%"],
            ['status', '=', 1]
        ])->find();
        if (!empty($bindData)) {
            $channelCode = $bindData['channel_code'];
            $bindDomain = explode("\n", trim($bindData['domain']));
            foreach ($bindDomain as $v) {
                $tempDomain = explode(" ", trim($v));
                $tempDomainKey = '';
                $tempDomainVal = '';
                foreach ($tempDomain as $v1) {
                    if (trim($v1) !== '') {
                        if (empty($tempDomainKey)) {
                            $tempDomainKey = trim($v1);
                        } elseif (empty($tempDomainVal)) {
                            $tempDomainVal = trim($v1);
                        } else {
                            break;
                        }
                    }
                }

                if (stripos($tempDomainKey, $domain) !== false && !empty($tempDomainVal)) {
                    $channelCode = $tempDomainVal;
                    break;
                }
            }
            $template_id = $bindData['template_id'];
            $tempData = Db::name('SystemTemplate')->where(['id' => $template_id, 'is_deleted' => 0])->find();
            if (!empty($tempData)) {
                $fetchData = [
                    'base_url' => $schemeDomain . '/' . $tempData['package'] . '/', // 页面默认URL
                    'download_type' => $bindData['download_type'], // 应用下载方式，1普通下载，2openinstall
                    'channel_code' => $channelCode, // 渠道号
                    'ad_config_install_type' => $bindData['ad_config_install_type'], // 安卓安装方式，1托管APK，2外部APK
                    'ad_download_url' => '', // 安卓安装地址
                    'pg_config_install_type' => $bindData['pg_config_install_type'], // 苹果安装方式，1托管IPA，2外部IPA，3AppStore及其他，4外部plist
                    'pg_download_url' => '', // 苹果安装地址
                    'statistics_code' => $bindData['statistics_code'], // 统计代码
                    'openintsall_app_key' => $bindData['openintsall_app_key'],
                ];
                if (!empty($bindData['ext_json']) && !empty(json_decode($bindData['ext_json'], true))) {
                    foreach (json_decode($bindData['ext_json'], true) as $extKey => $extVal) {
                        $fetchData[$extKey] = is_array($extVal) ? json_encode($extVal, JSON_UNESCAPED_UNICODE) : $extVal;
                    }
                }

                // 查询安卓下载地址
                $adInstallArr = json_decode($bindData['ad_config_install_data'], true);
                if ($fetchData['ad_config_install_type'] == 1) {
                    $packageData = Db::name('SystemPackage')->where('id', $adInstallArr['apk_id'])->find();
                    if (!empty($packageData) && !empty($packageData['path'])) {
                        $fetchData['ad_download_url'] = $packageData['path'];
                    }
                } else {
                    $fetchData['ad_download_url'] = $adInstallArr['download_url'];
                }

                // 查询苹果下载地址
                $pgInstallArr = json_decode($bindData['pg_config_install_data'], true);
                if ($fetchData['pg_config_install_type'] == 1) {
                    $packageData = Db::name('SystemPackage')->where('id', $pgInstallArr['ipa_id'])->find();
                    if (!empty($packageData) && !empty($packageData['path'])) {
                        $plistData = json_decode($packageData['data'], true);
                        if (isset($plistData['plist_path'])) {
                            $fetchData['pg_download_url'] = "itms-services://?action=download-manifest&url=" . $plistData['plist_path'];
                        }
                    }
                } else if ($fetchData['pg_config_install_type'] == 2) {
                    $fetchData['pg_download_url'] = $pgInstallArr['download_url'];
                } else if ($fetchData['pg_config_install_type'] == 3) {
                    $fetchData['pg_download_url'] = $pgInstallArr['app_store_url'];
                } else {
                    $fetchData['pg_download_url'] = 'itms-services://?action=download-manifest&url=' . $pgInstallArr['plist'];
                }

                // 渲染视图
                if (is_file($tempData['package'] . '/m/index.html') && $this->request->isMobile()) {
                    $fetchData['base_url'] .= 'm/';
                    $this->fetch($tempData['package'] . '/m/index.html', $fetchData);
                } else if (is_file($tempData['package'] . '/index.html')) {
                    $this->fetch($tempData['package'] . '/index.html', $fetchData);
                }
            }
        }

        echo "<script language='JavaScript'>self.location='/404';</script>";
        exit();
    }

}
