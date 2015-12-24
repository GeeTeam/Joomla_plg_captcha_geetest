<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Captcha
 *
 * @copyright   Phpsj.com All rights reserved.
 * @license     GNU General Public License version 2 or later;
 */

defined('_JEXEC') or die;

require_once dirname(__FILE__).'/class.geetestlib.php';

class PlgCaptchaGeetest extends JPlugin {
    //加载语言文件
    protected $autoloadLanguage = true;

    //初始化验证码
    public function onInit($id = 'dynamic_geetest_1') {
        $document = JFactory::getDocument();
        $app = JFactory::getApplication();
        $lang = JFactory::getLanguage();
        JHtml::_('jquery.framework');
        
        //检查是否存在公钥
        $pubkey = $this->params->get('public_key','');
        if($pubkey == null || $pubkey == '') {
            throw new Exception(JText::_('PLG_GEETEST_ERROR_NO_PUBLIC_KEY'));
        }
        //常量公钥
        if(!defined('CAPTCHA_ID')){
            define('CAPTCHA_ID', $pubkey);
        }
        //检测语言
        if($lang->getTag() == 'zh-CN'){
            $glang='zh-cn';
        }else{
            $glang='en';
        }
        
        //定义一些JS
        $GtSdk = new GeetestLib();
        $return = $GtSdk->register();
        if ($return) {
            $_SESSION['gtserver'] = 1;
            $result = array('success' => 1,'gt' => CAPTCHA_ID,'challenge' => $GtSdk->challenge);
        }else{
            $_SESSION['gtserver'] = 0;
            $rnd1 = md5(rand(0,100));
            $rnd2 = md5(rand(0,100));
            $challenge = $rnd1 . substr($rnd2,0,2);
            $result = array('success' => 0,'gt' => CAPTCHA_ID,'challenge' => $challenge);
            $_SESSION['challenge'] = $result['challenge'];
        }
        
        //$file='http://static.geetest.com/static/js/geetest.0.0.0.js';
        //JHtml::_('script', $file, true, true);
        $document->addScriptDeclaration('
            jQuery(document).ready(function(){
                if(window.hasOwnProperty("Geetest")){
                    new window.Geetest({
                        gt : "'.$result['gt'].'",
                        challenge : "'.$result['challenge'].'",
                        product : "float", //下一版本增加可选
                        offline : !'.$result['success'].',
                        lang : "'.$glang.'" //下一版本增加可选
                    }).appendTo("#'.$id.'");
                }
            });
        ');
        return true;
    }

    //设置验证码html表单
    public function onDisplay($name = null,$id = 'dynamic_geetest_1',$class = '') {
        return '
            <div class="geetest_box" id="'.$id.'">
               <script type="text/javascript"
                  src="http://api.geetest.com/get.php">
               </script>
            </div>
        ';
    }

    //进行验证码校验
    public function onCheckAnswer($code = null) {
        //检查是否存在私钥
        $privatekey = $this->params->get('private_key','');
        if($privatekey == null || $privatekey == '') {
            $this->_subject->setError(JText::_('PLG_GEETEST_ERROR_NO_PRIVATE_KEY'));
            return false;
        }
        //常量私钥
        if(!defined('PRIVATE_KEY')){
            define('PRIVATE_KEY', $privatekey);
        }
        
        $input = JFactory::getApplication()->input;
        $geetest_challenge = $input->get('geetest_challenge','','string');
        $geetest_validate = $input->get('geetest_validate','','string');
        $geetest_seccode = $input->get('geetest_seccode','','string');

        $GtSdk = new GeetestLib();
        if (isset($_SESSION['gtserver']) && $_SESSION['gtserver'] == 1) {
            $result = $GtSdk->validate($geetest_challenge,$geetest_validate,$geetest_seccode);
            if ($result == TRUE) {
                $return=true;
            } else if ($result == FALSE) {
                $this->_subject->setError(JText::_('PLG_GEETEST_ERROR_INVALID'));
                $return=false;
            } else {
                $this->_subject->setError(JText::_('PLG_GEETEST_ERROR_UNKNOWN'));
                $return=false;
            }
        }else{
            if ($GtSdk->get_answer($geetest_seccode)) {
                $return=true;
            }else{
                $this->_subject->setError(JText::_('PLG_GEETEST_ERROR_INVALID'));
                $return=false;
            }
        }//var_dump($return);exit();
        return $return;
    }
}
