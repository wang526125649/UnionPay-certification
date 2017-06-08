<?php

namespace Jingapp\Controller;
use Think\Controller;
/**
 *实名验证
 **/

class CertificationController extends CshCompetenceController {
	private $_chnlid = ""; //渠道号
	private $_userid = ""; //总管账号
	private $_secondaryid = '';//二级用户id
	private $_key = "";//银联提供的MD5秘钥
	private $_getDynKey = "";//获取动态秘钥链接
	private $_apiTrans = "";//认证链接
	private $_3des = "";//银联提供的3EDS加密密匙 

	/**
	 * 实名验证提交入口
	 * @param string name 名字
	 * @param string certificatecode 身份证号
	 * @return jsong
	 */
	public function card_submit_100(){
		$name = trim(I('post.name'));//姓名:M
		$type = trim(I('post.type'));//01-香港身份证，02-护照；03-军官证；04-港澳居民往来内地通行证 05-台湾居民往来大陆通行证 06-警官证 07-士兵证 09-客户号 10—身份证；11-职工编号；20-其它。:M
		$certificatecode = trim(I('post.certificatecode'));//证件号码:M\

		$image_positive = $this->upload('image_positive',$certificatecode,'(1)');//身份证正面地址
		$image_opposite = $this->upload('image_opposite',$certificatecode,'(2)');//身份证反面地址
		(_empty($type)) ? $this->ajaxReturn("证件类型不正确") : '' ;
		(_empty($certificateCode)) ? $this->ajaxReturn("证件号填写不正确") : '' ;
		($image_positive == 'error') ? $this->ajaxReturn("证件正面图片上传不成功") : '' ;
		($image_opposite == 'error') ? $this->ajaxReturn("证件反面图片上传不成功") : '' ;

		$restus = $this->verification($name,$certificatecode);
		if($restus['resultCode'] == '00'){
			$this->ajaxReturn("身份证信息验证通过");
		}else{
			$this->ajaxReturn("身份证信息验证不通过");
		}
		 
	}

	/**
	 * 获取动态秘钥
	 * @return jsong
	 */

	private function obtain(){
		$time = date('YmdHis',time());//按照银联规定定义时间格式

		//定义MD5摘要
		$md5 = md5("UTF-81.0.004131".$this->_chnlid.$this->_userid.$time.$time.$this->_key);

		//定义传递的参数数组；
		$xml = '<?xml version="1.0" encoding="GBK" standalone="yes"?>';
    	$xml .= '<requestData>';
    	$xml .= '<charCode>UTF-8</charCode>'; //参数编码方式，UTF-8
		$xml .= '<version>1.0.0</version>'; //接口版本号（1.0.0）
		$xml .= '<tradeType>0413</tradeType>'; //交易类型
		$xml .= '<tradeSource>1</tradeSource>'; //交易方式，0为前台页面，1为后台接口
		$xml .= '<chnlId>'.$this->_chnlid.'</chnlId>'; //渠道号
		$xml .= '<userId>'.$this->_userid.'</userId>'; //发起交易的二级用户ID
		$xml .= '<orderId>'.$time.'</orderId>'; //订单号
		$xml .= '<timeStamp>'.$time.'</timeStamp>'; //时间戳 当前时间
		$xml .= '<md5ConSec>'.$md5.'</md5ConSec>'; //加密后的摘要
    	$xml .= '</requestData>';
		
		//使用3DES加密报文数据
		Vendor("3DES.encrypt");//调用第三方类库
		$rep = new \encrypt($this->_3des);    
		$encrypt=$rep->encrypt($xml);  
		//上送报文 
		//4位报文体字节长度+用户ID（12位，不足12位的在左补空格）+渠道号（12位）+交易方式（1位，0为前台页面，1为后台接口）+加密后的xml报文体
		if(strlen($encrypt)<1000){
			$len = "0".strlen($encrypt);
		}elseif(strlen($encrypt)<100){
			$len = "00".strlen($encrypt);
		}else{
			$len = "000".strlen($encrypt);
		}
 
		$send = $len.$this->_chnlid.$this->_userid."1".$encrypt;
		//日志
		$this->sclog("身份证：获取动态秘钥报文///".$send);
		//定义返回值接收变量；
		$decrypt = $this->http($this->_getDynKey, $send);
		if(!empty($decrypt)){
			//截取返回报文3DES数据
			$subst = substr($decrypt,29);
			// 解密3DES报文
			$ret = $rep->decrypt($subst);
			if(!empty($ret)){
				//解析读取xml数据
				libxml_disable_entity_loader(true); 
				$xmlstring = simplexml_load_string($ret, 'SimpleXMLElement', LIBXML_NOCDATA); 
				$val = json_decode(json_encode($xmlstring),true);
				return $val;
			}else{
				$this->ajaxReturn("动态密匙获取失败");
			}		
		}else{
			$this->ajaxReturn("返回值接收出错");
		}
	}

	/**
	 * 身份验证信息报文提交
	 * @param string accNo 银行卡号
	 * @param string certificateCode 身份证号
	 * @return jsong
	 */

	private function verification($name,$certificatecode){
		$val = $this->obtain();
		
		//定义MD5摘要
		$md5 = md5(mb_convert_encoding("UTF-81.0.004171".$val['chnlId'].$this->_secondaryid.$val['orderId'].$val['timeStamp'].$name.$certificatecode.$this->_key,"GBK","UTF-8"));
		
		//定义传递的参数数组；
		$xml = '<?xml version="1.0" encoding="GBK" standalone="yes"?>';
    	$xml .= '<requestData>';
    	$xml .= '<charCode>UTF-8</charCode>'; //参数编码方式，UTF-8
		$xml .= '<version>1.0.0</version>'; //接口版本号（1.0.0）
		$xml .= '<tradeType>0417</tradeType>'; //交易类型
		$xml .= '<tradeSource>1</tradeSource>'; //交易方式，0为前台页面，1为后台接口
		$xml .= '<chnlId>'.$val['chnlId'].'</chnlId>'; //渠道号
		$xml .= '<userId>'.$this->_secondaryid.'</userId>'; //发起交易的二级用户ID
		$xml .= '<orderId>'.$val['orderId'].'</orderId>'; //订单号
		$xml .= '<timeStamp>'.$val['timeStamp'].'</timeStamp>'; //时间戳 当前时间
		$xml .= "<name>".$name."</name>"; //姓名
		$xml .= "<certificateCode>".$certificatecode."</certificateCode>"; //身份证号
		$xml .= '<md5ConSec>'.$md5.'</md5ConSec>'; //加密后的摘要
    	$xml .= '</requestData>';

		//加密密匙 
		$key = substr(md5($this->_3des.$val['random']),4,-4);
		//使用3DES加密报文数据
		Vendor("3DES.encrypt");//调用第三方类库
		$rep = new \encrypt($key);    
		$encrypt=$rep->encrypt($xml);  
		//上送报文 
		//4位报文体字节长度+用户ID（12位，不足12位的在左补空格）+渠道号（12位）+交易方式（1位，0为前台页面，1为后台接口）+加密后的xml报文体
		if(strlen($encrypt)<1000){
			$len = "0".strlen($encrypt);
		}elseif(strlen($encrypt)<100){
			$len = "00".strlen($encrypt);
		}else{
			$len = "000".strlen($encrypt);
		}
		$useridy = sprintf("%12s",$this->_secondaryid);

		$send = $len.$useridy.$this->_chnlid."1".$encrypt;
		//日志
		$this->sclog("身份证：认证报文///".$send);
		//定义返回值接收变量；
		$decrypt = $this->http($this->_apiTrans, $send);
		if(!empty($decrypt)){
			//截取返回报文3DES数据
			$subst = substr($decrypt,29);
			// 解密3DES报文
			$rest = $rep->decrypt($subst);
			if(!empty($rest)){
				//解析读取xml数据
				$rests = iconv('utf-8', 'gb2312', $rest);
				libxml_disable_entity_loader(true); 
				$xmlstring = simplexml_load_string($rests, 'SimpleXMLElement', LIBXML_NOCDATA);
				$val = json_decode(json_encode($xmlstring),true);
				return $val;
			}else{
				$this->ajaxReturn("无法收到返回数据");
			}		
		}else{
			$this->ajaxReturn("返回值接收出错");
		} 

		
	}

	/**
	* 发送HTTP请求方法
	* @param  string $url    请求URL
	* @param  array  $data 请求参数
	* @return array  $a  响应数据
	*/
	private function http($url, $data){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url); //链接
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //是否输出 0/1
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查  
	    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, true);  // 从证书中检查SSL加密算法是否存在  
		curl_setopt($ch,CURL_HTTPHEADER,'Content-type: text/xml;charset=GBK'); //头信息
		curl_setopt($ch, CURLOPT_POST, 1); // 是否用post
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data); // 传输数据
	
		$a = curl_exec($ch);
		return $a;
	}
	/**
	* 上传图片类
	* @param  string  $image 请求参数
	* @param  string  $name 请求名字
	* @param  string  $val 请求类型
	*/
	private function upload($image,$name,$val=''){
		$upload = new \Think\Upload();// 实例化上传类
		$upload->maxSize =  0;// 设置附件上传大小
		$upload->exts  = array('jpg', 'gif', 'png', 'jpeg');// 设置附件上传类型
		$upload->rootPath = './Uploads/'; // 设置附件上传根目录		
		$upload->subName = 'yinlian';//子目录创建方式，[0]-函数名，[1]-参数，多个参数使用数组
		$upload->saveName = $name.$val;// 设置文件上传名
		// 上传文件 
		$info   =   $upload->upload();
		if(!$info) {// 上传错误提示错误信息
			return 'error';
		}else{// 上传成功
			foreach($info as $file){
				return $file['savepath'].$file['savename'];
			}
		}
	}
	/**
	 * 日志输出函数
	 */
	protected function sclog($log_str,$file = 'log'){
		$filename=$_SERVER['DOCUMENT_ROOT'] . __ROOT__ . '/Public/yinlian/'.date("Y-m-d",time()).'_'.$file.'.txt';
		$handle = fopen($filename, 'a'); 
		//日志输出
		if($log_str == ''){
			error_log("\r\n\r\n".date("Hi",time())."\r\n",3,$filename);
		}else{
			fwrite($handle,$log_str."\r\n");
			error_log("\r\n---------------------END----",3,$filename);
			error_log(date("[Y-m-d H:i:s]")."----------\r\n\r\n",3,$filename);
		}
		
	}
}